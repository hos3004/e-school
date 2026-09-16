<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console\Support;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * يحوّل مدخلات المستخدم (Y-m-d بتوقيت المؤسسة) إلى مدى UTC نصف مفتوح
 * [from, to) صالح لاستدعاء ProgramSessionReportDigestQueries، مع نفس منطق
 * حدود اليوم/الشهر المستعمل في أمر الإرسال الشهري — شاشتا الويب والأمر
 * المجدول يجب أن يتفقا على تعريف واحد لـ«اليوم» و«الشهر».
 */
final readonly class ReportDateRange
{
    public function __construct(
        public string $preset,
        public string $fromDate,
        public string $toDate,
        public CarbonImmutable $fromUtc,
        public CarbonImmutable $untilUtcExclusive,
    ) {}

    public static function resolve(?string $from, ?string $to, string $timezone): self
    {
        if ($from !== null && $from !== '' && $to !== null && $to !== '') {
            self::assertValidDate($from, 'from');
            self::assertValidDate($to, 'to');

            $fromLocal = CarbonImmutable::createFromFormat('Y-m-d', $from, $timezone)->startOfDay();
            $toLocal = CarbonImmutable::createFromFormat('Y-m-d', $to, $timezone)->startOfDay();

            if ($toLocal->lessThan($fromLocal)) {
                throw ValidationException::withMessages([
                    'to' => __('console_reports.errors.range_order'),
                ]);
            }

            return new self(
                'custom',
                $fromLocal->format('Y-m-d'),
                $toLocal->format('Y-m-d'),
                $fromLocal->setTimezone('UTC'),
                $toLocal->addDay()->setTimezone('UTC'),
            );
        }

        $now = CarbonImmutable::now($timezone);
        $today = $now->startOfDay();

        return new self(
            'today',
            $today->format('Y-m-d'),
            $today->format('Y-m-d'),
            $today->setTimezone('UTC'),
            $today->addDay()->setTimezone('UTC'),
        );
    }

    public static function resolveMonth(?string $from, ?string $to, ?string $month, string $timezone): self
    {
        if ($from !== null && $from !== '') {
            return self::resolve($from, $to, $timezone);
        }

        $now = CarbonImmutable::now($timezone);
        $target = $month !== null && $month !== ''
            ? self::parseMonth($month, $timezone)
            : $now->startOfMonth();

        $start = $target->startOfMonth()->startOfDay();
        $end = $target->endOfMonth()->startOfDay()->addDay();

        return new self(
            'month',
            $start->format('Y-m-d'),
            $end->subDay()->format('Y-m-d'),
            $start->setTimezone('UTC'),
            $end->setTimezone('UTC'),
        );
    }

    private static function parseMonth(string $month, string $timezone): CarbonImmutable
    {
        if (preg_match('/^\d{4}-\d{2}$/', $month) !== 1) {
            throw ValidationException::withMessages([
                'month' => __('console_reports.errors.invalid_month'),
            ]);
        }

        return CarbonImmutable::createFromFormat('Y-m-d', $month.'-01', $timezone);
    }

    private static function assertValidDate(string $value, string $field): void
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1 || !checkdate(
            (int) substr($value, 5, 2),
            (int) substr($value, 8, 2),
            (int) substr($value, 0, 4),
        )) {
            throw ValidationException::withMessages([
                $field => __('console_reports.errors.invalid_date'),
            ]);
        }
    }
}
