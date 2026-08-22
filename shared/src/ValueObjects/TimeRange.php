<?php

declare(strict_types=1);

namespace Shared\ValueObjects;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * فترة زمنية مغلقة من البداية ومفتوحة من النهاية: [start, end)
 *
 * الفتح من النهاية مقصود — حصة تنتهي 18:00 وأخرى تبدأ 18:00 ليستا متعارضتين.
 * كل الأوقات UTC. التحويل لتوقيت المستخدم يحدث في طبقة العرض فقط.
 */
final readonly class TimeRange
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
    ) {
        if ($end->lessThanOrEqualTo($start)) {
            throw new InvalidArgumentException('نهاية الفترة يجب أن تكون بعد بدايتها.');
        }
    }

    public static function of(CarbonImmutable $start, CarbonImmutable $end): self
    {
        return new self($start->utc(), $end->utc());
    }

    public static function fromDuration(CarbonImmutable $start, int $minutes): self
    {
        return new self($start->utc(), $start->utc()->addMinutes($minutes));
    }

    public function durationInMinutes(): int
    {
        return (int) $this->start->diffInMinutes($this->end);
    }

    /**
     * تعارض حقيقي: تتداخل الفترتان في أي لحظة.
     */
    public function overlaps(self $other): bool
    {
        return $this->start->lessThan($other->end) && $other->start->lessThan($this->end);
    }

    public function contains(CarbonImmutable $moment): bool
    {
        return $moment->greaterThanOrEqualTo($this->start) && $moment->lessThan($this->end);
    }

    /**
     * هل تقع هذه الفترة بالكامل داخل الأخرى؟ (تحقّق من إتاحة المعلم)
     */
    public function isWithin(self $other): bool
    {
        return $this->start->greaterThanOrEqualTo($other->start)
            && $this->end->lessThanOrEqualTo($other->end);
    }

    /**
     * توسيع الفترة بهامش قبلها وبعدها — لحساب نافذة الدخول للفصل
     * أو فرض فاصل بين حصتين متتاليتين لنفس المعلم.
     */
    public function expandedBy(int $beforeMinutes, ?int $afterMinutes = null): self
    {
        return new self(
            $this->start->subMinutes($beforeMinutes),
            $this->end->addMinutes($afterMinutes ?? $beforeMinutes),
        );
    }

    public function isPast(?CarbonImmutable $now = null): bool
    {
        return $this->end->lessThanOrEqualTo($now ?? CarbonImmutable::now('UTC'));
    }

    public function isFuture(?CarbonImmutable $now = null): bool
    {
        return $this->start->greaterThan($now ?? CarbonImmutable::now('UTC'));
    }

    public function isOngoing(?CarbonImmutable $now = null): bool
    {
        return $this->contains($now ?? CarbonImmutable::now('UTC'));
    }

    /**
     * كم دقيقة تبقّت حتى بداية الفترة — سالبة لو بدأت بالفعل.
     */
    public function minutesUntilStart(?CarbonImmutable $now = null): int
    {
        return (int) ($now ?? CarbonImmutable::now('UTC'))->diffInMinutes($this->start, false);
    }

    /**
     * @return array{start: string, end: string}
     */
    public function toArray(): array
    {
        return [
            'start' => $this->start->toIso8601String(),
            'end' => $this->end->toIso8601String(),
        ];
    }
}
