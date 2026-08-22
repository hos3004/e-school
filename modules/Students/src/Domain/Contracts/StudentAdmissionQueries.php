<?php

declare(strict_types=1);

namespace Modules\Students\Domain\Contracts;

interface StudentAdmissionQueries
{
    /**
     * هل الطالب مجاز للتوزيع على برنامج أو مجموعة؟
     *
     * يجب على أي Action في Enrollments أو Groups استدعاء هذا العقد قبل الكتابة؛
     * القيمة true لا تُعاد إلا لطلب waiting_assignment أو assigned.
     */
    public function isClearedForAssignment(string $studentProfileId): bool;
}
