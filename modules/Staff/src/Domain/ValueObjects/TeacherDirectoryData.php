<?php

declare(strict_types=1);

namespace Modules\Staff\Domain\ValueObjects;

/**
 * سجل دليل المعلمين للعرض — بيانات مجمّعة دفعة واحدة من عقود القراءة
 * المعلنة (Identity / Groups / Sessions) دون أي استيراد عابر للموديولات.
 */
final readonly class TeacherDirectoryData
{
    public function __construct(
        public string $staffProfileId,
        public string $userId,
        public string $name,
        public ?string $avatarPath,
        public string $accountStatus,
        public string $employmentType,
        public ?string $terminatedAt,
        public int $qualifiedCoursesCount,
        public int $activeGroups,
        public int $upcomingSessions,
        public int $completedThisMonth,
        public int $cancelledThisMonth,
        public bool $hasApprovedAvailability,
    ) {}
}
