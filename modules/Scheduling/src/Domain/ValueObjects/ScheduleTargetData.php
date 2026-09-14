<?php

declare(strict_types=1);

namespace Modules\Scheduling\Domain\ValueObjects;

/**
 * جدول معتمد بصفته هدف مراسلة — معرّفات ملفات فقط، لا نماذج ولا مستخدمين.
 *
 * الجدول إما جماعي (group_id) أو فردي (student_profile_id)، وهذا القيد مفروض
 * في القاعدة نفسها. من يقرأ هذا الـDTO يحوّل المعرّفات إلى مستخدمين عبر
 * عقود الموديولات المالكة، فلا يعرف Scheduling جداول الطلاب ولا المستخدمين.
 */
final readonly class ScheduleTargetData
{
    /** @param array<string, string> $courseName */
    public function __construct(
        public string $id,
        public string $organizationId,
        public string $courseId,
        public array $courseName,
        public ?string $groupId,
        public ?string $studentProfileId,
        public string $staffProfileId,
        public string $sessionType,
        public bool $isActive,
    ) {}
}
