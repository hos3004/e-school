<?php

declare(strict_types=1);

namespace Modules\Staff\Domain\Contracts;

use Carbon\CarbonImmutable;

/**
 * قراءة عبر الحدود — تُرجع مصفوفات بدائية فقط.
 */
interface StaffQueries
{
    /**
     * @return array{id: string, staff_code: string}|null
     */
    public function findActiveProfileForUser(string $userId): ?array;

    public function isAvailableOnWeekday(string $staffProfileId, int $weekday, ?CarbonImmutable $on = null): bool;

    /**
     * @return list<string>
     */
    public function activeTeacherIdsForOrganization(string $organizationId): array;
}
