<?php

declare(strict_types=1);

namespace Modules\Guardians\Domain\Enums;

/**
 * القناة المفضلة للتواصل مع الوصي — توجّه لاختيار قناة الإشعارات.
 */
enum ContactChannel: string
{
    case PhoneCall = 'phone_call';
    case WhatsApp = 'whatsapp';
    case Sms = 'sms';
    case Email = 'email';
    case InApp = 'in_app';

    public function label(): string
    {
        return __('guardians::contact_channel.'.$this->value);
    }
}
