<?php

declare(strict_types=1);

namespace Modules\Staff\Infrastructure\Query;

use Carbon\CarbonImmutable;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Staff\Domain\Models\TeacherAvailability;

/**
 * قراءة فقط — تُرجع مصفوفات بدائية لا Eloquent models،
 * لاستخدامها من موديولات أخرى (مثل Scheduling عند فحص التعارض).
 */
final readonly class StaffQueryService
{
    /**
     * @return array{id: string, staff_code: string}|null
     */
    public function findActiveProfileForUser(string $userId): ?array
    {
        /** @var StaffProfile|null $profile */
        $profile = StaffProfile::query()
            ->forUser($userId)
            ->active()
            ->first();

        if ($profile === null) {
            return null;
        }

        return ['id' => $profile->id, 'staff_code' => $profile->staff_code];
    }

    /**
     * هل المعلم متاح في يوم أسبوعي معيّن ضمن سجل إتاحة ساري؟
     */
    public function isAvailableOnWeekday(string $staffProfileId, int $weekday, ?CarbonImmutable $on = null): bool
    {
        return TeacherAvailability::query()
            ->forProfile($staffProfileId)
            ->onWeekday($weekday)
            ->activeOn($on ?? CarbonImmutable::now('UTC'))
            ->exists();
    }

    /**
     * @return list<string>
     */
    public function activeTeacherIdsForOrganization(string $organizationId): array
    {
        return StaffProfile::query()
            ->forOrganization($organizationId)
            ->active()
            ->pluck('id')
            ->all();
    }
}
