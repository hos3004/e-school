<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\Enums;

/**
 * قنوات التسليم المدعومة في المنصة.
 *
 * القنوات المفعّلة تشغيليًا تُقرأ من config('notifications.channels.enabled')
 * — هذا الـ enum يعرّف الأنواع المعروفة فقط، والتفعيل قرار إعداد لا كود.
 */
enum Channel: string
{
    case Email = 'email';

    case Sms = 'sms';

    case Push = 'push';

    case Whatsapp = 'whatsapp';

    case InApp = 'in_app';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $channel): string => $channel->value,
            self::cases(),
        );
    }

    public function label(): string
    {
        return __('notifications::channels.'.$this->value);
    }
}
