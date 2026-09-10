<?php

declare(strict_types=1);

namespace Modules\Scheduling\Application\Services;

use Modules\Groups\Domain\Contracts\GroupAdministrationQueries;
use Modules\Scheduling\Domain\Models\Schedule;
use Modules\Students\Domain\Contracts\StudentDirectoryQueries;

/**
 * طلاب قالب الجدول الحاليون — عضوية المجموعة النشطة أو الطالب الفردي.
 *
 * يقرأ عبر العقود العامة فقط؛ لا يعرف جداول Groups أو Students.
 */
final readonly class ScheduleStudentAudience
{
    public function __construct(
        private GroupAdministrationQueries $groups,
        private StudentDirectoryQueries $students,
    ) {}

    /** @return list<array{student_profile_id: string, user_id: string|null, name: string}> */
    public function forSchedule(Schedule $schedule): array
    {
        $organizationId = (string) $schedule->organization_id;
        $profileIds = $this->profileIds($schedule);

        if ($profileIds === []) {
            return [];
        }

        $directory = $this->students->byIds($organizationId, $profileIds);
        $names = $this->students->namesForProfiles($organizationId, $profileIds);
        $audience = [];

        foreach ($profileIds as $profileId) {
            $userId = $directory[$profileId]->userId ?? null;
            $audience[] = [
                'student_profile_id' => $profileId,
                'user_id' => is_string($userId) && $userId !== '' ? $userId : null,
                'name' => (string) ($names[$profileId] ?? ''),
            ];
        }

        return $audience;
    }

    /** @return list<string> */
    private function profileIds(Schedule $schedule): array
    {
        if ($schedule->student_profile_id !== null) {
            return [(string) $schedule->student_profile_id];
        }

        $groupId = $schedule->group_id === null ? null : (string) $schedule->group_id;
        if ($groupId === null) {
            return [];
        }

        return collect($this->groups->membershipsForGroup((string) $schedule->organization_id, $groupId))
            ->filter(static fn ($member): bool => $member->status === 'active' && $member->leftAt === null)
            ->map(static fn ($member): string => $member->studentProfileId)
            ->unique()
            ->values()
            ->all();
    }
}
