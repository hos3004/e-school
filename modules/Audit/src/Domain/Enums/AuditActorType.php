<?php

declare(strict_types=1);

namespace Modules\Audit\Domain\Enums;

/**
 * نوع الفاعل الذي أنشأ قيدة التدقيق.
 *
 * قيود التدقيق قد تصدر عن مستخدم بشري، أو عن النظام (مجدول/حدث آلي)،
 * أو عن تكامل خارجي معتمد. النوع يُخزَّن في العمود actor_type.
 */
enum AuditActorType: string
{
    /** مستخدم بشري مسجّل الدخول. */
    case User = 'user';

    /** عملية آلية داخل المنصة (مجدول، حدث نظام). */
    case System = 'system';

    /** تكامل خارجي عبر API مفتاح خدمة. */
    case Integration = 'integration';

    public function label(): string
    {
        return __('audit::labels.actor_types.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::User => 'blue',
            self::System => 'gray',
            self::Integration => 'violet',
        };
    }
}
