<?php

declare(strict_types=1);

namespace Modules\Enrollments\Domain\Contracts;

use Modules\Enrollments\Domain\ValueObjects\EnrollmentSummaryData;

interface EnrollmentAdministrationQueries
{
    /** @return list<EnrollmentSummaryData> */
    public function forStudent(string $organizationId, string $studentProfileId): array;

    /**
     * القيود التي تسمح بإنشاء حصص مستقبلية في برنامج، مفهرسة بالطالب.
     *
     * @param list<string> $studentProfileIds فارغة = كل طلاب البرنامج
     * @return array<string, string> student_profile_id => enrollment_id
     */
    public function schedulableEnrollmentIdsByStudent(
        string $organizationId,
        string $programId,
        array $studentProfileIds = [],
    ): array;

    /**
     * حقائق القيود اللازمة لحصيلة إقفال برنامج.
     *
     * `enrollments_live` مانع: قيد لم يصل حالة نهائية يعني طالبًا ما زال في
     * البرنامج، وأرشفة البرنامج تحته تخفي دراسة جارية.
     *
     * @return array{enrollments_total: int, enrollments_live: int, enrollments_completed: int, enrollments_withdrawn: int, students_distinct: int}
     */
    public function closureFactsForProgram(string $organizationId, string $programId): array;
}
