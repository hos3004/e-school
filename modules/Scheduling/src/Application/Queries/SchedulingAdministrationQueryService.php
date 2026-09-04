<?php

declare(strict_types=1);

namespace Modules\Scheduling\Application\Queries;

use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Academics\Domain\ValueObjects\AcademicCatalogItemData;
use Modules\Audit\Domain\Contracts\AuditQueryService;
use Modules\Enrollments\Domain\Contracts\EnrollmentAdministrationQueries;
use Modules\Groups\Domain\Contracts\GroupAdministrationQueries;
use Modules\Groups\Domain\ValueObjects\SchedulingGroupData;
use Modules\Identity\Domain\Contracts\UserAccountDirectory;
use Modules\Scheduling\Domain\Models\PostponementRequest;
use Modules\Scheduling\Domain\Models\Schedule;
use Modules\Sessions\Domain\Contracts\SessionAdministrationQueries;
use Modules\Sessions\Domain\Contracts\SessionSchedulingQueries;
use Modules\Staff\Domain\Contracts\StaffQueries;
use Modules\Staff\Domain\Contracts\TeacherQualificationQueries;
use Modules\Students\Domain\Contracts\StudentDirectoryQueries;

/** تجميع DTOs للوحة الجدولة دون علاقات عابرة للموديولات. */
final class SchedulingAdministrationQueryService
{
    /** @var array<string, list<SchedulingGroupData>> */
    private array $groupsCache = [];

    /** @var array<string, array<string, string>> */
    private array $teacherNamesCache = [];

    /** @var array<string, array<string, mixed>> */
    private array $postponementCache = [];

    public function __construct(
        private readonly AcademicCatalogQueries $academics,
        private readonly GroupAdministrationQueries $groups,
        private readonly EnrollmentAdministrationQueries $enrollments,
        private readonly StaffQueries $staff,
        private readonly TeacherQualificationQueries $qualifications,
        private readonly StudentDirectoryQueries $students,
        private readonly UserAccountDirectory $accounts,
        private readonly SessionAdministrationQueries $sessions,
        private readonly SessionSchedulingQueries $sessionFacts,
        private readonly AuditQueryService $audit,
    ) {}

    /** @return array<string, string> */
    public function groupOptions(string $organizationId): array
    {
        return collect($this->groups($organizationId))->mapWithKeys(fn (SchedulingGroupData $group): array => [
            $group->id => $this->localized($group->name).' · '.$group->code,
        ])->all();
    }

    /** @return array<string, string> */
    public function courseOptions(
        string $organizationId,
        ?string $groupId = null,
        ?string $targetType = null,
    ): array {
        if ($groupId === null || $groupId === '') {
            $items = collect($this->academics->programs($organizationId))
                ->flatMap(fn (AcademicCatalogItemData $program): array => $this->academics->courses($organizationId, $program->id))
                ->unique(static fn (AcademicCatalogItemData $course): string => $course->id)
                ->values();
        } else {
            $group = $this->group($organizationId, $groupId);
            $courseIds = $group === null
                ? []
                : collect($group->teacherAssignments)
                    ->pluck('courseId')
                    ->filter()
                    ->map(static fn (mixed $id): string => (string) $id)
                    ->unique()
                    ->values()
                    ->all();
            $items = collect($this->academics->coursesByIds($organizationId, $courseIds))->values();
        }

        $requiredMode = match ($targetType) {
            'group' => 'group',
            'student' => 'individual',
            default => null,
        };

        return $items
            ->filter(static fn (AcademicCatalogItemData $course): bool => $requiredMode === null
                || $course->sessionMode === null
                || $course->sessionMode === 'both'
                || $course->sessionMode === $requiredMode)
            ->mapWithKeys(fn (AcademicCatalogItemData $course): array => [
                $course->id => $this->catalogLabel($course),
            ])->all();
    }

    /** @return array<string, string> */
    public function teacherOptions(
        string $organizationId,
        ?string $groupId,
        ?string $courseId,
    ): array {
        if ($courseId === null || $courseId === '') {
            return [];
        }

        if ($groupId !== null && $groupId !== '') {
            $group = $this->group($organizationId, $groupId);
            $ids = $group === null ? [] : collect($group->teacherAssignments)
                ->filter(static fn ($assignment): bool => $assignment->courseId === $courseId)
                ->pluck('staffProfileId')
                ->map(static fn (mixed $id): string => (string) $id)
                ->unique()
                ->values()
                ->all();
        } else {
            $active = $this->staff->activeTeacherIdsForOrganization($organizationId);
            $qualified = $this->qualifications->qualifiedTeacherIdsForCourse($courseId);
            $ids = array_values(array_intersect($active, $qualified));
        }

        return $this->teacherNames($organizationId, $ids);
    }

    /** @return array<string, string> */
    public function studentOptions(string $organizationId, ?string $courseId): array
    {
        if ($courseId === null || $courseId === '') {
            return [];
        }
        $course = $this->academics->coursesByIds($organizationId, [$courseId])[$courseId] ?? null;
        if ($course === null || $course->programId === null) {
            return [];
        }

        $enrollments = $this->enrollments->schedulableEnrollmentIdsByStudent($organizationId, $course->programId);
        $profiles = $this->students->byIds($organizationId, array_keys($enrollments));
        $accounts = $this->accounts->findMany(
            $organizationId,
            collect($profiles)->map(static fn ($profile): string => $profile->userId)->values()->all(),
        );
        $options = [];
        foreach ($profiles as $profile) {
            // الاسم وحده — كود الطالب يعيش في عمود مستقل، لا ملتصقًا بالاسم.
            $options[$profile->id] = $accounts[$profile->userId]->name ?? $profile->studentCode;
        }

        return $options;
    }

    public function groupLabel(string $organizationId, ?string $groupId): string
    {
        if ($groupId === null) {
            return __('scheduling::filament.common.not_available');
        }
        $group = $this->group($organizationId, $groupId);

        return $group === null ? $groupId : $this->localized($group->name).' · '.$group->code;
    }

    public function courseLabel(string $organizationId, string $courseId): string
    {
        $course = $this->academics->coursesByIds($organizationId, [$courseId])[$courseId] ?? null;

        return $course === null ? $courseId : $this->catalogLabel($course);
    }

    public function teacherLabel(string $organizationId, string $staffProfileId): string
    {
        return $this->teacherNames($organizationId, [$staffProfileId])[$staffProfileId] ?? $staffProfileId;
    }

    public function studentLabel(string $organizationId, ?string $studentProfileId): string
    {
        if ($studentProfileId === null) {
            return __('scheduling::filament.common.not_available');
        }
        $profile = $this->students->find($organizationId, $studentProfileId);
        if ($profile === null) {
            return $studentProfileId;
        }
        $account = $this->accounts->find($organizationId, $profile->userId);

        return $account === null ? $profile->studentCode : $account->name;
    }

    /** @return array<string, list<array<string, mixed>>> */
    public function scheduleHub(string $organizationId, Schedule $schedule): array
    {
        $sessionRows = array_map(fn ($session): array => [
            'id' => $session->id,
            'title' => $this->localized($session->title),
            'status' => __('sessions::status.'.$session->status),
            'scheduled_start' => $session->scheduledStart,
            'scheduled_end' => $session->scheduledEnd,
        ], $this->sessions->forSchedule(
            $organizationId,
            (string) $schedule->getKey(),
            (int) config('scheduling.admin_hub.max_sessions'),
        ));

        $audits = $this->audit->paginateForOrganization($organizationId, [
            'auditable_type' => 'schedules',
            'auditable_id' => (string) $schedule->getKey(),
        ], (int) config('scheduling.admin_hub.max_history'));

        $history = [];
        foreach ($audits->items() as $entry) {
            $history[] = [
                'id' => $entry->id,
                'action' => $entry->action,
                'reason' => $entry->reason,
                'actor_id' => $entry->actorId,
                'created_at' => $entry->createdAt,
            ];
        }

        return [
            'sessions' => $sessionRows,
            'history' => $history,
        ];
    }

    /** @return array<string, mixed> */
    public function postponementDetails(string $organizationId, PostponementRequest $request): array
    {
        $cacheKey = $organizationId.':'.(string) $request->getKey();
        if (isset($this->postponementCache[$cacheKey])) {
            return $this->postponementCache[$cacheKey];
        }

        $session = $this->sessionFacts->find($organizationId, (string) $request->session_id);

        return $this->postponementCache[$cacheKey] = [
            'session' => $session === null
                ? (string) $request->session_id
                : $this->localized($session->title).' · '.$session->scheduledStart->toIso8601String(),
            'student' => $this->studentLabel($organizationId, (string) $request->requested_for_student_id),
            'requested_by' => $this->accountLabel($organizationId, (string) $request->requested_by),
            'responded_by' => $request->responded_by === null
                ? __('scheduling::filament.common.not_available')
                : $this->accountLabel($organizationId, (string) $request->responded_by),
        ];
    }

    /** @return list<SchedulingGroupData> */
    private function groups(string $organizationId): array
    {
        return $this->groupsCache[$organizationId]
            ??= $this->groups->activeGroupsForScheduling($organizationId);
    }

    private function group(string $organizationId, string $groupId): ?SchedulingGroupData
    {
        return collect($this->groups($organizationId))
            ->first(static fn (SchedulingGroupData $group): bool => $group->id === $groupId);
    }

    /**
     * @param list<string> $ids
     * @return array<string, string>
     */
    private function teacherNames(string $organizationId, array $ids): array
    {
        $missing = array_values(array_diff($ids, array_keys($this->teacherNamesCache[$organizationId] ?? [])));
        if ($missing !== []) {
            $this->teacherNamesCache[$organizationId] = [
                ...($this->teacherNamesCache[$organizationId] ?? []),
                ...$this->staff->namesForProfiles($organizationId, $missing),
            ];
        }

        return array_intersect_key($this->teacherNamesCache[$organizationId] ?? [], array_flip($ids));
    }

    private function accountLabel(string $organizationId, string $userId): string
    {
        $account = $this->accounts->find($organizationId, $userId);

        return $account === null ? $userId : $account->name;
    }

    private function catalogLabel(AcademicCatalogItemData $item): string
    {
        return $this->localized($item->name).' · '.$item->code;
    }

    /** @param array<string, mixed> $value */
    private function localized(array $value): string
    {
        return (string) ($value[app()->getLocale()] ?? $value['ar'] ?? $value['en'] ?? reset($value) ?: '');
    }
}
