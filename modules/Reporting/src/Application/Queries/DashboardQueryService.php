<?php

declare(strict_types=1);

namespace Modules\Reporting\Application\Queries;

use Modules\Reporting\Domain\Contracts\DashboardQuery;
use Modules\Reporting\Domain\Models\StudentDashboard;
use Modules\Reporting\Domain\Models\TeacherDashboard;
use Modules\Reporting\Domain\ValueObjects\StudentDashboardRow;
use Modules\Reporting\Domain\ValueObjects\TeacherDashboardRow;

/**
 * خدمة استعلام اللوحات — قراءة فقط، تُرجع DTOs.
 */
final readonly class DashboardQueryService implements DashboardQuery
{
    public function studentByEnrollment(string $organizationId, string $enrollmentId): ?StudentDashboardRow
    {
        /** @var StudentDashboard|null $dashboard */
        $dashboard = StudentDashboard::query()
            ->forOrganization($organizationId)
            ->where('enrollment_id', $enrollmentId)
            ->first();

        return $dashboard === null ? null : $this->toStudentRow($dashboard);
    }

    public function studentsAtRisk(string $organizationId, int $maxRateBp, int $limit = 50): array
    {
        return StudentDashboard::query()
            ->forOrganization($organizationId)
            ->atRisk($maxRateBp)
            ->orderBy('attendance_rate_bp')
            ->limit($limit)
            ->get()
            ->map(fn (StudentDashboard $dashboard): StudentDashboardRow => $this->toStudentRow($dashboard))
            ->all();
    }

    public function teacherByStaff(string $organizationId, string $staffProfileId): ?TeacherDashboardRow
    {
        /** @var TeacherDashboard|null $dashboard */
        $dashboard = TeacherDashboard::query()
            ->forOrganization($organizationId)
            ->where('staff_profile_id', $staffProfileId)
            ->first();

        return $dashboard === null ? null : new TeacherDashboardRow(
            staffProfileId: (string) $dashboard->staff_profile_id,
            sessionsCompleted: (int) $dashboard->sessions_completed,
            cancellationsBySelf: (int) $dashboard->cancellations_by_self,
            postponements: (int) $dashboard->postponements,
            payoutMinor: (int) $dashboard->payout_minor,
            currency: (string) $dashboard->currency,
            lastSessionAt: $dashboard->last_session_at?->toIso8601String(),
        );
    }

    private function toStudentRow(StudentDashboard $dashboard): StudentDashboardRow
    {
        return new StudentDashboardRow(
            enrollmentId: (string) $dashboard->enrollment_id,
            studentProfileId: (string) $dashboard->student_profile_id,
            sessionsAttended: (int) $dashboard->sessions_attended,
            sessionsMissed: (int) $dashboard->sessions_missed,
            attendanceRateBp: (int) $dashboard->attendance_rate_bp,
            violationsCount: (int) $dashboard->violations_count,
            freezesCount: (int) $dashboard->freezes_count,
            lastSessionAt: $dashboard->last_session_at?->toIso8601String(),
        );
    }
}
