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
}
