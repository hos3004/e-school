<?php

declare(strict_types=1);

namespace Modules\Students\Domain\Contracts;

use Modules\Students\Domain\ValueObjects\AdmissionCandidateData;

interface StudentAdmissionQueries
{
    /**
     * هل الطالب مجاز للتوزيع على برنامج أو مجموعة؟
     *
     * يجب على أي Action في Enrollments أو Groups استدعاء هذا العقد قبل الكتابة؛
     * القيمة true لا تُعاد إلا لطلب waiting_assignment أو assigned.
     */
    public function isClearedForAssignment(string $studentProfileId): bool;

    /**
     * طلبات التسجيل المحددة في التسكين الجماعي، محصورة بمؤسسة المنفّذ.
     *
     * الحصر على المؤسسة داخل الاستعلام نفسه لا في المتصل: المعرّفات تصل من
     * المتصفح ولا يُوثق بها، فالطلب الخارج عن المؤسسة لا يُعاد أصلًا.
     *
     * @param list<string> $applicationIds
     * @return list<AdmissionCandidateData>
     */
    public function placementCandidates(string $organizationId, array $applicationIds): array;
}
