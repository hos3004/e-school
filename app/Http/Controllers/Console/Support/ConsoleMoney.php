<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console\Support;

use InvalidArgumentException;
use NumberFormatter;
use ResourceBundle;
use Shared\ValueObjects\Money;

/** Human-entered console amounts: exact string conversion, ICU currency precision. */
final class ConsoleMoney
{
    public static function fractionDigits(string $currency): int
    {
        $currency = strtoupper($currency);
        $currencies = ResourceBundle::create('en', 'ICUDATA-curr')?->get('Currencies');
        if (!$currencies instanceof ResourceBundle || !$currencies->get($currency) instanceof ResourceBundle) {
            throw new InvalidArgumentException(__('console_courses.money_currency'));
        }
        $formatter = new NumberFormatter('en_US', NumberFormatter::CURRENCY);
        if (!$formatter->setTextAttribute(NumberFormatter::CURRENCY_CODE, $currency)) {
            throw new InvalidArgumentException(__('console_courses.money_currency'));
        }
        $digits = $formatter->getAttribute(NumberFormatter::FRACTION_DIGITS);
        if (!is_int($digits) || $digits < 0) {
            throw new InvalidArgumentException(__('console_courses.money_currency'));
        }

        return $digits;
    }

    public static function fromMajor(string $amount, string $currency): Money
    {
        $precision = self::fractionDigits($currency);
        $amount = str_replace([',', '٫'], '.', trim($amount));
        if (!preg_match('/^([0-9]+)(?:\\.([0-9]+))?$/D', $amount, $parts)) {
            throw new InvalidArgumentException(__('console_courses.money_invalid'));
        }
        $fraction = $parts[2] ?? '';
        if (strlen($fraction) > $precision) {
            throw new InvalidArgumentException(__('console_courses.money_precision', ['currency' => strtoupper($currency), 'digits' => $precision]));
        }
        $minor = ltrim($parts[1].str_pad($fraction, $precision, '0'), '0');
        $minor = $minor === '' ? '0' : $minor;
        $maximum = (string) PHP_INT_MAX;
        if (strlen($minor) > strlen($maximum) || (strlen($minor) === strlen($maximum) && strcmp($minor, $maximum) > 0)) {
            throw new InvalidArgumentException(__('console_courses.money_too_large'));
        }

        return Money::of((int) $minor, $currency);
    }

    public static function toMajor(Money $money): string
    {
        $precision = self::fractionDigits($money->currency);
        $negative = $money->minorUnits < 0;
        $minor = ltrim((string) $money->minorUnits, '-');
        if ($precision === 0) {
            return ($negative ? '-' : '').$minor;
        }
        $digits = str_pad($minor, $precision + 1, '0', STR_PAD_LEFT);

        return ($negative ? '-' : '').substr($digits, 0, -$precision).'.'.substr($digits, -$precision);
    }
}
