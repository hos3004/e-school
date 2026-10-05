<?php

declare(strict_types=1);

namespace Modules\Groups\Domain\Enums;

/**
 * دورة حياة المجموعة الدراسية.
 *
 * المجموعة تولد «قيد التخطيط»، تُفعَّل لتقبل الطلاب والمعلمين والبرامج،
 * ثم تُختم عند انتهاء مدتها. الانتقال يمر دائمًا عبر canTransitionTo.
 */
enum GroupStatus: string
{
    /** قيد التخطيط — لا تقبل تسجيل طلاب بعد. */
    case Planning = 'planning';

    /** نشطة — تقبل الطلاب والمعلمين والبرامج. */
    case Active = 'active';

    /** مُختمة — اكتملت مدتها أو أُغلق التسجيل نهائيًا. */
    case Completed = 'completed';

    /**
     * الانتقالات المسموحة. أي انتقال غير مذكور هنا مرفوض.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Planning => [self::Active],
            self::Active => [self::Completed],
            self::Completed => [],
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

    /** هل تقبل المجموعة تعديلات تشغيلية (معلمين/برامج) في هذه الحالة؟ */
    public function acceptsMembers(): bool
    {
        return in_array($this, [self::Planning, self::Active], true);
    }

    /** هل تقبل المجموعة تسجيل طلاب جدد؟ التسجيل يتطلب تفعيل المجموعة. */
    public function acceptsEnrollment(): bool
    {
        return $this === self::Active;
    }

    /**
     * هل تقبل المجموعة تسكينًا معلّقًا؟
     *
     * المجموعة «قيد التخطيط» تُنشأ بالحد الأدنى من البيانات ويُسكَّن فيها الطلاب
     * بانتساب `MembershipStatus::Pending` — يشغل مقعدًا ولا يمنح وصولًا. يترقّى
     * الانتساب إلى Active عند تفعيل المجموعة بعد اكتمال المعلم والمواعيد والسعة.
     */
    public function acceptsPendingEnrollment(): bool
    {
        return $this === self::Planning;
    }

    /** هل تقبل المجموعة تسكين طلاب بأي صورة — نشطة كانت أم قيد التخطيط؟ */
    public function acceptsPlacement(): bool
    {
        return $this->acceptsEnrollment() || $this->acceptsPendingEnrollment();
    }

    public function label(): string
    {
        return __('groups::status.group.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Planning => 'gray',
            self::Active => 'green',
            self::Completed => 'emerald',
        };
    }
}
