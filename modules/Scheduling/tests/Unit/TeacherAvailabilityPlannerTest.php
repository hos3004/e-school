<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Mockery\MockInterface;
use Modules\Scheduling\Application\Services\TeacherAvailabilityPlanner;
use Modules\Sessions\Domain\Contracts\SessionSchedulingQueries;
use Modules\Sessions\Domain\ValueObjects\SessionSchedulingData;
use Modules\Staff\Domain\Contracts\StaffAdministrationQueries;
use Modules\Staff\Domain\ValueObjects\TeacherAvailabilityData;

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('offers approved teacher slots and removes times overlapping an existing booking', function (): void {
    CarbonImmutable::setTestNow('2026-10-10 08:00:00 UTC');
    $availability = new TeacherAvailabilityData(
        id: 'availability-1',
        weekday: 0,
        startTime: '09:00:00',
        endTime: '12:00:00',
        timezone: 'UTC',
        approvalStatus: 'approved',
        decisionReason: null,
        effectiveFrom: '2026-10-01',
        effectiveTo: null,
    );
    $booking = new SessionSchedulingData(
        id: 'session-1',
        organizationId: 'organization-1',
        scheduleId: 'schedule-1',
        groupId: null,
        courseId: 'course-1',
        staffProfileId: 'teacher-1',
        sessionType: 'individual',
        status: 'scheduled',
        scheduledStart: CarbonImmutable::parse('2026-10-11 10:00:00 UTC'),
        scheduledEnd: CarbonImmutable::parse('2026-10-11 10:55:00 UTC'),
        title: ['ar' => 'حصة محجوزة'],
        studentProfileIds: ['student-1'],
    );

    $staff = Mockery::mock(StaffAdministrationQueries::class, function (MockInterface $mock) use ($availability): void {
        $mock->shouldReceive('availabilityForTeacher')->once()->andReturn([$availability]);
    });
    $sessions = Mockery::mock(SessionSchedulingQueries::class, function (MockInterface $mock) use ($booking): void {
        $mock->shouldReceive('bookingsForTeacher')->once()->andReturn([$booking]);
    });

    $overview = (new TeacherAvailabilityPlanner($staff, $sessions))->overview(
        organizationId: 'organization-1',
        staffProfileId: 'teacher-1',
        weekdays: [0],
        intervalWeeks: 1,
        durationMinutes: 55,
        timezone: 'UTC',
        startsOn: '2026-10-11',
        endsOn: '2026-10-11',
        selectedStartTime: '09:00',
    );

    expect($overview['available_start_times'])
        ->toContain('09:00', '10:55')
        ->not->toContain('09:10', '10:00')
        ->and($overview['booked_sessions'])->toHaveCount(1)
        ->and($overview['total_occurrences'])->toBe(1);
});

it('returns no individual slots when the teacher has no approved availability', function (): void {
    CarbonImmutable::setTestNow('2026-10-10 08:00:00 UTC');
    $staff = Mockery::mock(StaffAdministrationQueries::class);
    $staff->shouldReceive('availabilityForTeacher')->once()->andReturn([]);
    $sessions = Mockery::mock(SessionSchedulingQueries::class);
    $sessions->shouldReceive('bookingsForTeacher')->once()->andReturn([]);

    $overview = (new TeacherAvailabilityPlanner($staff, $sessions))->overview(
        organizationId: 'organization-1',
        staffProfileId: 'teacher-1',
        weekdays: [0],
        intervalWeeks: 1,
        durationMinutes: 25,
        timezone: 'UTC',
        startsOn: '2026-10-11',
        endsOn: '2026-10-11',
    );

    expect($overview['available_start_times'])->toBe([])
        ->and($overview['has_declared_availability'])->toBeFalse();
});
