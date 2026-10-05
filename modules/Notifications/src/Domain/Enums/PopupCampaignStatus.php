<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\Enums;

use Carbon\CarbonImmutable;

enum PopupCampaignStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Paused = 'paused';
    case Archived = 'archived';

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Draft => $target === self::Published,
            self::Published => $target === self::Paused || $target === self::Archived,
            self::Paused => $target === self::Published || $target === self::Archived,
            // الأرشفة حالة نهائية — لا خروج منها.
            self::Archived => false,
        };
    }

    public function label(): string
    {
        return __('notifications::popups.status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Published => 'success',
            self::Paused => 'warning',
            self::Archived => 'danger',
        };
    }

    /**
     * الحالة الفعلية المشتقة: الجدولة تحكم الظهور لا الحالة المخزّنة فقط.
     */
    public function effectiveLabel(?CarbonImmutable $startsAt, ?CarbonImmutable $endsAt, CarbonImmutable $now): string
    {
        if ($this !== self::Published) {
            return $this->label();
        }

        return match (true) {
            $startsAt !== null && $startsAt->greaterThan($now) => __('notifications::popups.effective_status.scheduled'),
            $endsAt !== null && $endsAt->lessThanOrEqualTo($now) => __('notifications::popups.effective_status.expired'),
            default => __('notifications::popups.effective_status.active'),
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
