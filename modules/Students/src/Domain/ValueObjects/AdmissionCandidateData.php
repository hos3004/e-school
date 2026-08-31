<?php

declare(strict_types=1);

namespace Modules\Students\Domain\ValueObjects;

/**
 * طلب تسجيل بصورته اللازمة لقرار التسكين — بلا نموذج Eloquent.
 *
 * الكود هو `student_code` المعروض (E001) لا الـULID؛ الواجهات تعرض الاسم
 * والكود، ولا تعرض معرّفات داخلية للمستخدم أبدًا.
 */
final readonly class AdmissionCandidateData
{
    public function __construct(
        public string $applicationId,
        public string $organizationId,
        public ?string $studentProfileId,
        public string $fullName,
        public ?string $studentCode,
        public string $status,
        public bool $clearedForAssignment,
        public ?string $preferredProgramId,
        public ?string $preferredCourseId,
    ) {}

    /** الكود المعروض، أو نص بديل مترجم حين لم يُنشأ ملف الطالب بعد. */
    public function displayCode(): string
    {
        return $this->studentCode ?? __('students::admin.common.not_available');
    }
}
