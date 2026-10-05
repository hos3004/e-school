<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Modules\Academics\Domain\Enums\SessionMode;
use Modules\Academics\Domain\Models\Course;
use Modules\Identity\Domain\Models\User;
use Modules\Scheduling\Application\Actions\CreateScheduleAction;
use Modules\Scheduling\Application\Services\ConsoleIndividualTeacherService;
use Modules\Scheduling\Domain\Models\Schedule;
use Modules\Staff\Domain\Enums\EmploymentType;
use Modules\Staff\Domain\Enums\StaffGender;
use Modules\Staff\Domain\Enums\TeacherAvailabilityApprovalStatus;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Staff\Domain\Models\TeacherAvailability;

beforeEach(function (): void {
    Gate::before(static fn (): bool => true);
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

/** @return array<string, object> */
function individualFlexFixture(): array
{
    $fixture = schedulingFixture();
    $course = Course::factory()->create([
        'organization_id' => $fixture['organization']->id,
        'level_id' => $fixture['level']->id,
        'session_mode' => SessionMode::Individual,
        'name' => ['ar' => 'كورس فردي', 'en' => 'Individual course'],
    ]);
    DB::table('teacher_courses')->insert([
        'id' => (string) Str::ulid(),
        'staff_profile_id' => $fixture['teacher']->id,
        'course_id' => $course->id,
        'qualified_at' => now('UTC'),
        'qualified_by' => $fixture['operator']->id,
        'created_at' => now('UTC'),
        'updated_at' => now('UTC'),
    ]);
    TeacherAvailability::query()->create([
        'staff_profile_id' => $fixture['teacher']->id,
        'weekday' => 0,
        'start_time' => '09:00',
        'end_time' => '12:00',
        'timezone' => 'UTC',
        'effective_from' => '2026-10-01',
        'effective_to' => '2026-12-31',
        'approval_status' => TeacherAvailabilityApprovalStatus::Approved,
    ]);

    $fixture['individualCourse'] = $course;

    return $fixture;
}

it('creates an individual schedule with flexible start enabled when asked', function (): void {
    CarbonImmutable::setTestNow('2026-10-10 08:00:00 UTC');
    $fixture = individualFlexFixture();
    $this->actingAs($fixture['operator']);

    $scheduleId = app(ConsoleIndividualTeacherService::class)->assignTeacher(
        organizationId: (string) $fixture['organization']->id,
        studentProfileId: (string) $fixture['student']->id,
        courseId: (string) $fixture['individualCourse']->id,
        staffProfileId: (string) $fixture['teacher']->id,
        weeklySlots: [['weekday' => 0, 'start_time' => '10:00']],
        durationMinutes: 35,
        intervalWeeks: 1,
        timezone: 'UTC',
        startsOn: '2026-10-11',
        actorId: (string) $fixture['operator']->id,
        reason: 'اختبار البدء المرن',
        flexibleStart: true,
    );

    expect(Schedule::query()->findOrFail($scheduleId)->flexible_start)->toBeTrue();
});

it('defaults a new individual schedule to non-flexible when not asked', function (): void {
    CarbonImmutable::setTestNow('2026-10-10 08:00:00 UTC');
    $fixture = individualFlexFixture();
    $this->actingAs($fixture['operator']);

    $scheduleId = app(ConsoleIndividualTeacherService::class)->assignTeacher(
        organizationId: (string) $fixture['organization']->id,
        studentProfileId: (string) $fixture['student']->id,
        courseId: (string) $fixture['individualCourse']->id,
        staffProfileId: (string) $fixture['teacher']->id,
        weeklySlots: [['weekday' => 0, 'start_time' => '10:00']],
        durationMinutes: 35,
        intervalWeeks: 1,
        timezone: 'UTC',
        startsOn: '2026-10-11',
        actorId: (string) $fixture['operator']->id,
        reason: 'اختبار الوضع الافتراضي',
    );

    expect(Schedule::query()->findOrFail($scheduleId)->flexible_start)->toBeFalse();
});

it('lets an admin toggle flexible start on an existing individual schedule without touching the teacher or slots', function (): void {
    CarbonImmutable::setTestNow('2026-10-10 08:00:00 UTC');
    $fixture = individualFlexFixture();
    $this->actingAs($fixture['operator']);
    $service = app(ConsoleIndividualTeacherService::class);

    $scheduleId = $service->assignTeacher(
        organizationId: (string) $fixture['organization']->id,
        studentProfileId: (string) $fixture['student']->id,
        courseId: (string) $fixture['individualCourse']->id,
        staffProfileId: (string) $fixture['teacher']->id,
        weeklySlots: [['weekday' => 0, 'start_time' => '10:00']],
        durationMinutes: 35,
        intervalWeeks: 1,
        timezone: 'UTC',
        startsOn: '2026-10-11',
        actorId: (string) $fixture['operator']->id,
        reason: 'إسناد أولي',
    );

    $result = $service->setFlexibleStart(
        organizationId: (string) $fixture['organization']->id,
        studentProfileId: (string) $fixture['student']->id,
        scheduleId: $scheduleId,
        flexibleStart: true,
        actorId: (string) $fixture['operator']->id,
        reason: 'يحتاج الطالب مرونة بالتراضي مع المعلم',
    );

    $schedule = Schedule::query()->findOrFail($scheduleId);

    expect($result['flexible_start'])->toBeTrue()
        ->and($schedule->flexible_start)->toBeTrue()
        ->and((string) $schedule->staff_profile_id)->toBe((string) $fixture['teacher']->id)
        ->and($schedule->weeklySlots()->count())->toBe(1);
});

it('keeps the flexible start choice when only the teacher changes', function (): void {
    CarbonImmutable::setTestNow('2026-10-10 08:00:00 UTC');
    $fixture = individualFlexFixture();
    $this->actingAs($fixture['operator']);
    $service = app(ConsoleIndividualTeacherService::class);

    $secondTeacherUser = User::factory()
        ->inOrganization((string) $fixture['organization']->id)
        ->create(['name' => 'معلم آخر']);
    $secondTeacher = StaffProfile::query()->create([
        'organization_id' => $fixture['organization']->id,
        'user_id' => $secondTeacherUser->id,
        'staff_code' => 'T-SCHEDULE-2',
        'employment_type' => EmploymentType::Contractor,
        'gender' => StaffGender::Male,
        'hired_at' => '2026-01-01',
    ]);
    DB::table('teacher_courses')->insert([
        'id' => (string) Str::ulid(),
        'staff_profile_id' => $secondTeacher->id,
        'course_id' => $fixture['individualCourse']->id,
        'qualified_at' => now('UTC'),
        'qualified_by' => $fixture['operator']->id,
        'created_at' => now('UTC'),
        'updated_at' => now('UTC'),
    ]);
    TeacherAvailability::query()->create([
        'staff_profile_id' => $secondTeacher->id,
        'weekday' => 0,
        'start_time' => '09:00',
        'end_time' => '12:00',
        'timezone' => 'UTC',
        'effective_from' => '2026-10-01',
        'effective_to' => '2026-12-31',
        'approval_status' => TeacherAvailabilityApprovalStatus::Approved,
    ]);

    $scheduleId = $service->assignTeacher(
        organizationId: (string) $fixture['organization']->id,
        studentProfileId: (string) $fixture['student']->id,
        courseId: (string) $fixture['individualCourse']->id,
        staffProfileId: (string) $fixture['teacher']->id,
        weeklySlots: [['weekday' => 0, 'start_time' => '10:00']],
        durationMinutes: 35,
        intervalWeeks: 1,
        timezone: 'UTC',
        startsOn: '2026-10-11',
        actorId: (string) $fixture['operator']->id,
        reason: 'إسناد أولي',
        flexibleStart: true,
    );

    $service->changeTeacher(
        organizationId: (string) $fixture['organization']->id,
        studentProfileId: (string) $fixture['student']->id,
        scheduleId: $scheduleId,
        staffProfileId: (string) $secondTeacher->id,
        actorId: (string) $fixture['operator']->id,
        reason: 'تغيير المعلم',
    );

    $schedule = Schedule::query()->findOrFail($scheduleId);

    expect($schedule->flexible_start)->toBeTrue()
        ->and((string) $schedule->staff_profile_id)->toBe((string) $secondTeacher->id);
});

it('never lets a group schedule carry flexible start regardless of the input', function (): void {
    CarbonImmutable::setTestNow('2026-10-10 08:00:00 UTC');
    $fixture = schedulingFixture();

    $schedule = app(CreateScheduleAction::class)->execute(
        (string) $fixture['organization']->id,
        [
            'target_type' => 'group',
            'group_id' => (string) $fixture['group']->id,
            'course_id' => (string) $fixture['course']->id,
            'staff_profile_id' => (string) $fixture['teacher']->id,
            'weekdays' => [0],
            'interval_weeks' => 1,
            'start_time' => '10:00',
            'duration_minutes' => 60,
            'timezone' => 'UTC',
            'starts_on' => '2026-10-11',
            'flexible_start' => true,
        ],
        (string) $fixture['operator']->id,
        'اختبار الحصة الجماعية',
    );

    expect($schedule->flexible_start)->toBeFalse();
});
