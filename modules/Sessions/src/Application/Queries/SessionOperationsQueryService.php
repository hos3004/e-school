<?php

declare(strict_types=1);

namespace Modules\Sessions\Application\Queries;

use Illuminate\Support\Facades\DB;
use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Academics\Domain\ValueObjects\AcademicCatalogItemData;
use Modules\Audit\Domain\Contracts\AuditQueryService;
use Modules\Groups\Domain\Contracts\GroupAdministrationQueries;
use Modules\Groups\Domain\ValueObjects\SchedulingGroupData;
use Modules\Identity\Domain\Contracts\UserAccountDirectory;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Domain\Models\SessionParticipant;
use Modules\Sessions\Domain\Models\TeacherApology;
use Modules\Staff\Domain\Contracts\StaffQueries;
use Modules\Students\Domain\Contracts\StudentDirectoryQueries;
use Modules\Students\Domain\ValueObjects\StudentDirectoryData;

/** يجمع شاشة تشغيل الحصة من DTOs وعقود الموديولات المالكة. */
final class SessionOperationsQueryService
{
    /** @var array<string, array<string, AcademicCatalogItemData>> */
    private array $courses = [];

    /** @var array<string, array<string, SchedulingGroupData>> */
    private array $groups = [];

    /** @var array<string, array<string, string>> */
    private array $teachers = [];

    /** @var array<string, array<string, string>> */
    private array $students = [];

    public function __construct(
        private readonly AcademicCatalogQueries $academics,
        private readonly GroupAdministrationQueries $groupQueries,
        private readonly StaffQueries $staff,
        private readonly StudentDirectoryQueries $studentQueries,
        private readonly UserAccountDirectory $accounts,
        private readonly AuditQueryService $audit,
    ) {}

    public function courseLabel(string $organizationId, string $courseId): string
    {
        $this->loadCourses($organizationId, [$courseId]);
        $course = $this->courses[$organizationId][$courseId] ?? null;

        return $course === null ? $courseId : $this->catalogLabel($course);
    }

    public function programLabelForCourse(string $organizationId, string $courseId): string
    {
        $this->loadCourses($organizationId, [$courseId]);
        $programId = $this->courses[$organizationId][$courseId]->programId ?? null;
        if ($programId === null) {
            return __('sessions::fields.not_available');
        }

        $program = $this->academics->programsByIds($organizationId, [$programId])[$programId] ?? null;

        return $program === null ? $programId : $this->catalogLabel($program);
    }

    public function groupLabel(string $organizationId, ?string $groupId): string
    {
        if ($groupId === null || $groupId === '') {
            return __('sessions::fields.not_available');
        }

        $this->loadGroups($organizationId, [$groupId]);
        $group = $this->groups[$organizationId][$groupId] ?? null;

        return $group === null ? $groupId : $this->localized($group->name).' · '.$group->code;
    }

    public function groupTimezone(string $organizationId, ?string $groupId): string
    {
        if ($groupId === null || $groupId === '') {
            return 'UTC';
        }

        $this->loadGroups($organizationId, [$groupId]);
        $group = $this->groups[$organizationId][$groupId] ?? null;

        return $group === null ? 'UTC' : $group->timezone;
    }

    public function teacherLabel(string $organizationId, ?string $staffProfileId): string
    {
        if ($staffProfileId === null || $staffProfileId === '') {
            return __('sessions::fields.not_available');
        }

        $this->loadTeachers($organizationId, [$staffProfileId]);

        return $this->teachers[$organizationId][$staffProfileId] ?? $staffProfileId;
    }

    public function studentLabel(string $organizationId, string $studentProfileId): string
    {
        $this->loadStudents($organizationId, [$studentProfileId]);

        return $this->students[$organizationId][$studentProfileId] ?? $studentProfileId;
    }

    /**
     * @param list<string> $groupIds
     * @return array<string, string>
     */
    public function groupOptions(string $organizationId, array $groupIds): array
    {
        $this->loadGroups($organizationId, $groupIds);
        $options = [];
        foreach ($groupIds as $groupId) {
            $options[$groupId] = $this->groupLabel($organizationId, $groupId);
        }

        return $options;
    }

    /**
     * @param list<string> $teacherIds
     * @return array<string, string>
     */
    public function teacherOptions(string $organizationId, array $teacherIds): array
    {
        $this->loadTeachers($organizationId, $teacherIds);

        return array_intersect_key($this->teachers[$organizationId] ?? [], array_flip($teacherIds));
    }

    public function sessionLabel(string $organizationId, string $sessionId): string
    {
        $session = Session::query()
            ->forOrganization($organizationId)
            ->whereKey($sessionId)
            ->first(['id', 'title', 'scheduled_start']);

        if ($session === null) {
            return $sessionId;
        }

        return $this->localized(is_array($session->title) ? $session->title : [])
            .' · '.$session->scheduled_start->format('Y-m-d H:i');
    }

    /** @return array<string, list<array<string, mixed>>> */
    public function sessionHub(string $organizationId, Session $session): array
    {
        $participants = $session->participants()
            ->withTrashed()
            ->orderBy('created_at')
            ->get();
        $studentIds = $participants
            ->pluck('student_profile_id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->values()
            ->all();
        $this->loadStudents($organizationId, $studentIds);

        $substitutions = DB::table('session_substitutions')
            ->where('organization_id', $organizationId)
            ->where('session_id', (string) $session->getKey())
            ->orderByDesc('assigned_at')
            ->get();
        $teacherIds = array_values(array_unique(array_filter([
            (string) $session->original_teacher_id,
            (string) $session->staff_profile_id,
            ...$substitutions->flatMap(static fn (object $item): array => [
                (string) $item->original_teacher_id,
                (string) $item->substitute_teacher_id,
            ])->all(),
        ])));
        $this->loadTeachers($organizationId, $teacherIds);

        $history = $session->statusHistory()
            ->orderByDesc('changed_at')
            ->get()
            ->map(static fn ($item): array => [
                'id' => (string) $item->getKey(),
                'from_status' => $item->from_status === null
                    ? __('sessions::fields.not_available')
                    : (SessionStatus::tryFrom((string) $item->from_status)?->label() ?? (string) $item->from_status),
                'to_status' => SessionStatus::tryFrom((string) $item->to_status)?->label() ?? (string) $item->to_status,
                'reason' => $item->reason,
                'changed_at' => $item->changed_at?->toIso8601String(),
            ])->values()->all();

        $apologies = TeacherApology::query()
            ->forOrganization($organizationId)
            ->where('session_id', (string) $session->getKey())
            ->latest('submitted_at')
            ->get()
            ->map(fn (TeacherApology $item): array => [
                'id' => (string) $item->getKey(),
                'teacher' => $this->teacherLabel($organizationId, (string) $item->staff_profile_id),
                'status' => __('sessions::apology.status.'.$item->status->value),
                'reason' => $item->reason,
                'submitted_at' => $item->submitted_at?->toIso8601String(),
                'is_late_notice' => (bool) $item->is_late_notice,
                'decision_reason' => $item->decision_reason,
            ])->values()->all();

        $audits = $this->audit->paginateForOrganization($organizationId, [
            'auditable_type' => 'sessions',
            'auditable_id' => (string) $session->getKey(),
        ], (int) config('sessions.admin_hub.max_items'));
        $auditItems = $audits->items();
        $actorIds = [];
        foreach ($auditItems as $item) {
            if ($item->actorId !== null) {
                $actorIds[] = $item->actorId;
            }
        }
        $actors = $this->accounts->findMany(
            $organizationId,
            array_values(array_unique($actorIds)),
        );
        $auditRows = [];
        foreach ($auditItems as $item) {
            $auditRows[] = [
                'id' => $item->id,
                'action' => $item->action,
                'reason' => $item->reason,
                'actor' => $item->actorId === null
                    ? __('sessions::fields.system_actor')
                    : ($actors[$item->actorId]->name ?? $item->actorId),
                'created_at' => $item->createdAt,
            ];
        }

        return [
            'participants' => $participants->map(fn (SessionParticipant $item): array => [
                'id' => (string) $item->getKey(),
                'student' => $this->studentLabel($organizationId, (string) $item->student_profile_id),
                'invitation_status' => $item->revoked_at === null && !$item->trashed()
                    ? __('sessions::fields.invitation_active')
                    : __('sessions::fields.invitation_revoked'),
                'invited_at' => $item->invited_at?->toIso8601String(),
                'revoked_at' => $item->revoked_at?->toIso8601String(),
                'revocation_reason' => $item->revocation_reason,
                'first_joined_at' => $item->first_joined_at?->toIso8601String(),
                'last_left_at' => $item->last_left_at?->toIso8601String(),
                'attended_minutes' => (int) $item->attended_minutes,
            ])->values()->all(),
            'history' => $history,
            'substitutions' => $substitutions->map(fn (object $item): array => [
                'id' => (string) $item->id,
                'original_teacher' => $this->teacherLabel($organizationId, (string) $item->original_teacher_id),
                'substitute_teacher' => $this->teacherLabel($organizationId, (string) $item->substitute_teacher_id),
                'reason' => $item->reason,
                'is_override' => (bool) $item->is_override,
                'override_reason' => $item->override_reason,
                'assigned_at' => (string) $item->assigned_at,
            ])->values()->all(),
            'apologies' => $apologies,
            'audit' => $auditRows,
        ];
    }

    /** @param list<string> $courseIds */
    private function loadCourses(string $organizationId, array $courseIds): void
    {
        $missing = array_values(array_diff($courseIds, array_keys($this->courses[$organizationId] ?? [])));
        if ($missing !== []) {
            $this->courses[$organizationId] = [
                ...($this->courses[$organizationId] ?? []),
                ...$this->academics->coursesByIds($organizationId, $missing),
            ];
        }
    }

    /** @param list<string> $groupIds */
    private function loadGroups(string $organizationId, array $groupIds): void
    {
        $missing = array_values(array_diff($groupIds, array_keys($this->groups[$organizationId] ?? [])));
        if ($missing !== []) {
            $this->groups[$organizationId] = [
                ...($this->groups[$organizationId] ?? []),
                ...$this->groupQueries->groupsByIds($organizationId, $missing),
            ];
        }
    }

    /** @param list<string> $teacherIds */
    private function loadTeachers(string $organizationId, array $teacherIds): void
    {
        $missing = array_values(array_diff($teacherIds, array_keys($this->teachers[$organizationId] ?? [])));
        if ($missing !== []) {
            $this->teachers[$organizationId] = [
                ...($this->teachers[$organizationId] ?? []),
                ...$this->staff->namesForProfiles($organizationId, $missing),
            ];
        }
    }

    /** @param list<string> $studentIds */
    private function loadStudents(string $organizationId, array $studentIds): void
    {
        $missing = array_values(array_diff($studentIds, array_keys($this->students[$organizationId] ?? [])));
        if ($missing === []) {
            return;
        }

        $profiles = $this->studentQueries->byIds($organizationId, $missing);
        $accounts = $this->accounts->findMany(
            $organizationId,
            array_values(array_map(static fn (StudentDirectoryData $profile): string => $profile->userId, $profiles)),
        );
        foreach ($profiles as $profile) {
            $this->students[$organizationId][$profile->id] = ($accounts[$profile->userId]->name ?? $profile->studentCode)
                .' · '.$profile->studentCode;
        }
    }

    private function catalogLabel(AcademicCatalogItemData $item): string
    {
        return $this->localized($item->name).' · '.$item->code;
    }

    /** @param array<string, string> $value */
    private function localized(array $value): string
    {
        return $value[app()->getLocale()] ?? $value['ar'] ?? $value['en'] ?? (string) (reset($value) ?: '');
    }
}
