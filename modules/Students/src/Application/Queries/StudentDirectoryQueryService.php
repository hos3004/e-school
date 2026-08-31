<?php

declare(strict_types=1);

namespace Modules\Students\Application\Queries;

use Modules\Identity\Domain\Contracts\UserQueryService;
use Modules\Students\Domain\Contracts\StudentDirectoryQueries;
use Modules\Students\Domain\Models\StudentProfile;
use Modules\Students\Domain\ValueObjects\StudentDirectoryData;

final readonly class StudentDirectoryQueryService implements StudentDirectoryQueries
{
    public function __construct(private UserQueryService $users) {}

    public function forUserIds(string $organizationId, array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter($userIds)));

        if ($userIds === []) {
            return [];
        }

        return StudentProfile::query()
            ->forOrganization($organizationId)
            ->whereIn('user_id', $userIds)
            ->orderBy('student_code')
            ->get()
            ->map(static fn (StudentProfile $profile): StudentDirectoryData => self::toDto($profile))
            ->values()
            ->all();
    }

    public function byIds(string $organizationId, array $studentProfileIds): array
    {
        $studentProfileIds = array_values(array_unique(array_filter($studentProfileIds)));

        if ($studentProfileIds === []) {
            return [];
        }

        return StudentProfile::query()
            ->forOrganization($organizationId)
            ->whereKey($studentProfileIds)
            ->get()
            ->mapWithKeys(static fn (StudentProfile $profile): array => [
                (string) $profile->getKey() => self::toDto($profile),
            ])
            ->all();
    }

    public function find(string $organizationId, string $studentProfileId): ?StudentDirectoryData
    {
        /** @var StudentProfile|null $profile */
        $profile = StudentProfile::query()
            ->forOrganization($organizationId)
            ->whereKey($studentProfileId)
            ->first();

        return $profile === null ? null : self::toDto($profile);
    }

    public function namesForProfiles(string $organizationId, array $studentProfileIds): array
    {
        $studentProfileIds = array_values(array_unique(array_filter(
            $studentProfileIds,
            static fn (mixed $id): bool => is_string($id) && $id !== '',
        )));

        if ($studentProfileIds === []) {
            return [];
        }

        /*
         * الاسم يعيش في `users` المملوكة لموديول Identity — القراءة تمر بعقده
         * المعلن، والكود الأكاديمي بديل حين يتعذّر الوصول للاسم.
         */
        $profiles = StudentProfile::query()
            ->forOrganization($organizationId)
            ->withTrashed()
            ->whereKey($studentProfileIds)
            ->get(['id', 'user_id', 'student_code']);

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
                ? (string) $profile->student_code
                : (string) $summary->name;
        }

        return $names;
    }

    private static function toDto(StudentProfile $profile): StudentDirectoryData
    {
        return new StudentDirectoryData(
            id: (string) $profile->getKey(),
            organizationId: (string) $profile->organization_id,
            userId: (string) $profile->user_id,
            studentCode: (string) $profile->student_code,
            joinedAt: $profile->joined_at?->toDateString(),
            archived: $profile->deleted_at !== null,
        );
    }
}
