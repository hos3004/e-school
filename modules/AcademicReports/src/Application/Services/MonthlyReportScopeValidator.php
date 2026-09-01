<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Application\Services;

use Modules\Enrollments\Domain\Contracts\EnrollmentAdministrationQueries;
use Modules\Enrollments\Domain\ValueObjects\EnrollmentSummaryData;
use Modules\Students\Domain\Contracts\StudentDirectoryQueries;
use Shared\Support\BusinessRuleViolation;

final readonly class MonthlyReportScopeValidator
{
    public function __construct(
        private StudentDirectoryQueries $students,
        private EnrollmentAdministrationQueries $enrollments,
    ) {}

    public function validate(string $organizationId, string $studentProfileId, string $enrollmentId): void
    {
        $student = $this->students->find($organizationId, $studentProfileId);

        if ($student === null || $student->archived) {
            throw BusinessRuleViolation::make(
                'academicreports.monthly_report.invalid_student',
                'academicreports::errors.monthly_report_invalid_student',
            );
        }

        $enrollment = collect($this->enrollments->forStudent($organizationId, $studentProfileId))
            ->first(static fn (EnrollmentSummaryData $candidate): bool => $candidate->id === $enrollmentId);

        if ($enrollment === null) {
            throw BusinessRuleViolation::make(
                'academicreports.monthly_report.invalid_enrollment',
                'academicreports::errors.monthly_report_invalid_enrollment',
            );
        }
    }
}
