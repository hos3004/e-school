<?php

declare(strict_types=1);

namespace Modules\Groups\Application\Services;

use Illuminate\Support\Facades\Gate;
use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Groups\Application\Actions\ActivateGroupAction;
use Modules\Groups\Application\Actions\AssignTeacherAction;
use Modules\Groups\Application\Actions\AttachProgramAction;
use Modules\Groups\Application\Actions\CreateGroupAction;
use Modules\Groups\Application\Actions\UpdateGroupAction;
use Modules\Groups\Domain\Enums\GroupStatus;
use Modules\Groups\Domain\Models\Group;
use Modules\Staff\Domain\Contracts\TeacherDirectoryQueries;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\LocalizedJsonColumn;
use Shared\Support\Transaction;

/** Group setup for the console; no group models cross this boundary. */
final readonly class ConsoleSetupService
{
    public function __construct(
        private CreateGroupAction $createGroup,
        private UpdateGroupAction $updateGroup,
        private AttachProgramAction $attachProgram,
        private AssignTeacherAction $assignTeacher,
        private ActivateGroupAction $activateGroup,
        private AcademicCatalogQueries $academics,
        private TeacherDirectoryQueries $teachers,
        private Transaction $transaction,
    ) {}

    /** @return list<array<string, mixed>> */
    public function groups(string $organizationId): array
    {
        Gate::authorize('viewAny', Group::class);
        $groups = Group::query()->forOrganization($organizationId)
            ->with(['programs', 'teachers' => fn ($query) => $query->whereNull('assigned_to')])
            ->withCount(['memberships as occupied_seats' => fn ($query) => $query->whereNull('left_at')])
            ->orderByDesc('created_at')->get();
        $teacherIds = $groups->flatMap(fn (Group $group) => $group->teachers->pluck('staff_profile_id'))->unique()->values()->all();
        $teachers = $this->teachers->directoryFor($organizationId, $teacherIds);

        return $groups->map(function (Group $group) use ($teachers): array {
            $missing = [];
            if (config('groups.activation.requires_capacity') && $group->capacity === null) {
                $missing[] = 'capacity';
            }
            if (config('groups.activation.requires_start_date') && $group->starts_on === null) {
                $missing[] = 'starts_on';
            }
            if (config('groups.activation.requires_teacher') && $group->teachers->isEmpty()) {
                $missing[] = 'teacher';
            }

            return [
                ...$group->only(['id', 'code', 'name', 'capacity', 'timezone']),
                'label' => LocalizedJsonColumn::display($group->name),
                'status' => $group->status->value,
                'starts_on' => $group->starts_on?->toDateString(),
                'ends_on' => $group->ends_on?->toDateString(),
                'occupied_seats' => (int) $group->getAttribute('occupied_seats'),
                'program_ids' => $group->programs->pluck('program_id')->values()->all(),
                'missing' => $missing,
                'can_activate' => $group->status === GroupStatus::Planning && $missing === [] && ($group->capacity === null || $group->capacity >= (int) $group->getAttribute('occupied_seats')),
                'teachers' => $group->teachers->map(fn ($assignment): array => [
                    'id' => (string) $assignment->getKey(),
                    'staff_profile_id' => (string) $assignment->staff_profile_id,
                    'name' => $teachers[(string) $assignment->staff_profile_id]->name ?? (string) $assignment->staff_profile_id,
                    'course_id' => $assignment->course_id,
                    'role' => $assignment->role->value,
                ])->values()->all(),
            ];
        })->values()->all();
    }

    /** @param array<string, mixed> $data */
    public function save(string $organizationId, ?string $id, array $data, string $actorId, string $reason): string
    {
        return $this->transaction->run(function () use ($organizationId, $id, $data, $actorId, $reason): string {
            $group = $id === null ? null : $this->find($organizationId, $id);
            Gate::authorize($group === null ? 'create' : 'update', $group ?? Group::class);
            $programIds = $data['program_ids'] ?? [];
            if ($group !== null) {
                if (isset($data['name']) && is_array($data['name'])) {
                    $data['name'] = array_replace(is_array($group->name) ? $group->name : [], $data['name']);
                }
                $removed = array_diff($group->programs()->pluck('program_id')->all(), $programIds);
                if ($removed !== []) {
                    throw BusinessRuleViolation::make('groups.program_detach_required', 'console_courses.program_links_help');
                }
                if ($group->status === GroupStatus::Active) {
                    $missing = [];
                    foreach (['capacity' => 'requires_capacity', 'starts_on' => 'requires_start_date'] as $field => $setting) {
                        if (config('groups.activation.'.$setting) && array_key_exists($field, $data) && $data[$field] === null) {
                            $missing[] = __('groups::attributes.'.$field);
                        }
                    }
                    if ($missing !== []) {
                        throw BusinessRuleViolation::make('groups.activation_data_incomplete', 'groups::errors.activation_data_incomplete', ['missing' => implode('، ', $missing)]);
                    }
                }
            }
            unset($data['program_ids']);
            if ($group !== null && isset($data['capacity']) && (int) $data['capacity'] < $group->memberships()->whereNull('left_at')->count()) {
                throw BusinessRuleViolation::make('groups.capacity_below_members', 'groups::errors.capacity_below_members', [
                    'capacity' => $data['capacity'], 'members' => $group->memberships()->whereNull('left_at')->count(),
                ]);
            }
            $saved = $group === null
                ? $this->createGroup->execute([...$data, 'organization_id' => $organizationId], $actorId, $reason)
                : $this->updateGroup->execute($group, $data, $actorId, $reason);
            foreach ($programIds as $programId) {
                if (!$saved->programs()->where('program_id', $programId)->exists()) {
                    $this->attachProgram->execute($saved, (string) $programId, $actorId, $reason);
                }
            }

            return (string) $saved->getKey();
        });
    }

    /** @param array<string, mixed> $data */
    public function assign(string $organizationId, string $id, array $data, string $actorId, string $reason): void
    {
        $group = $this->find($organizationId, $id);
        Gate::authorize('assignTeacher', $group);
        $programIds = $group->programs()->pluck('program_id')->all();
        $course = $this->academics->coursesByIds($organizationId, [(string) $data['course_id']])[(string) $data['course_id']] ?? null;
        $valid = $course !== null && in_array($course->programId, $programIds, true) && $course->sessionMode !== 'individual';
        if (!$valid) {
            throw BusinessRuleViolation::make('groups.course_not_found', 'groups::errors.course_not_found');
        }
        $this->assignTeacher->execute($group, $data, $actorId, $reason);
    }

    public function activate(string $organizationId, string $id, string $actorId, string $reason): void
    {
        $group = $this->find($organizationId, $id);
        Gate::authorize('activate', $group);
        $this->activateGroup->execute($group, $actorId, $reason);
    }

    private function find(string $organizationId, string $id): Group
    {
        return Group::query()->forOrganization($organizationId)->lockForUpdate()->findOrFail($id);
    }
}
