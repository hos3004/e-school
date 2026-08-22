<?php

declare(strict_types=1);

namespace Shared\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * مبلغ مالي — يُخزَّن دائمًا كعدد صحيح من الوحدات الصغرى (قروش/سنتات).
 *
 * قاعدة صارمة: لا float في أي حساب مالي في هذا المشروع.
 * 600 جنيه تُخزَّن 60000، وتُعرض 600.00 EGP.
 */
final readonly class Money implements JsonSerializable, Stringable
{
    private function __construct(
        public int $minorUnits,
        public string $currency,
    ) {}

    public static function of(int $minorUnits, string $currency = 'EGP'): self
    {
        return new self($minorUnits, strtoupper($currency));
    }

    /**
     * إنشاء من وحدة رئيسية بدقة منزلتين — للإدخال البشري فقط.
     */
    public static function fromMajor(int|string $major, string $currency = 'EGP'): self
    {
        $value = (string) $major;

        if (! preg_match('/^-?\d+(\.\d{1,2})?$/', $value)) {
            throw new InvalidArgumentException("قيمة مالية غير صالحة: {$value}");
        }

        return new self((int) round((float) $value * 100), strtoupper($currency));
    }

    public static function zero(string $currency = 'EGP'): self
    {
        return new self(0, strtoupper($currency));
    }

    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minorUnits + $other->minorUnits, $this->currency);
    }

    public function minus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minorUnits - $other->minorUnits, $this->currency);
    }

    /**
     * الضرب في عدد الحصص أو نسبة — يُقرَّب نصفيًا لأعلى بالقيمة المطلقة.
     */
    public function multipliedBy(int|float $factor): self
    {
        return new self((int) round($this->minorUnits * $factor, 0, PHP_ROUND_HALF_UP), $this->currency);
    }

    public function negated(): self
    {
        return new self(-$this->minorUnits, $this->currency);
    }

    public function isZero(): bool
    {
        return $this->minorUnits === 0;
    }

    public function isNegative(): bool
    {
        return $this->minorUnits < 0;
    }

    public function equals(self $other): bool
    {
        return $this->minorUnits === $other->minorUnits && $this->currency === $other->currency;
    }

    public function toMajor(): string
    {
        return number_format($this->minorUnits / 100, 2, '.', '');
    }

    public function format(?string $locale = null): string
    {
        $formatter = new \NumberFormatter($locale ?? app()->getLocale(), \NumberFormatter::CURRENCY);

        return $formatter->formatCurrency($this->minorUnits / 100, $this->currency);
    }

    /**
     * @return array{minor_units: int, currency: string}
     */
    public function jsonSerialize(): array
    {
        return ['minor_units' => $this->minorUnits, 'currency' => $this->currency];
    }

    public function __toString(): string
    {
        return $this->toMajor().' '.$this->currency;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "لا يمكن الجمع بين عملتين مختلفتين: {$this->currency} و {$other->currency}"
            );
        }
    }
}
