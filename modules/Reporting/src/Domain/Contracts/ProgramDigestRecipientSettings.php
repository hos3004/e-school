<?php

declare(strict_types=1);

namespace Modules\Reporting\Domain\Contracts;

use Illuminate\Validation\ValidationException;
use Modules\Reporting\Domain\ValueObjects\ProgramDigestRecipientData;

/**
 * قراءة وكتابة إعداد مستلم التقرير الشهري المجمَّع للبرامج.
 *
 * الكتابة تُسجَّل في audit_log دائمًا (من يستلم بيانات الطلاب إعداد حساس).
 */
interface ProgramDigestRecipientSettings
{
    /** الإعداد العام الحالي لهذه المؤسسة، أو null إن لم يُضبط بعد. */
    public function current(string $organizationId): ?ProgramDigestRecipientData;

    /**
     * حفظ الإعداد العام (program_id فارغ) — يُنشئ أو يستبدل السطر الوحيد.
     *
     * @throws ValidationException عند تعارض إصدار متزامن أو بيانات غير صالحة
     */
    public function saveGlobal(
        string $organizationId,
        string $recipientType,
        ?string $recipientUserId,
        ?string $customEmail,
        string $actorId,
        string $reason,
        ?string $expectedVersion,
    ): ProgramDigestRecipientData;

    /** البريد الفعلي القابل للإرسال إليه الآن، أو null إن لم يوجد إعداد صالح. */
    public function resolveEmail(string $organizationId): ?string;
}
