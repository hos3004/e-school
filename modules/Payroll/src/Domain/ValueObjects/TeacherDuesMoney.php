<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\ValueObjects;

use InvalidArgumentException;
use Shared\ValueObjects\Money;

/** Decimal input/output without binary floating-point conversion. */
final class TeacherDuesMoney
{
    public static function fromMajor(string $value, string $currency): Money
    {
        if (!preg_match('/^\d{1,16}(?:\.\d{1,2})?$/', $value)) {
            throw new InvalidArgumentException('Invalid decimal amount.');
        }
        $parts = explode('.', $value, 2);
        $minor = (int) ($parts[0].str_pad($parts[1] ?? '', 2, '0'));

        return Money::of($minor, $currency);
    }

    public static function display(int $minor): string
    {
        $raw = (string) $minor;
        $negative = str_starts_with($raw, '-');
        $digits = str_pad(ltrim($raw, '-'), 3, '0', STR_PAD_LEFT);

        return ($negative ? '-' : '').substr($digits, 0, -2).'.'.substr($digits, -2);
    }
}
