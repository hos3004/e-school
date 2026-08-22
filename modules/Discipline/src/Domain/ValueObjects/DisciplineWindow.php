<?php

declare(strict_types=1);

namespace Modules\Discipline\Domain\ValueObjects;

use Carbon\CarbonImmutable;

/**
 * نافذة احتساب المخالفات — المفتاح المخزَّن في العمود window_key (char 7).
 *
 * الشكل يأتي من config('discipline.counter_window'):
 *  - monthly   → «2026-08»
 *  - quarterly → «2026-Q3»
 *
 * العدّاد لا يُخزَّن رقمًا أبدًا؛ يُحسب دائمًا بجمع أحداث النافذة نفسها.
 */
final readonly class DisciplineWindow
{
    public function __construct(
        public string $key,
    ) {}

    public static function forDate(CarbonImmutable $date): self
    {
        return match ((string) config('discipline.counter_window', 'monthly')) {
            'quarterly' => new self(sprintf('%d-Q%d', $date->year, intdiv($date->month - 1, 3) + 1)),
            default => new self($date->format('Y-m')),
        };
    }

    public static function current(): self
    {
        return self::forDate(CarbonImmutable::now('UTC'));
    }

    public function equals(self $other): bool
    {
        return $this->key === $other->key;
    }

    public function __toString(): string
    {
        return $this->key;
    }
}
