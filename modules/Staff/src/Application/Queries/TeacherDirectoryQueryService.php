<?php

declare(strict_types=1);

namespace Modules\Staff\Application\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Modules\Groups\Domain\Contracts\GroupAdministrationQueries;
use Modules\Identity\Domain\Contracts\UserQueryService;
use Modules\Sessions\Domain\Contracts\SessionAdministrationQueries;
use Modules\Staff\Domain\Contracts\TeacherDirectoryQueries;
use Modules\Staff\Domain\Enums\TeacherAvailabilityApprovalStatus;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Staff\Domain\Models\TeacherAvailability;
use Modules\Staff\Domain\Models\TeacherCourse;
use Modules\Staff\Domain\ValueObjects\TeacherDirectoryData;

/**
 * تنفيذ دليل المعلمين: عدد محدود من الاستعلامات المجمّعة لصفحة كاملة،
 * بغضّ النظر عن عدد المعلمين — لا استعلام مستقل لكل معلم ولا لكل عمود.
 */
final readonly class TeacherDirectoryQueryService implements TeacherDirectoryQueries
{
    public function __construct(
        private UserQueryService $users,
        private GroupAdministrationQueries $groups,
        private SessionAdministrationQueries $sessions,
    ) {}

    public function directoryFor(string $organizationId, array $staffProfileIds): array
    {
        $staffProfileIds = array_values(array_unique(array_filter(
            $staffProfileIds,
            static fn (mixed $id): bool => is_string($id) && $id !== '',
        )));

        if ($staffProfileIds === []) {
            return [];
        }

        /** @var Collection<int, StaffProfile> $profiles */
        $profiles = StaffProfile::query()
            ->forOrganization($organizationId)
            ->whereKey($staffProfileIds)
            ->get(['id', 'user_id', 'staff_code', 'employment_type', 'terminated_at']);

        $userIds = $profiles->pluck('user_id')->filter()->map(
            static fn (mixed $id): string => (string) $id,
        )->values()->all();

        $users = $this->users->summariesByIds($userIds);
        $activeGroupCounts = $this->groups->activeAssignmentCountsForTeachers($organizationId, $staffProfileIds);
        $monthStart = CarbonImmutable::now('UTC')->startOfMonth();
        $sessionCounts = $this->sessions->countsForTeachers($organizationId, $staffProfileIds, $monthStart);
        $available = array_fill_keys($this->withActiveAvailability($staffProfileIds), true);

        // تأهيلات الكورسات — استعلام مجمّع واحد على جدول الموديول نفسه.
        $qualificationCounts = TeacherCourse::query()
            ->whereIn('staff_profile_id', $staffProfileIds)
            ->whereNull('revoked_at')
            ->groupBy('staff_profile_id')
            ->selectRaw('staff_profile_id, count(*) as courses_count')
            ->pluck('courses_count', 'staff_profile_id')
            ->map(static fn (mixed $count): int => (int) $count)
            ->all();

        $directory = [];

        foreach ($profiles as $profile) {
            $profileId = (string) $profile->getKey();
            $userId = (string) $profile->user_id;
            $user = $users[$userId] ?? null;

            // التعريف القانوني للمعلم في الدليل: حساب فعّال (نشط) —
            // الموقوف/المجمّد يُستبعد كما في بقية خدمات القراءة.
            if ($user === null || $user->status !== 'active') {
                continue;
            }

            $counts = $sessionCounts[$profileId] ?? ['upcoming' => 0, 'completed' => 0, 'cancelled' => 0];

            $directory[$profileId] = new TeacherDirectoryData(
                staffProfileId: $profileId,
                userId: $userId,
                name: $user->name,
                avatarPath: $user->avatarPath,
                accountStatus: $user->status,
                employmentType: (string) $profile->employment_type?->value,
                terminatedAt: $profile->terminated_at?->toDateString(),
                qualifiedCoursesCount: $qualificationCounts[$profileId] ?? 0,
                activeGroups: $activeGroupCounts[$profileId] ?? 0,
                upcomingSessions: $counts['upcoming'],
                completedThisMonth: $counts['completed'],
                cancelledThisMonth: $counts['cancelled'],
                hasApprovedAvailability: isset($available[$profileId]),
            );
        }

        return $directory;
    }

    public function withActiveAvailability(array $staffProfileIds): array
    {
        $staffProfileIds = array_values(array_unique(array_filter(
            $staffProfileIds,
            static fn (mixed $id): bool => is_string($id) && $id !== '',
        )));

        if ($staffProfileIds === []) {
            return [];
        }

        return TeacherAvailability::query()
            ->whereIn('staff_profile_id', $staffProfileIds)
            ->where('approval_status', TeacherAvailabilityApprovalStatus::Approved->value)
            ->whereDate('effective_from', '<=', CarbonImmutable::today())
            ->where(static function ($query): void {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', CarbonImmutable::today());
            })
            ->distinct()
            ->pluck('staff_profile_id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->values()
            ->all();
    }
}
