<?php

declare(strict_types=1);

namespace Modules\Enrollments\Application\Queries;

use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Academics\Domain\ValueObjects\AcademicCatalogItemData;
use Modules\Enrollments\Domain\Models\Enrollment;
use Modules\Enrollments\Domain\Models\EnrollmentStatusHistory;
use Modules\Groups\Domain\Contracts\GroupAdministrationQueries;
use Modules\Identity\Domain\Contracts\UserAccountDirectory;
use Modules\Students\Domain\Contracts\StudentDirectoryQueries;

/** قراءة مركز القيد عبر DTOs وعقود الموديولات المالكة فقط. */
final readonly class EnrollmentOperationsQueryService
{
    public function __construct(
        private StudentDirectoryQueries $students,
        private UserAccountDirectory $accounts,
        private AcademicCatalogQueries $academics,
        private GroupAdministrationQueries $groups,
    ) {}

    public function studentLabel(string $organizationId, string $studentProfileId): string
    {
        $student = $this->students->find($organizationId, $studentProfileId);

        if ($student === null) {
            return __('enrollments::filament.common.not_available');
        }

        $account = $this->accounts->find($organizationId, $student->userId);

        return ($account === null ? $studentProfileId : $account->name).' · '.$student->studentCode;
    }

    public function programLabel(string $organizationId, string $programId): string
    {
        $program = $this->academics->programsByIds($organizationId, [$programId])[$programId] ?? null;

        return $program === null
            ? __('enrollments::filament.common.not_available')
            : $this->catalogLabel($program);
    }

    public function levelLabel(string $organizationId, ?string $levelId): string
    {
        if ($levelId === null || $levelId === '') {
            return __('enrollments::filament.common.not_available');
        }

        $level = $this->academics->levelsByIds($organizationId, [$levelId])[$levelId] ?? null;

        return $level === null
            ? __('enrollments::filament.common.not_available')
            : $this->catalogLabel($level);
    }

    /** @return array<string, string> */
    public function programOptions(string $organizationId): array
    {
        return collect($this->academics->programs($organizationId))
            ->mapWithKeys(fn (AcademicCatalogItemData $program): array => [
                $program->id => $this->catalogLabel($program),
            ])
            ->all();
    }

    /** @return array<string, string> */
    public function levelOptions(string $organizationId, ?string $programId): array
    {
        if ($programId === null || $programId === '') {
            return [];
        }

        return collect($this->academics->levels($organizationId, $programId))
            ->mapWithKeys(fn (AcademicCatalogItemData $level): array => [
                $level->id => $this->catalogLabel($level),
            ])
            ->all();
    }

    /** @return array<string, list<array<string, mixed>>> */
    public function hub(string $organizationId, string $enrollmentId): array
    {
        /** @var Enrollment|null $enrollment */
        $enrollment = Enrollment::query()
            ->forOrganization($organizationId)
            ->whereKey($enrollmentId)
            ->first();

        if ($enrollment === null) {
            return ['student' => [], 'academic' => [], 'history' => [], 'groups' => []];
        }

        $history = EnrollmentStatusHistory::query()
            ->where('enrollment_id', $enrollmentId)
            ->latest('changed_at')
            ->limit((int) config('enrollments.admin_hub.max_history'))
            ->get();
        $actorIds = $history->pluck('changed_by')->map(static fn (mixed $id): string => (string) $id)->all();
        $actors = $this->accounts->findMany($organizationId, $actorIds);
        $memberships = $this->groups->membershipsForStudent($organizationId, (string) $enrollment->student_profile_id);

        return [
            'student' => [[
                'name' => $this->studentLabel($organizationId, (string) $enrollment->student_profile_id),
                'student_profile_id' => (string) $enrollment->student_profile_id,
            ]],
            'academic' => [[
                'program' => $this->programLabel($organizationId, (string) $enrollment->program_id),
                'level' => $this->levelLabel($organizationId, $enrollment->current_level_id),
                'applied_at' => $enrollment->applied_at?->toIso8601String(),
                'activated_at' => $enrollment->activated_at?->toIso8601String(),
                'completed_at' => $enrollment->completed_at?->toIso8601String(),
            ]],
            'history' => $history->map(static fn (EnrollmentStatusHistory $item): array => [
                'id' => (string) $item->getKey(),
                'from_status' => $item->from_status === null
                    ? __('enrollments::filament.common.not_available')
                    : __('enrollments::status.'.$item->from_status),
                'to_status' => __('enrollments::status.'.$item->to_status),
                'reason' => (string) $item->reason,
                'actor' => $actors[(string) $item->changed_by]->name
                    ?? __('enrollments::filament.common.system'),
                'changed_at' => $item->changed_at?->toIso8601String(),
            ])->values()->all(),
            'groups' => array_map(fn ($membership): array => [
                'id' => $membership->membershipId,
                'group' => $this->localized($membership->groupName).' · '.$membership->groupCode,
                'group_status' => __('groups::status.group.'.$membership->groupStatus),
                'membership_status' => __('groups::status.membership.'.$membership->membershipStatus),
                'joined_at' => $membership->joinedAt,
                'left_at' => $membership->leftAt,
            ], $memberships),
        ];
    }

    private function catalogLabel(AcademicCatalogItemData $item): string
    {
        return $this->localized($item->name).' · '.$item->code;
    }

    /** @param array<string, string> $value */
    private function localized(array $value): string
    {
        return $value[app()->getLocale()] ?? $value['ar'] ?? $value['en'] ?? (string) reset($value);
    }
}
