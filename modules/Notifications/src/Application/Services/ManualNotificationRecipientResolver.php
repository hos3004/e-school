<?php

declare(strict_types=1);

namespace Modules\Notifications\Application\Services;

use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Groups\Domain\Contracts\GroupAdministrationQueries;
use Modules\Groups\Domain\ValueObjects\SchedulingGroupData;
use Modules\Identity\Domain\Contracts\DTOs\UserAccountData;
use Modules\Identity\Domain\Contracts\UserAccountDirectory;
use Modules\Notifications\Domain\Enums\ManualAudience;
use Modules\Notifications\Domain\Enums\ManualRecipientType;
use Modules\Notifications\Domain\ValueObjects\ManualRecipientResolution;
use Modules\Scheduling\Domain\Contracts\ScheduleDirectoryQueries;
use Modules\Scheduling\Domain\ValueObjects\ScheduleTargetData;
use Modules\Staff\Domain\Contracts\StaffQueries;
use Modules\Students\Domain\Contracts\StudentDirectoryQueries;
use Modules\Students\Domain\ValueObjects\StudentDirectoryData;
use Shared\Support\BusinessRuleViolation;

/**
 * يحوّل اختيار الواجهة إلى user IDs موثوقة ومحصورة بالمؤسسة.
 * لا يعرف Notifications أي نموذج Eloquent من الموديولات المالكة.
 *
 * الأهداف الجماعية ثلاثة: المجموعة والكورس والجدول. الكورس والجدول أُضيفا
 * لأن المدرسة تعمل بجداول فردية، فزرّ مراسلة يقتصر على المجموعات يصل ميتًا.
 * وفي كل الحالات الناتج قائمة مستلمين أفراد؛ الإرسال رسائل منفصلة لا مجموعة
 * ولا قائمة بث، فلا يرى مستلم رقم مستلم آخر.
 */
final readonly class ManualNotificationRecipientResolver
{
    public function __construct(
        private UserAccountDirectory $accounts,
        private StudentDirectoryQueries $students,
        private StaffQueries $staff,
        private GroupAdministrationQueries $groups,
        private ScheduleDirectoryQueries $schedules,
        private AcademicCatalogQueries $catalog,
    ) {}

    /** @return array<string, string> */
    public function search(
        string $organizationId,
        ManualRecipientType $type,
        string $term,
        int $limit = 25,
    ): array {
        $limit = max(1, min($limit, 50));

        return match ($type) {
            ManualRecipientType::Student => $this->studentOptions($organizationId, $term, $limit),
            ManualRecipientType::Teacher => $this->teacherOptions($organizationId, $term, $limit),
            ManualRecipientType::Group => $this->groupOptions($organizationId, $term, $limit),
            ManualRecipientType::Course => $this->courseOptions($organizationId, $term, $limit),
            ManualRecipientType::Schedule => $this->scheduleOptions($organizationId, $term, $limit),
        };
    }

    public function label(
        string $organizationId,
        ManualRecipientType $type,
        string $targetId,
    ): ?string {
        try {
            return $this->resolve($organizationId, $type, $targetId)->label;
        } catch (BusinessRuleViolation) {
            return null;
        }
    }

    public function resolve(
        string $organizationId,
        ManualRecipientType $type,
        string $targetId,
        ManualAudience $audience = ManualAudience::All,
    ): ManualRecipientResolution {
        if ($organizationId === '' || $targetId === '') {
            $this->recipientNotFound();
        }

        return match ($type) {
            ManualRecipientType::Student => $this->resolveStudent($organizationId, $targetId),
            ManualRecipientType::Teacher => $this->resolveTeacher($organizationId, $targetId),
            ManualRecipientType::Group => $this->resolveGroup($organizationId, $targetId, $audience),
            ManualRecipientType::Course => $this->resolveCourse($organizationId, $targetId, $audience),
            ManualRecipientType::Schedule => $this->resolveSchedule($organizationId, $targetId, $audience),
        };
    }

    /** @return array<string, string> */
    private function studentOptions(string $organizationId, string $term, int $limit): array
    {
        $accounts = array_values(array_filter(
            $this->accounts->search($organizationId, $term, $limit),
            static fn (UserAccountData $account): bool => $account->status === 'active',
        ));
        $profiles = $this->students->forUserIds(
            $organizationId,
            array_map(static fn (UserAccountData $account): string => $account->id, $accounts),
        );
        $profilesByUser = [];

        foreach ($profiles as $profile) {
            if (!$profile->archived) {
                $profilesByUser[$profile->userId] = $profile;
            }
        }

        $options = [];

        foreach ($accounts as $account) {
            $profile = $profilesByUser[$account->id] ?? null;

            if ($profile !== null) {
                $options[$account->id] = $account->name.' · '.$profile->studentCode;
            }
        }

        return $options;
    }

    /** @return array<string, string> */
    private function teacherOptions(string $organizationId, string $term, int $limit): array
    {
        $needle = mb_strtolower(trim($term));
        $options = [];

        foreach ($this->staff->activeTeacherSummariesForOrganization($organizationId) as $teacher) {
            $haystack = mb_strtolower($teacher['name'].' '.$teacher['staff_code']);

            if ($needle !== '' && !str_contains($haystack, $needle)) {
                continue;
            }

            $userId = $this->staff->userIdForProfile($organizationId, $teacher['staff_profile_id']);

            if ($userId !== null) {
                $options[$userId] = $teacher['name'].' · '.$teacher['staff_code'];
            }

            if (count($options) >= $limit) {
                break;
            }
        }

        return $options;
    }

    /** @return array<string, string> */
    private function groupOptions(string $organizationId, string $term, int $limit): array
    {
        $needle = mb_strtolower(trim($term));
        $options = [];

        foreach ($this->groups->activeGroupsForScheduling($organizationId) as $group) {
            $label = $this->groupLabel($group);

            if ($needle !== '' && !str_contains(mb_strtolower($label), $needle)) {
                continue;
            }

            $options[$group->id] = $label;

            if (count($options) >= $limit) {
                break;
            }
        }

        return $options;
    }

    /** @return array<string, string> */
    private function courseOptions(string $organizationId, string $term, int $limit): array
    {
        $courseIds = $this->schedules->activeCourseIds($organizationId);

        if ($courseIds === []) {
            return [];
        }

        $needle = mb_strtolower(trim($term));
        $options = [];

        foreach ($this->catalog->coursesByIds($organizationId, $courseIds) as $courseId => $course) {
            $label = $this->localizedName($course->name, $course->code).' · '.$course->code;

            if ($needle !== '' && !str_contains(mb_strtolower($label), $needle)) {
                continue;
            }

            $options[(string) $courseId] = $label;

            if (count($options) >= $limit) {
                break;
            }
        }

        return $options;
    }

    /** @return array<string, string> */
    private function scheduleOptions(string $organizationId, string $term, int $limit): array
    {
        $needle = mb_strtolower(trim($term));
        $schedules = $this->schedules->active($organizationId);
        $options = [];

        foreach ($schedules as $schedule) {
            $label = $this->scheduleLabel($organizationId, $schedule);

            if ($needle !== '' && !str_contains(mb_strtolower($label), $needle)) {
                continue;
            }

            $options[$schedule->id] = $label;

            if (count($options) >= $limit) {
                break;
            }
        }

        return $options;
    }

    private function resolveStudent(string $organizationId, string $userId): ManualRecipientResolution
    {
        $account = $this->accounts->find($organizationId, $userId);
        $profile = $this->students->forUserIds($organizationId, [$userId])[0] ?? null;

        if ($account === null || $account->status !== 'active' || $profile === null || $profile->archived) {
            $this->recipientNotFound();
        }

        return new ManualRecipientResolution(
            type: ManualRecipientType::Student,
            targetId: $userId,
            label: $account->name.' · '.$profile->studentCode,
            userIds: [$userId],
        );
    }

    private function resolveTeacher(string $organizationId, string $userId): ManualRecipientResolution
    {
        $account = $this->accounts->find($organizationId, $userId);
        $profile = $this->staff->findActiveProfileForUser($userId);

        if ($account === null
            || $account->status !== 'active'
            || $profile === null
            || !$this->staff->isActiveTeacherForOrganization($organizationId, $profile['id'])) {
            $this->recipientNotFound();
        }

        return new ManualRecipientResolution(
            type: ManualRecipientType::Teacher,
            targetId: $userId,
            label: $account->name.' · '.$profile['staff_code'],
            userIds: [$userId],
        );
    }

    private function resolveGroup(
        string $organizationId,
        string $groupId,
        ManualAudience $audience,
    ): ManualRecipientResolution {
        $group = collect($this->groups->activeGroupsForScheduling($organizationId))
            ->first(static fn (SchedulingGroupData $candidate): bool => $candidate->id === $groupId);

        if (!$group instanceof SchedulingGroupData) {
            $this->recipientNotFound();
        }

        $studentProfileIds = $audience->includesStudents()
            ? $this->groupStudentProfileIds($organizationId, $groupId)
            : [];
        $staffProfileIds = $audience->includesTeacher()
            ? $this->groupStaffProfileIds($organizationId, $groupId)
            : [];

        return $this->audienceResolution(
            organizationId: $organizationId,
            type: ManualRecipientType::Group,
            targetId: $groupId,
            label: $this->groupLabel($group),
            studentProfileIds: $studentProfileIds,
            staffProfileIds: $staffProfileIds,
        );
    }

    private function resolveCourse(
        string $organizationId,
        string $courseId,
        ManualAudience $audience,
    ): ManualRecipientResolution {
        $schedules = $this->schedules->activeForCourse($organizationId, $courseId);

        if ($schedules === []) {
            $this->recipientNotFound();
        }

        $course = $this->catalog->coursesByIds($organizationId, [$courseId])[$courseId] ?? null;
        [$studentProfileIds, $staffProfileIds] = $this->profileIdsForSchedules(
            $organizationId,
            $schedules,
            $audience,
        );

        return $this->audienceResolution(
            organizationId: $organizationId,
            type: ManualRecipientType::Course,
            targetId: $courseId,
            label: $course === null
                ? $courseId
                : $this->localizedName($course->name, $course->code).' · '.$course->code,
            studentProfileIds: $studentProfileIds,
            staffProfileIds: $staffProfileIds,
        );
    }

    private function resolveSchedule(
        string $organizationId,
        string $scheduleId,
        ManualAudience $audience,
    ): ManualRecipientResolution {
        $schedule = $this->schedules->find($organizationId, $scheduleId);

        if ($schedule === null || !$schedule->isActive) {
            $this->recipientNotFound();
        }

        [$studentProfileIds, $staffProfileIds] = $this->profileIdsForSchedules(
            $organizationId,
            [$schedule],
            $audience,
        );

        return $this->audienceResolution(
            organizationId: $organizationId,
            type: ManualRecipientType::Schedule,
            targetId: $scheduleId,
            label: $this->scheduleLabel($organizationId, $schedule),
            studentProfileIds: $studentProfileIds,
            staffProfileIds: $staffProfileIds,
        );
    }

    /**
     * @param list<ScheduleTargetData> $schedules
     * @return array{0: list<string>, 1: list<string>}
     */
    private function profileIdsForSchedules(
        string $organizationId,
        array $schedules,
        ManualAudience $audience,
    ): array {
        $studentProfileIds = [];
        $staffProfileIds = [];

        foreach ($schedules as $schedule) {
            if ($audience->includesTeacher()) {
                $staffProfileIds[] = $schedule->staffProfileId;
            }

            if (!$audience->includesStudents()) {
                continue;
            }

            if ($schedule->studentProfileId !== null) {
                $studentProfileIds[] = $schedule->studentProfileId;

                continue;
            }

            if ($schedule->groupId !== null) {
                $studentProfileIds = [
                    ...$studentProfileIds,
                    ...$this->groupStudentProfileIds($organizationId, $schedule->groupId),
                ];
            }
        }

        return [
            array_values(array_unique($studentProfileIds)),
            array_values(array_unique($staffProfileIds)),
        ];
    }

    /**
     * @param list<string> $studentProfileIds
     * @param list<string> $staffProfileIds
     */
    private function audienceResolution(
        string $organizationId,
        ManualRecipientType $type,
        string $targetId,
        string $label,
        array $studentProfileIds,
        array $staffProfileIds,
    ): ManualRecipientResolution {
        $userIds = [
            ...$this->studentUserIds($organizationId, $studentProfileIds),
            ...$this->staffUserIds($organizationId, $staffProfileIds),
        ];
        $userIds = $this->activeUserIds($organizationId, array_values(array_unique($userIds)));

        if ($userIds === []) {
            throw BusinessRuleViolation::make(
                'notifications.manual_empty_audience',
                'notifications::errors.manual_empty_audience',
            );
        }

        return new ManualRecipientResolution(
            type: $type,
            targetId: $targetId,
            label: $label,
            userIds: $userIds,
        );
    }

    /** @return list<string> */
    private function groupStudentProfileIds(string $organizationId, string $groupId): array
    {
        return collect($this->groups->membershipsForGroup($organizationId, $groupId))
            ->filter(static fn (mixed $membership): bool => $membership->status === 'active'
                && $membership->leftAt === null)
            ->pluck('studentProfileId')
            ->filter(static fn (mixed $id): bool => is_string($id) && $id !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * معلمو المجموعة = معلمو جداولها المعتمدة. إسناد المجموعة وحده قد يتأخر
     * عن الواقع، والجدول هو ما تُولَّد منه الحصص فعلًا.
     *
     * @return list<string>
     */
    private function groupStaffProfileIds(string $organizationId, string $groupId): array
    {
        $ids = [];

        foreach ($this->schedules->activeForGroup($organizationId, $groupId) as $schedule) {
            $ids[] = $schedule->staffProfileId;
        }

        foreach ($this->groups->assignmentsForGroup($organizationId, $groupId) as $assignment) {
            // الإسناد المنتهي لا يُراسَل: المعلم لم يعد طرفًا في هذه المجموعة.
            if ($assignment->assignedTo !== null || $assignment->staffProfileId === '') {
                continue;
            }

            $ids[] = $assignment->staffProfileId;
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param list<string> $studentProfileIds
     * @return list<string>
     */
    private function studentUserIds(string $organizationId, array $studentProfileIds): array
    {
        if ($studentProfileIds === []) {
            return [];
        }

        return array_values(array_unique(array_map(
            static fn (StudentDirectoryData $profile): string => $profile->userId,
            array_filter(
                array_values($this->students->byIds($organizationId, $studentProfileIds)),
                static fn (StudentDirectoryData $profile): bool => !$profile->archived
                    && $profile->userId !== '',
            ),
        )));
    }

    /**
     * @param list<string> $staffProfileIds
     * @return list<string>
     */
    private function staffUserIds(string $organizationId, array $staffProfileIds): array
    {
        $userIds = [];

        foreach ($staffProfileIds as $staffProfileId) {
            $userId = $this->staff->userIdForProfile($organizationId, $staffProfileId);

            if (is_string($userId) && $userId !== '') {
                $userIds[] = $userId;
            }
        }

        return array_values(array_unique($userIds));
    }

    /**
     * @param list<string> $userIds
     * @return list<string>
     */
    private function activeUserIds(string $organizationId, array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $accounts = $this->accounts->findMany($organizationId, $userIds);

        return array_values(array_filter(
            $userIds,
            static fn (string $userId): bool => isset($accounts[$userId])
                && $accounts[$userId]->status === 'active',
        ));
    }

    private function scheduleLabel(string $organizationId, ScheduleTargetData $schedule): string
    {
        $course = $this->localizedName($schedule->courseName, $schedule->courseId);

        if ($schedule->studentProfileId !== null) {
            $names = $this->students->namesForProfiles($organizationId, [$schedule->studentProfileId]);

            return $course.' · '.($names[$schedule->studentProfileId] ?? $schedule->studentProfileId);
        }

        if ($schedule->groupId !== null) {
            $group = $this->groups->groupsByIds($organizationId, [$schedule->groupId])[$schedule->groupId] ?? null;

            if ($group instanceof SchedulingGroupData) {
                return $course.' · '.$this->groupLabel($group);
            }
        }

        return $course;
    }

    private function groupLabel(SchedulingGroupData $group): string
    {
        return $this->localizedName($group->name, $group->code).' · '.$group->code;
    }

    /**
     * @param array<string, string> $name
     */
    private function localizedName(array $name, string $fallback): string
    {
        $locale = app()->getLocale();
        $values = array_values($name);
        $resolved = $name[$locale]
            ?? $name[(string) config('app.fallback_locale')]
            ?? ($values[0] ?? null);

        return is_string($resolved) && trim($resolved) !== '' ? $resolved : $fallback;
    }

    private function recipientNotFound(): never
    {
        throw BusinessRuleViolation::make(
            'notifications.manual_recipient_not_found',
            'notifications::errors.manual_recipient_not_found',
        );
    }
}
