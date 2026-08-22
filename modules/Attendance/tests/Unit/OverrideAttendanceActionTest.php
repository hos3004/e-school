<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Attendance\Application\Actions\OverrideAttendanceAction;
use Modules\Attendance\Application\Actions\RecordAttendanceAction;
use Modules\Attendance\Domain\Enums\AttendanceStatus;
use Modules\Attendance\Domain\Events\AttendanceOverridden;
use Modules\Attendance\Domain\Models\Attendance;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('academic.attendance.thresholds', [
        'partial_min_percent' => 25,
        'present_min_percent' => 75,
        'left_early_before_minutes' => 10,
        'late_after_minutes' => 5,
    ]);
    config()->set('attendance.override.reason_min_chars', 5);
});

function createOverridableAttendance(): Attendance
{
    return app(RecordAttendanceAction::class)->execute(
        sessionParticipantId: (string) str()->ulid(),
        attendedMinutes: 0,
        sessionMinutes: 60,
    );
}

it('overrides the derived status with a documented reason and seals the record', function () {
    Event::fake([AttendanceOverridden::class]);

    $attendance = createOverridableAttendance();
    expect($attendance->status)->toBe(AttendanceStatus::NoShow);

    app(OverrideAttendanceAction::class)->execute(
        $attendance,
        AttendanceStatus::Excused,
        'عذر طبي موثق بمستشفى معتمد',
    );

    $attendance->refresh();

    expect($attendance->status)->toBe(AttendanceStatus::Excused)
        ->and($attendance->derived_status)->toBe(AttendanceStatus::NoShow)
        ->and($attendance->isConfirmed())->toBeTrue()
        ->and($attendance->override_reason)->toBe('عذر طبي موثق بمستشفى معتمد');

    Event::assertDispatched(
        AttendanceOverridden::class,
        fn (AttendanceOverridden $event): bool => $event->attendanceId === (string) $attendance->getKey()
            && $event->fromStatus === AttendanceStatus::NoShow->value
            && $event->toStatus === AttendanceStatus::Excused->value
            && $event->reason === 'عذر طبي موثق بمستشفى معتمد'
    );
});

it('rejects an override without a sufficient reason', function () {
    $attendance = createOverridableAttendance();

    app(OverrideAttendanceAction::class)->execute($attendance, AttendanceStatus::Excused, '   ');
})->throws(Shared\Support\BusinessRuleViolation::class);

it('rejects an override with a reason shorter than the configured minimum', function () {
    $attendance = createOverridableAttendance();

    // أقل من config('attendance.override.reason_min_chars') = 5
    app(OverrideAttendanceAction::class)->execute($attendance, AttendanceStatus::Excused, 'نعم');
})->throws(Shared\Support\BusinessRuleViolation::class);

it('rejects an override that does not change the status', function () {
    $attendance = createOverridableAttendance();

    app(OverrideAttendanceAction::class)->execute(
        $attendance,
        AttendanceStatus::NoShow,
        'لا تغيير فعلي في الحالة هنا',
    );
})->throws(Shared\Support\BusinessRuleViolation::class);
