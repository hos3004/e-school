<?php

declare(strict_types=1);

namespace Modules\Guardians\Domain\Contracts;

/**
 * عمليات ربط/فكّ روابط الأوصياء المعلنة لطبقة التركيب.
 *
 * لا كتابة مباشرة في جدول الروابط من خارج الموديول؛ كل عملية
 * تمر هنا للتحقق من المؤسسة ثم تفويض الإجراءات الرسمية المدققة.
 */
interface GuardianLinkOperations
{
    /**
     * ربط وصي بطالب عبر LinkStudentToGuardian الرسمي.
     *
     * @param list<string>|null $visibleSections
     */
    public function link(
        string $organizationId,
        string $guardianProfileId,
        string $studentProfileId,
        string $relationship,
        bool $isPrimary,
        bool $canActFor,
        ?array $visibleSections,
        string $actorId,
        string $reason,
    ): string;

    /** فكّ رابط بسبب مكتوب — السجل يُؤرشف ولا يُحذف نهائيًا. */
    public function unlink(
        string $organizationId,
        string $guardianLinkId,
        string $actorId,
        string $reason,
    ): void;
}
