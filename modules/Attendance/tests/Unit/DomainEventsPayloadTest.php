<?php

declare(strict_types=1);

use Modules\Attendance\Domain\Events\AttendanceConfirmed;
use Modules\Attendance\Domain\Events\AttendanceOverridden;
use Modules\Attendance\Domain\Events\AttendanceRecorded;

/**
 * عقد الأحداث: أسماء بصيغة الماضي، موديول المالك صحيح،
 * والحمولة معرّفات وقيَم بدائية فقط.
 */
it('publishes primitive-only payloads with past-tense names', function (): void {
    $recorded = new AttendanceRecorded(
        attendanceId: '01A00000000000000000000001',
        sessionParticipantId: '01P00000000000000000000002',
        derivedStatus: 'present',
        attendedMinutes: 55,
    );

    expect($recorded->name())->toBe('attendance.recorded')
        ->and($recorded->module())->toBe('Attendance')
        ->and($recorded->payload())->toBe([
            'attendance_id' => '01A00000000000000000000001',
            'session_participant_id' => '01P00000000000000000000002',
            'derived_status' => 'present',
            'attended_minutes' => 55,
        ]);

    $confirmed = new AttendanceConfirmed(
        attendanceId: '01A00000000000000000000001',
        sessionParticipantId: '01P00000000000000000000002',
        status: 'present',
        confirmedBy: '01U00000000000000000000003',
    );

    expect($confirmed->name())->toBe('attendance.confirmed')
        ->and($confirmed->module())->toBe('Attendance')
        ->and($confirmed->payload()['status'])->toBe('present');

    $overridden = new AttendanceOverridden(
        attendanceId: '01A00000000000000000000001',
        sessionParticipantId: '01P00000000000000000000002',
        fromStatus: 'no_show',
        toStatus: 'excused',
        reason: 'عذر موثق',
    );

    expect($overridden->name())->toBe('attendance.overridden')
        ->and($overridden->payload()['from_status'])->toBe('no_show')
        ->and($overridden->payload()['to_status'])->toBe('excused')
        ->and($overridden->payload()['reason'])->toBe('عذر موثق');
});
