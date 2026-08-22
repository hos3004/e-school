<?php

declare(strict_types=1);

namespace Modules\Groups\Domain\Enums;

/**
 * دورة حياة انتساب الطالب للمجموعة.
 *
 * الانتساب النشط هو ما يُحتسب في سعة المجموعة؛ مغادرته تُثبَّت بـ left_at
 * ولا يُحذف السجل أبدًا. الانتقال يمر دائمًا عبر canTransitionTo.
 */
enum MembershipStatus: string
{
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

    public function label(): string
    {
        return __('groups::status.membership.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::Left => 'gray',
        };
    }
}
