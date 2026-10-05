<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\Enums;

enum PopupPlacement: string
{
    case AfterLogin = 'after_login';
    case Dashboard = 'dashboard';
    case SpecificPage = 'specific_page';
    case AllAuthenticatedPages = 'all_authenticated_pages';

    public function label(): string
    {
        return __('notifications::popups.placement.'.$this->value);
    }

    /**
     * هل يطابق هذا الموضع سياق الطلب؟ تطابق قيمة الموضع نفسه يتم في
     * استعلام القاعدة؛ هنا يُفحص مفتاح الصفحة الحالية فقط.
     */
    public function matches(?string $requestedPageKey, ?string $campaignPageKey): bool
    {
        return match ($this) {
            self::AfterLogin, self::Dashboard, self::AllAuthenticatedPages => true,
            self::SpecificPage => $requestedPageKey !== null && $requestedPageKey === ($campaignPageKey ?? ''),
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
