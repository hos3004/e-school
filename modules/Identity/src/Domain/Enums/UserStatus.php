<?php

declare(strict_types=1);

namespace Modules\Identity\Domain\Enums;

/**
 * دورة حياة حساب المستخدم.
 *
 * القاعدة: الحساب الموقوف أو المجمّد لا يُحذف أبدًا — نغيّر حالته فقط
 * ونمنع وصوله للمنصة. لذلك كل تغيير حالة يمر عبر canTransitionTo.
 */
enum UserStatus: string
{
    /** حساب نشط يمكنه الدخول والعمل. */
    case Active = 'active';

    /** موقوف إداريًا — دخول ممنوع مؤقتًا، قابل لإعادة التفعيل. */
    case Suspended = 'suspended';

    /** مجمّد (مثلًا لعدم السداد) — بيانات محفوظة، وصول ممنوع حتى الفكّ. */
    case Frozen = 'frozen';

    /**
     * الانتقالات المسموحة. أي انتقال غير مذكور هنا مرفوض.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Active => [
                self::Suspended,
                self::Frozen,
            ],
            self::Suspended => [
                self::Active,
                self::Frozen,
            ],
            self::Frozen => [
                self::Active,
                self::Suspended,
            ],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /** هل يستطيع الحساب في هذه الحالة الدخول إلى المنصة؟ */
    public function allowsLogin(): bool
    {
        return $this === self::Active;
    }

    public function label(): string
    {
        return __('identity::status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Suspended => 'warning',
            self::Frozen => 'danger',
        };
    }
}
