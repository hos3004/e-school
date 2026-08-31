<?php

declare(strict_types=1);

namespace Modules\Groups\Domain\Enums;

/**
 * دورة حياة انتساب الطالب للمجموعة.
 *
 * الانتساب النشط هو ما يحضر فعلًا؛ المعلّق هو ما سُكّن في مجموعة «قيد التخطيط»
 * قبل اكتمال بياناتها. الحالتان تشغلان مقعدًا في السعة، ومغادرة أي منهما
 * تُثبَّت بـ left_at ولا يُحذف السجل أبدًا. الانتقال يمر دائمًا عبر
 * canTransitionTo.
 */
enum MembershipStatus: string
{
    /**
     * معلّق — سُكّن الطالب في مجموعة «قيد التخطيط».
     *
     * يشغل مقعدًا في السعة ولا يمنح وصولًا للحصص؛ يترقّى إلى Active عند تفعيل
     * المجموعة عبر ActivateGroupAction.
     */
    case Pending = 'pending';

    /** منتسب حاليًا ويحضر مع المجموعة. */
    case Active = 'active';

    /** غادر المجموعة — انتهت علاقته بها وتثبيت وقت المغادرة. */
    case Left = 'left';

    /**
     * الانتقالات المسموحة.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Active, self::Left],
            self::Active => [self::Left],
            self::Left => [],
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

    /** هل يشغل هذا الانتساب مقعدًا في سعة المجموعة؟ */
    public function occupiesSeat(): bool
    {
        return in_array($this, [self::Pending, self::Active], true);
    }

    /** هل يمنح هذا الانتساب وصولًا فعليًا لحصص المجموعة ومحتواها؟ */
    public function grantsAccess(): bool
    {
        return $this === self::Active;
    }

    public function label(): string
    {
        return __('groups::status.membership.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Active => 'green',
            self::Left => 'gray',
        };
    }
}
