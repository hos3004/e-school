<?php

declare(strict_types=1);

namespace Modules\Integrations\Domain\Enums;

/**
 * دورة حياة الاتصال بمزوّد خارجي.
 *
 * أي تغيير حالة يمر عبر canTransitionTo — لا تعيين مباشر للنص أبدًا.
 */
enum ConnectionStatus: string
{
    /** أُنشئ الاتصال ولم يُفعَّل بعد. */
    case Pending = 'pending';

    /** مُفعَّل ويقبل الإرسال والاستقبال. */
    case Active = 'active';

    /** فشل آخر تفاعل مع المزوّد — ينتظر إعادة محاولة تفعيل. */
    case Error = 'error';

    /** موقوف بإدارة المؤسسة. */
    case Disabled = 'disabled';

    /** انتهت صلاحية بيانات الاعتماد. */
    case Expired = 'expired';

    /**
     * الانتقالات المسموحة. أي انتقال غير مذكور هنا مرفوض.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [
                self::Active,
                self::Disabled,
            ],
            self::Active => [
                self::Error,
                self::Disabled,
                self::Expired,
            ],
            self::Error => [
                self::Active,
                self::Disabled,
                self::Expired,
            ],
            self::Disabled => [
                self::Pending,
                self::Active,
            ],

            // نهائية عمليًا — تتطلب إعادة إدخال بيانات الاعتماد من الصفر.
            self::Expired => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /** الحالة النهائية لا تسمح بأي انتقال لاحق. */
    public function isTerminal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    /** هل يقبل هذا الاتصال إرسال أو استقبال Webhooks؟ */
    public function acceptsDeliveries(): bool
    {
        return $this === self::Active;
    }

    public function label(): string
    {
        return __('integrations::status.connection.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Active => 'green',
            self::Error => 'amber',
            self::Disabled => 'blue',
            self::Expired => 'rose',
        };
    }
}
