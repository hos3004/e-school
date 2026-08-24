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

    public function userIdForProfile(string $organizationId, string $staffProfileId): ?string;

    /**
     * أسماء المعلمين مقابل معرّفات ملفاتهم، لعرضها في جداول موديولات أخرى.
     *
     * الاستهلاك دفعة واحدة مقصود: عرض الاسم لكل صف على حدة يولّد N+1 في
     * جدول إداري، والقائمة هنا تُبنى باستعلام واحد لصفحة كاملة.
     *
     * @param list<string> $staffProfileIds
     * @return array<string, string> معرّف الملف => الاسم
     */
    public function namesForProfiles(string $organizationId, array $staffProfileIds): array;

    /**
     * كل معرّفات ملفات الموظفين في المؤسسة — بمن فيهم من انتهت خدمته.
     *
     * تُستعمل لعزل جداول تابعة لا تحمل `organization_id` بنفسها. اقتصارها
     * على النشطين كان سيُخفي سجلات معلم سابق بدل أن يعزلها.
     *
     * @return list<string>
     */
    public function profileIdsForOrganization(string $organizationId): array;
}
