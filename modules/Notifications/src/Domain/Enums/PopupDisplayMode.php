<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\Enums;

/**
 * نمط عرض النافذة المنبثقة — شريط سفلي غير حابس، أو ملء شاشة.
 */
enum PopupDisplayMode: string
{
    case BottomBanner = 'bottom_banner';
    case Fullscreen = 'fullscreen';

    public function label(): string
    {
        return __('notifications::popups.display_mode.'.$this->value);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(static fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}
