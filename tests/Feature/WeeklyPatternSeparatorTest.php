<?php

declare(strict_types=1);

use Modules\Scheduling\Application\Services\WeeklyScheduleSummary;

/**
 * الفاصل بلا مسافات ألصق موعدين معًا في نص النمط الأسبوعي: «17:00والجمعة» —
 * ظهر في أول رسالة حقيقية بعد تقصير رسالة تعديل الجدول، 18 سبتمبر 2026.
 */
it('separates weekly slots with visible spacing in arabic', function (): void {
    $summary = app(WeeklyScheduleSummary::class)->forLocale([
        ['weekday' => 2, 'start_time' => '17:00'],
        ['weekday' => 5, 'start_time' => '17:30'],
    ], 'ar');

    expect($summary)->not->toContain('00والجمعة')
        ->and($summary)->toContain(' و ');
});
