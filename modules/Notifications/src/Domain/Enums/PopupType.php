<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\Enums;

enum PopupType: string
{
    case UrgentAnnouncement = 'urgent_announcement';
    case ProgramPromotion = 'program_promotion';
    case Reminder = 'reminder';
    case Administrative = 'administrative';
    case General = 'general';

    public function label(): string
    {
        return __('notifications::popups.type.'.$this->value);
    }

    /** أيقونة من Design System — لا CSS مخصص من الأدمن. */
    public function icon(): string
    {
        return match ($this) {
            self::UrgentAnnouncement => 'heroicon-o-exclamation-triangle',
            self::ProgramPromotion => 'heroicon-o-megaphone',
            self::Reminder => 'heroicon-o-bell-alert',
            self::Administrative => 'heroicon-o-clipboard-document-list',
            self::General => 'heroicon-o-information-circle',
        };
    }

    /** لون دلالي ثابت لكل نوع. */
    public function color(): string
    {
        return match ($this) {
            self::UrgentAnnouncement => 'danger',
            self::ProgramPromotion => 'primary',
            self::Reminder => 'warning',
            self::Administrative => 'info',
            self::General => 'gray',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(static fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}
