<?php

declare(strict_types=1);

namespace Modules\Enrollments\Domain\Enums;

/**
 * دورة حياة قيد الطالب في برنامج.
 *
 * قاعدة حاكمة من العميل: الحساب لا يُحذف أبدًا مهما كان السبب.
 * التجميد يمنع الوصول للكورسات فقط، والبيانات والسجل يبقيان كما هما.
 *
 * المخطط الكامل في docs/05-state-machines.md
 */
enum EnrollmentStatus: string
{
    /** طلب التحاق لم يُراجع بعد. */
    case Applied = 'applied';

    /** تحت المراجعة من التسجيل أو الإشراف الأكاديمي. */
    case UnderReview = 'under_review';

    /** مقبول ولم يبدأ الدراسة بعد. */
    case Approved = 'approved';

    /** قيد نشط — يحضر الحصص ويصل للمحتوى. */
    case Active = 'active';

    /** إيقاف اختياري بطلب الطالب مع تحديد موعد عودة. */
    case Paused = 'paused';

    /** تجميد تأديبي — آلي بعد استيفاء سُلَّم المخالفات، أو يدوي من الإدارة. */
    case Frozen = 'frozen';

    /** طلب فك التجميد قُدّم وينتظر التقييم. */
    case ReactivationRequested = 'reactivation_requested';

    /** التقييم جارٍ (اختبار جدية من الفريق الإداري). */
    case UnderAssessment = 'under_assessment';

    // ── الحالات النهائية ────────────────────────────────────────────────

    /** أنهى البرنامج بنجاح. */
    case Completed = 'completed';

    /** انسحب بإرادته. */
    case Withdrawn = 'withdrawn';

    /** رُفض طلب التحاقه. */
    case Rejected = 'rejected';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Applied => [self::UnderReview, self::Approved, self::Rejected],
            self::UnderReview => [self::Approved, self::Rejected],
            self::Approved => [self::Active, self::Withdrawn],
            self::Active => [self::Paused, self::Frozen, self::Completed, self::Withdrawn],
            self::Paused => [self::Active, self::Frozen, self::Withdrawn],
            self::Frozen => [self::ReactivationRequested, self::Withdrawn],
            self::ReactivationRequested => [self::UnderAssessment, self::Frozen],
            self::UnderAssessment => [self::Active, self::Frozen],

            self::Completed, self::Withdrawn, self::Rejected => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    /** هل يصل الطالب لمحتوى الكورسات وحصصها في هذه الحالة؟ */
    public function grantsCourseAccess(): bool
    {
        return $this === self::Active;
    }

    /**
     * هل يظل الحساب قائمًا ويرى سجله؟
     * الإجابة دائمًا نعم — التجميد تعليق وصول وليس حذفًا.
     */
    public function keepsAccount(): bool
    {
        return true;
    }

    /** هل يُجدول له حصص جديدة؟ */
    public function allowsScheduling(): bool
    {
        return in_array($this, [self::Approved, self::Active], true);
    }

    /** هل يُحتسب ضمن الطلاب النشطين في التقارير؟ */
    public function countsAsActive(): bool
    {
        return in_array($this, [self::Active, self::Paused], true);
    }

    public function label(): string
    {
        return __('enrollments::status.'.$this->value);
    }
}
