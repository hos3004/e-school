<?php

declare(strict_types=1);

namespace Modules\Discipline\Domain\ValueObjects;

use Carbon\CarbonImmutable;

/**
 * نافذة احتساب المخالفات — المفتاح المخزَّن في العمود window_key (char 7).
 *
 * الشكل يأتي من config('discipline.counter_window'):
 *  - rolling   → «R30»     آخر N يومًا من لحظة الاحتساب  ← الافتراضي
 *  - monthly   → «2026-08»  تصفير أول كل شهر ميلادي
 *  - quarterly → «2026-Q3»
 *
 * تحديث العميل 2026-08-22: النافذة الصحيحة **متحركة** لا شهرية.
 * انظر docs/client-answers.md §CLIENT UPDATE §ل.
 *
 * النافذة المتحركة لا تُصنَّف بمفتاح دلوٍ ثابت — لذلك يجب أن يفلتر العدّ
 * على المدى الزمني الذي يعيده range() لا على المفتاح وحده.
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
        return match (self::shape()) {
            'quarterly' => new self(sprintf('%d-Q%d', $date->year, intdiv($date->month - 1, 3) + 1)),
            'monthly' => new self($date->format('Y-m')),
            default => new self('R'.self::days()),
        };
    }

    /**
     * المدى الزمني الفعلي الذي يُحتسب داخله — نصف مفتوح [start, end).
     *
     * هذا هو ما يجب أن يفلتر عليه العدّ. النافذة المتحركة ليس لها دلو ثابت،
     * فالاعتماد على window_key وحده يعطي نتيجة خاطئة معها.
     *
     * @return array{start: CarbonImmutable, end: CarbonImmutable}
     */
    public static function rangeEndingAt(CarbonImmutable $moment): array
    {
        return match (self::shape()) {
            'quarterly' => [
                'start' => $moment->startOfQuarter(),
                'end' => $moment->startOfQuarter()->addQuarter(),
            ],
            'monthly' => [
                'start' => $moment->startOfMonth(),
                'end' => $moment->startOfMonth()->addMonth(),
            ],
            default => [
                'start' => $moment->subDays(self::days()),
                'end' => $moment,
            ],
        };
    }

    /**
     * عدد أيام النافذة المتحركة — إعداد لا رقم داخل الكود.
     */
    public static function days(): int
    {
        return max(1, (int) config('discipline.counter_window_days', 30));
    }

    public static function shape(): string
    {
        return (string) config('discipline.counter_window', 'rolling');
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
