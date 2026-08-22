<?php

declare(strict_types=1);

namespace Modules\Reporting\Domain\Contracts;

use Modules\Reporting\Domain\ValueObjects\StudentDashboardRow;
use Modules\Reporting\Domain\ValueObjects\TeacherDashboardRow;

/**
 * عقد القراءة من لوحات Reporting — الطريق الوحيد للاستهلاك الخارجي.
 *
 * يُرجع DTOs فقط، لا Eloquent models أبدًا.
 */
interface DashboardQuery
{
    public function studentByEnrollment(string $organizationId, string $enrollmentId): ?StudentDashboardRow;

    /**
     * @return list<StudentDashboardRow>
     */
    public function studentsAtRisk(string $organizationId, int $maxRateBp, int $limit = 50): array;

    public function teacherByStaff(string $organizationId, string $staffProfileId): ?TeacherDashboardRow;
}
