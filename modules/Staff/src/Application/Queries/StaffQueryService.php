<?php

declare(strict_types=1);

namespace Modules\Staff\Application\Queries;

use Carbon\CarbonImmutable;
use Modules\Identity\Domain\Contracts\UserQueryService;
use Modules\Staff\Domain\Contracts\StaffQueries;
use Modules\Staff\Domain\Enums\TeacherAvailabilityApprovalStatus;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Staff\Domain\Models\TeacherAvailability;

final readonly class StaffQueryService implements StaffQueries
{
    public function __construct(private UserQueryService $users) {}

    public function findActiveProfileForUser(string $userId): ?array
    {
        /** @var StaffProfile|null $profile */
        $profile = StaffProfile::query()->active()->forUser($userId)->first();

        return $profile === null ? null : [
            'id' => (string) $profile->getKey(),
            'staff_code' => (string) $profile->staff_code,
        ];
    }

    public function isAvailableOnWeekday(string $staffProfileId, int $weekday, ?CarbonImmutable $on = null): bool
    {
        return TeacherAvailability::query()
            ->forProfile($staffProfileId)
            ->onWeekday($weekday)
            ->activeOn($on ?? CarbonImmutable::now('UTC'))
            ->where('approval_status', TeacherAvailabilityApprovalStatus::Approved->value)
            ->exists();
    }

    public function activeTeacherIdsForOrganization(string $organizationId): array
    {
        return StaffProfile::query()
            ->forOrganization($organizationId)
            ->active()
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->values()
            ->all();
    }

    public function userIdForProfile(string $organizationId, string $staffProfileId): ?string
    {
        $userId = StaffProfile::query()
            ->forOrganization($organizationId)
            ->active()
            ->whereKey($staffProfileId)
            ->value('user_id');

        return is_string($userId) && $userId !== '' ? $userId : null;
    }

    /**
     * @param list<string> $staffProfileIds
     * @return array<string, string>
     */
    public function namesForProfiles(string $organizationId, array $staffProfileIds): array
    {
        $staffProfileIds = array_values(array_unique(array_filter(
            $staffProfileIds,
            static fn (mixed $id): bool => is_string($id) && $id !== '',
        )));

        if ($staffProfileIds === []) {
            return [];
        }

        /*
         * الاسم يعيش في `users` المملوك لموديول Identity **المختوم**، فلا
         * علاقة Eloquent إليه من هنا. القراءة تمر بعقد Identity المعلن،
         * والملف يحتفظ بكوده الوظيفي كبديل حين يتعذّر الوصول للاسم.
         */
        $profiles = StaffProfile::query()
            ->forOrganization($organizationId)
            ->whereKey($staffProfileIds)
            ->get(['id', 'user_id', 'staff_code']);

        $summaries = $this->users->summariesByIds(
            $profiles
                ->pluck('user_id')
                ->filter()
                ->map(static fn (mixed $id): string => (string) $id)
                ->values()
                ->all(),
        );

        $names = [];

        foreach ($profiles as $profile) {
            $summary = $summaries[(string) $profile->user_id] ?? null;

            $names[(string) $profile->getKey()] = $summary === null
                ? (string) $profile->staff_code
                : (string) $summary->name;
        }

        return $names;
    }

    /**
     * @return list<string>
     */
    public function profileIdsForOrganization(string $organizationId): array
    {
        return StaffProfile::query()
            ->forOrganization($organizationId)
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->values()
            ->all();
    }
}
