<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\Notifications\Application\Services\NotificationRecipientSilencer;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Modules\Notifications\Database\Seeders\NotificationTemplateSeeder;
use Modules\Scheduling\Domain\Events\ScheduleTimesChanged;
use Modules\Scheduling\Presentation\Filament\Resources\ScheduleResource\Pages\CreateSchedule;
use Shared\Testing\Fixtures;

/**
 * «أحيانًا أعدّل ولا أحتاج إرسال رسالة» — مربعا اختيار عند نقطة التعديل
 * يوقفان طرفًا بعينه لهذه العملية وحدها، دون مسّ الإعداد العام. 18 سبتمبر 2026.
 */
function notifyToggleEvent(string $organizationId, string $studentUserId, string $teacherUserId): ScheduleTimesChanged
{
    return new ScheduleTimesChanged(
        scheduleId: (string) Str::ulid(),
        organizationId: $organizationId,
        staffProfileId: (string) Str::ulid(),
        courseId: (string) Str::ulid(),
        rrule: 'FREQ=WEEKLY;INTERVAL=1;BYDAY=MO',
        studentUserIds: [$studentUserId],
        teacherUserId: $teacherUserId,
        courseName: ['ar' => 'كورس', 'en' => 'Course'],
        courseCode: 'C-1',
        targetName: 'طالب',
        teacherName: 'معلم',
        durationMinutes: 30,
        sessionCount: 1,
        scheduleTimes: [],
        timezone: 'Europe/Istanbul',
        weeklyPattern: ['ar' => 'الاثنين الساعة 18:00'],
        effectiveFrom: now('UTC')->toIso8601String(),
    );
}

it('suppresses only the teacher notification when the teacher is silenced', function (): void {
    $organizationId = Fixtures::organizationId();
    $studentUserId = Fixtures::userId();
    $teacherUserId = Fixtures::userId();

    app(NotificationRecipientSilencer::class)->silence('teacher');
    event(notifyToggleEvent($organizationId, $studentUserId, $teacherUserId));

    expect(NotificationOutbox::query()->where('event_name', 'schedule.times_changed')->where('user_id', $teacherUserId)->exists())->toBeFalse()
        ->and(NotificationOutbox::query()->where('event_name', 'schedule.times_changed')->where('user_id', $studentUserId)->exists())->toBeTrue();
});

it('suppresses only the student notification when the student is silenced', function (): void {
    $organizationId = Fixtures::organizationId();
    $studentUserId = Fixtures::userId();
    $teacherUserId = Fixtures::userId();

    app(NotificationRecipientSilencer::class)->silence('student');
    event(notifyToggleEvent($organizationId, $studentUserId, $teacherUserId));

    expect(NotificationOutbox::query()->where('event_name', 'schedule.times_changed')->where('user_id', $studentUserId)->exists())->toBeFalse()
        ->and(NotificationOutbox::query()->where('event_name', 'schedule.times_changed')->where('user_id', $teacherUserId)->exists())->toBeTrue();
});

it('notifies both parties as usual when nothing is silenced', function (): void {
    $organizationId = Fixtures::organizationId();
    $studentUserId = Fixtures::userId();
    $teacherUserId = Fixtures::userId();

    event(notifyToggleEvent($organizationId, $studentUserId, $teacherUserId));

    expect(NotificationOutbox::query()->where('event_name', 'schedule.times_changed')->where('user_id', $studentUserId)->exists())->toBeTrue()
        ->and(NotificationOutbox::query()->where('event_name', 'schedule.times_changed')->where('user_id', $teacherUserId)->exists())->toBeTrue();
});

it('never silences a role no admin asked to silence, guarding against an inverted default', function (): void {
    $silencer = app(NotificationRecipientSilencer::class);

    expect($silencer->isAudienceSilenced('student'))->toBeFalse()
        ->and($silencer->isAudienceSilenced('teacher'))->toBeFalse()
        ->and($silencer->isAudienceSilenced('admin'))->toBeFalse();
});

it('bundles the guardian audience under the student toggle, and never silences admin/supervisor', function (): void {
    $silencer = app(NotificationRecipientSilencer::class);
    $silencer->silence('student');

    expect($silencer->isAudienceSilenced('guardian'))->toBeTrue()
        ->and($silencer->isRecipientFieldSilenced('guardian_user_ids'))->toBeTrue()
        ->and($silencer->isAudienceSilenced('admin'))->toBeFalse()
        ->and($silencer->isAudienceSilenced('supervisor'))->toBeFalse();
});

it('creates a schedule without notifying the teacher when the admin turns that toggle off', function (): void {
    CarbonImmutable::setTestNow('2026-10-10 08:00:00 UTC');
    Gate::before(static fn (): bool => true);
    Filament::setCurrentPanel('admin');
    $fixture = schedulingFixture();
    $individualCourse = Modules\Academics\Domain\Models\Course::factory()->create([
        'organization_id' => $fixture['organization']->id,
        'level_id' => $fixture['level']->id,
        'session_mode' => Modules\Academics\Domain\Enums\SessionMode::Individual,
        'name' => ['ar' => 'القرآن الفردي', 'en' => 'Individual Quran'],
    ]);
    DB::table('teacher_courses')->insert([
        'id' => (string) Str::ulid(),
        'staff_profile_id' => $fixture['teacher']->id,
        'course_id' => $individualCourse->id,
        'qualified_at' => now('UTC'),
        'qualified_by' => $fixture['operator']->id,
        'created_at' => now('UTC'),
        'updated_at' => now('UTC'),
    ]);
    Modules\Staff\Domain\Models\TeacherAvailability::query()->create([
        'staff_profile_id' => $fixture['teacher']->id,
        'weekday' => 0,
        'start_time' => '09:00',
        'end_time' => '12:00',
        'timezone' => 'UTC',
        'effective_from' => '2026-10-01',
        'effective_to' => '2026-12-31',
        'approval_status' => Modules\Staff\Domain\Enums\TeacherAvailabilityApprovalStatus::Approved,
    ]);
    $this->actingAs($fixture['operator']);
    $this->seed(NotificationTemplateSeeder::class);

    Livewire::test(CreateSchedule::class)
        ->fillForm([
            'target_type' => 'student',
            'course_id' => (string) $individualCourse->id,
            'student_profile_id' => (string) $fixture['student']->id,
            'staff_profile_id' => (string) $fixture['teacher']->id,
            'weekly_slots' => [
                ['weekday' => 0, 'start_time' => '10:00'],
            ],
            'interval_weeks' => 1,
            'duration_minutes' => 35,
            'timezone' => 'UTC',
            'starts_on' => '2026-10-11',
            'ends_on' => '2026-10-11',
            'notify_teacher' => false,
            'reason' => 'اختبار إيقاف إشعار المعلم عند الإنشاء',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $teacherUserId = (string) DB::table('staff_profiles')->where('id', $fixture['teacher']->id)->value('user_id');
    $studentUserId = (string) DB::table('student_profiles')->where('id', $fixture['student']->id)->value('user_id');

    expect(NotificationOutbox::query()->where('category', 'schedule_summary')->where('user_id', $teacherUserId)->exists())->toBeFalse()
        ->and(NotificationOutbox::query()->where('category', 'schedule_summary')->where('user_id', $studentUserId)->exists())->toBeTrue();
});
