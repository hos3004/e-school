<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Modules\Notifications\Domain\Models\NotificationTemplate;
use Modules\Scheduling\Domain\Events\ScheduleCreated;
use Shared\Testing\Fixtures;

/**
 * رسالة «تم اعتماد الجدول الدراسي» كانت تسرد كل موعد مولّد — 17 سطرًا
 * بصيغة «2026-09-30 20:00 EEST» في رسالة واتساب واحدة. الرسالة الصحيحة
 * تذكر النمط الأسبوعي فقط.
 */
it('carries the weekly pattern in the schedule.created payload', function (): void {
    $event = new ScheduleCreated(
        scheduleId: (string) Str::ulid(),
        organizationId: Fixtures::organizationId(),
        staffProfileId: (string) Str::ulid(),
        courseId: (string) Str::ulid(),
        rrule: 'FREQ=WEEKLY;INTERVAL=1;BYDAY=MO,WE',
        studentUserIds: ['01TESTSTUDENTUSER00000000'],
        teacherUserId: '01TESTTEACHERUSER00000000',
        courseName: ['ar' => 'كورس', 'en' => 'Course'],
        courseCode: 'C-1',
        targetName: 'طالبة',
        teacherName: 'معلمة',
        durationMinutes: 25,
        sessionCount: 17,
        scheduleTimes: ['2026-09-30T17:00:00+00:00'],
        timezone: 'Europe/Istanbul',
        weeklyPattern: ['ar' => 'الاثنين 20:00 والأربعاء 20:00', 'en' => 'Mon 20:00 and Wed 20:00'],
    );

    expect($event->payload()['weekly_pattern'])->toBe([
        'ar' => 'الاثنين 20:00 والأربعاء 20:00',
        'en' => 'Mon 20:00 and Wed 20:00',
    ]);
});

it('shows the weekly pattern, not every generated date, on every channel and locale', function (string $channel, string $locale): void {
    $template = NotificationTemplate::query()
        ->whereNull('organization_id')
        ->where('event_key', 'schedule.created')
        ->where('locale', $locale)
        ->where('channel', $channel)
        ->first();

    expect($template)->not->toBeNull()
        ->and((string) $template->body)->toContain('{{weekly_pattern}}')
        ->and((string) $template->body)->not->toContain('{{schedule_times}}')
        ->and($template->parameters)->toContain('weekly_pattern')
        ->and($template->parameters)->not->toContain('schedule_times');
})->with([
    ['in_app', 'ar'], ['email', 'ar'], ['whatsapp', 'ar'],
    ['in_app', 'en'], ['email', 'en'], ['whatsapp', 'en'],
]);

it('delivers one short approval message when a recurring schedule is created', function (): void {
    CarbonImmutable::setTestNow('2026-10-10 08:00:00 UTC');
    $fixture = schedulingFixture();

    createOperationalSchedule($fixture, [
        'weekdays' => [1, 3],
        'start_time' => '14:00',
        'timezone' => 'UTC',
        'starts_on' => '2026-10-10',
        'ends_on' => '2026-12-10',
    ]);

    $rows = NotificationOutbox::query()
        ->where('event_name', 'schedule.created')
        ->where('channel', 'in_app')
        ->get();

    // الإشعار لم يسقط بسبب بارامتر ناقص، وأي مستلم يحمل نمطًا أسبوعيًا لا قائمة تواريخ.
    expect($rows)->not->toBeEmpty();

    foreach ($rows as $row) {
        $body = is_array($row->body) ? implode(' ', $row->body) : (string) $row->body;

        expect($body)->toContain('14:00')
            ->and(substr_count($body, '2026-'))->toBe(0)
            ->and($body)->not->toContain('UTC');
    }
});
