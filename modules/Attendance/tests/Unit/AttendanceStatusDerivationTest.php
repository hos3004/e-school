<?php

declare(strict_types=1);

use Modules\Attendance\Domain\Enums\AttendanceStatus;

beforeEach(function (): void {
    config()->set('academic.attendance.thresholds', [
        'partial_min_percent' => 25,
        'present_min_percent' => 75,
        'left_early_before_minutes' => 10,
        'late_after_minutes' => 5,
    ]);
});

it('derives no_show when the student never joined', function () {
    expect(AttendanceStatus::deriveFromMinutes(
        attendedMinutes: 0,
        sessionMinutes: 60,
        joinedAfterMinutes: 0,
        leftBeforeMinutes: 0,
    ))->toBe(AttendanceStatus::NoShow);
});

it('derives absent when attendance falls below the partial threshold', function () {
    // 14 دقيقة من 60 = 23% < 25%
    expect(AttendanceStatus::deriveFromMinutes(
        attendedMinutes: 14,
        sessionMinutes: 60,
        joinedAfterMinutes: 0,
        leftBeforeMinutes: 46,
    ))->toBe(AttendanceStatus::Absent);
});

it('derives partial when attendance is below the present threshold', function () {
    // 30 دقيقة من 60 = 50% — بين 25% و75%
    expect(AttendanceStatus::deriveFromMinutes(
        attendedMinutes: 30,
        sessionMinutes: 60,
        joinedAfterMinutes: 0,
        leftBeforeMinutes: 30,
    ))->toBe(AttendanceStatus::Partial);
});

it('derives left_early when leaving before the allowed margin', function () {
    // حضر 85% لكن انصرف قبل النهاية بعشر دقائق أو أكثر
    expect(AttendanceStatus::deriveFromMinutes(
        attendedMinutes: 52,
        sessionMinutes: 60,
        joinedAfterMinutes: 0,
        leftBeforeMinutes: 10,
    ))->toBe(AttendanceStatus::LeftEarly);
});

it('derives late when joining after the allowed margin', function () {
    expect(AttendanceStatus::deriveFromMinutes(
        attendedMinutes: 58,
        sessionMinutes: 60,
        joinedAfterMinutes: 8,
        leftBeforeMinutes: 0,
    ))->toBe(AttendanceStatus::Late);
});

it('derives present for a full timely attendance', function () {
    expect(AttendanceStatus::deriveFromMinutes(
        attendedMinutes: 60,
        sessionMinutes: 60,
        joinedAfterMinutes: 2,
        leftBeforeMinutes: 0,
    ))->toBe(AttendanceStatus::Present);
});

it('classifies violation statuses correctly', function () {
    expect(AttendanceStatus::Absent->isViolation())->toBeTrue()
        ->and(AttendanceStatus::NoShow->isViolation())->toBeTrue()
        ->and(AttendanceStatus::Present->isViolation())->toBeFalse()
        ->and(AttendanceStatus::Excused->isViolation())->toBeFalse();
});
