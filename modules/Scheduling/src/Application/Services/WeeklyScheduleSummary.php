<?php

declare(strict_types=1);

namespace Modules\Scheduling\Application\Services;

use Carbon\CarbonImmutable;

/**
 * يصوغ الموعد الأسبوعي نصًا مترجمًا لكل لغة مدعومة، لاستهلاكه في الإشعارات.
 *
 * لا نص واجهة داخل الشيفرة: أسماء الأيام من ترجمات Carbon، والفواصل من ملفات
 * ترجمة الموديول.
 */
final readonly class WeeklyScheduleSummary
{
    /**
     * @param list<array{weekday: int, start_time: string}> $slots
     * @return array<string, string>
     */
    public function localized(array $slots): array
    {
        $locales = array_values(array_filter(
            (array) config('notifications.localization.supported', ['ar', 'en']),
            static fn (mixed $locale): bool => is_string($locale) && $locale !== '',
        ));

        if ($locales === []) {
            $locales = ['ar'];
        }

        $summary = [];
        foreach ($locales as $locale) {
            $summary[(string) $locale] = $this->forLocale($slots, (string) $locale);
        }

        return $summary;
    }

    /** @param list<array{weekday: int, start_time: string}> $slots */
    public function forLocale(array $slots, string $locale): string
    {
        $separator = (string) __('scheduling::schedule_change.slot_separator', [], $locale);
        $parts = [];

        foreach ($slots as $slot) {
            $parts[] = __('scheduling::schedule_change.slot', [
                'day' => $this->weekdayName((int) $slot['weekday'], $locale),
                'time' => substr((string) $slot['start_time'], 0, 5),
            ], $locale);
        }

        return implode($separator, $parts);
    }

    private function weekdayName(int $weekday, string $locale): string
    {
        return CarbonImmutable::create(2024, 1, 7, 0, 0, 0, 'UTC')
            ->addDays($weekday)
            ->locale($locale)
            ->translatedFormat('l');
    }
}
