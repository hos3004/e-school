<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Modules\Scheduling\Application\Actions\UpdateScheduleAction;
use Modules\Scheduling\Application\Services\ScheduleNotificationPayloadFactory;
use Modules\Scheduling\Domain\Models\Schedule;

/**
 * رسالة تعديل جدول متكرر كانت تسرد كل موعد مولّد — 52 و77 سطرًا في حادثة
 * 18 سبتمبر 2026 — فوصلت الرسالة الواحدة في ثلاث فقاعات واتساب متتالية.
 * الرسالة الصحيحة تذكر النمط الأسبوعي فقط.
 */

/** @param array<string, object> $fixture */
function conciseSummarySchedule(array $fixture): Schedule
{
    return Schedule::query()->create([
        'organization_id' => (string) $fixture['organization']->id,
        'group_id' => (string) $fixture['group']->id,
        'course_id' => (string) $fixture['course']->id,
        'staff_profile_id' => (string) $fixture['teacher']->id,
        'session_type' => 'group',
        'rrule' => 'FREQ=WEEKLY;INTERVAL=1;BYDAY=TU,FR,SA',
        'start_time' => '17:00',
        'duration_minutes' => 25,
        'timezone' => 'Europe/Istanbul',
        'starts_on' => '2026-09-09',
        'materialized_until' => '2026-09-09',
        'is_active' => true,
        'created_by' => (string) $fixture['operator']->id,
    ]);
}

it('summarises a multi-slot schedule as a short weekly pattern, not a list of dates', function (): void {
    $fixture = schedulingFixture();
    $schedule = conciseSummarySchedule($fixture);
    $schedule->weeklySlots()->createMany([
        ['organization_id' => $schedule->organization_id, 'weekday' => 2, 'start_time' => '17:00'],
        ['organization_id' => $schedule->organization_id, 'weekday' => 5, 'start_time' => '17:30'],
        ['organization_id' => $schedule->organization_id, 'weekday' => 6, 'start_time' => '16:00'],
    ]);

    $payload = app(ScheduleNotificationPayloadFactory::class)->forSchedule($schedule->fresh());

    expect($payload['weeklyPattern']['ar'])
        ->toContain('17:00')
        ->toContain('17:30')
        ->toContain('16:00')
        ->not->toContain('2026-');
});

it("falls back to the schedule's single start_time when it has no weekly_slots rows", function (): void {
    $fixture = schedulingFixture();
    $schedule = conciseSummarySchedule($fixture);

    expect($schedule->weeklySlots()->count())->toBe(0);

    $payload = app(ScheduleNotificationPayloadFactory::class)->forSchedule($schedule->fresh());

    expect($payload['weeklyPattern']['ar'])->toContain('17:00');
});

it('sends one short recurring-pattern message instead of a per-date dump when a schedule is edited', function (): void {
    CarbonImmutable::setTestNow('2026-10-10 08:00:00 UTC');
    $fixture = schedulingFixture();

    $schedule = createOperationalSchedule($fixture, [
        'weekdays' => [6, 0],
        'start_time' => '12:00',
        'timezone' => 'UTC',
        'starts_on' => '2026-10-10',
        'ends_on' => '2026-11-10',
    ]);

    app(UpdateScheduleAction::class)->execute(
        $schedule,
        schedulePayload($fixture, [
            'weekdays' => [1],
            'start_time' => '14:00',
            'starts_on' => '2026-10-10',
            'ends_on' => '2026-11-10',
        ]),
        (string) $fixture['operator']->id,
        'تعديل الموعد الأسبوعي — اختبار الرسالة المختصرة',
    );

    $row = NotificationOutbox::query()
        ->where('event_name', 'schedule.times_changed')
        ->where('channel', 'in_app')
        ->latest('created_at')
        ->first();

    expect($row)->not->toBeNull();

    $body = is_array($row->body) ? implode(' ', $row->body) : (string) $row->body;

    // لا قائمة تواريخ سُردت (effective_from وحده قد يحمل تاريخًا)، والنمط الجديد واضح.
    expect(substr_count($body, '2026-'))->toBeLessThanOrEqual(1)
        ->and($body)->toContain('14:00');
});
