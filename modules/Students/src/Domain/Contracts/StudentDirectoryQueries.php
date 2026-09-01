<?php

declare(strict_types=1);

namespace Modules\Students\Domain\Contracts;

use Modules\Students\Domain\ValueObjects\StudentDirectoryData;

/** دليل طلاب للقراءة عبر حدود الموديول دون تسريب نموذج Eloquent. */
interface StudentDirectoryQueries
{
    /**
     * @param list<string> $userIds
     * @return list<StudentDirectoryData>
     */
    public function forUserIds(string $organizationId, array $userIds): array;

    /**
     * @param list<string> $studentProfileIds
     * @return array<string, StudentDirectoryData>
     */
    public function byIds(string $organizationId, array $studentProfileIds): array;

    public function find(string $organizationId, string $studentProfileId): ?StudentDirectoryData;

    /**
     * خريطة معرّف الملف ← اسم الطالب القابل للعرض (بديل عن ULID خام).
     *
     * @param list<string> $studentProfileIds
     * @return array<string, string>
     */
    public function namesForProfiles(string $organizationId, array $studentProfileIds): array;

    /**
     * Search active student profiles in one organization for safe selectors.
     *
     * @return array<string, string> student_profile_id => display name
     */
    public function searchNames(string $organizationId, string $search, int $limit = 50): array;
}
