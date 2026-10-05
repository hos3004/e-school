<?php

declare(strict_types=1);

namespace Modules\Scheduling\Domain\Contracts;

use Modules\Scheduling\Domain\ValueObjects\ScheduleTargetData;

/**
 * دليل الجداول المعتمدة للقراءة عبر حدود الموديول.
 *
 * سبب وجوده: المدرسة تعمل اليوم بجداول فردية لا بمجموعات، فوحدة «الفصل»
 * التي تُراسَل هي الجدول والكورس لا المجموعة. من يراسل يحتاج معرفة مَن على
 * الجدول، ولا يجوز أن يقرأ جدول schedules بنفسه.
 */
interface ScheduleDirectoryQueries
{
    public function find(string $organizationId, string $scheduleId): ?ScheduleTargetData;

    /** @return list<ScheduleTargetData> */
    public function activeForCourse(string $organizationId, string $courseId): array;

    /** @return list<ScheduleTargetData> */
    public function activeForGroup(string $organizationId, string $groupId): array;

    /**
     * معرّفات الكورسات التي عليها جداول معتمدة فعّالة.
     *
     * @return list<string>
     */
    public function activeCourseIds(string $organizationId): array;

    /**
     * الحد سقف مسح لا حد عرض؛ يُقرأ من الإعداد حين لا يُمرَّر.
     *
     * @return list<ScheduleTargetData>
     */
    public function active(string $organizationId, ?int $limit = null): array;
}
