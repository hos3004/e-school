<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Domain\Enums;

/**
 * دورة حياة التقرير الشهري.
 *
 * يُولَّد مسوّدةً آليًا، ثم يعتمده المشرف، ثم يُرسل للطالب ووليّ الأمر.
 * بعد الإرسال يصبح نهائيًا — أي تصحيح لاحق يكون بتقرير جديد موثّق.
 */
enum MonthlyReportStatus: string
{
    /** مسوّدة أولية يراها المشرف فقط. */
    case Draft = 'draft';

    /** اعتمدها المشرف وجاهزة للإرسال. */
    case Approved = 'approved';

    /** أُرسلت للطالب ووليّ الأمر — نهائية. */
    case Sent = 'sent';

    /**
     * الانتقالات المسموحة. أي انتقال غير مذكور هنا مرفوض.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Approved],
            self::Approved => [self::Sent],
            self::Sent => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /** هل هذه الحالة نهائية؟ */
    public function isTerminal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    public function label(): string
    {
        return __('academicreports::status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Approved => 'blue',
            self::Sent => 'emerald',
        };
    }
}
