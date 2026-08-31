<?php

declare(strict_types=1);

namespace Modules\Reporting\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Mockery;
use Modules\AcademicReports\Domain\Contracts\SessionReportStatusQueries;
use Modules\AcademicReports\Domain\ValueObjects\SessionReportStatusData;
use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Academics\Domain\ValueObjects\AcademicCatalogItemData;
use Modules\Attendance\Domain\Contracts\AttendanceAdministrationQueries;
use Modules\Attendance\Domain\ValueObjects\AttendanceAdministrationData;
use Modules\Groups\Domain\Contracts\GroupAdministrationQueries;
use Modules\Groups\Domain\ValueObjects\SchedulingGroupData;
use Modules\Reporting\Application\Queries\OperationalReportQueryService;
use Modules\Reporting\Application\Services\OperationalReportCriteriaFactory;
use Modules\Reporting\Domain\Contracts\OperationalReportQuery;
use Modules\Reporting\Domain\Contracts\ReportPdfRenderer;
use Modules\Reporting\Domain\ValueObjects\OperationalReportCriteria;
use Modules\Reporting\Domain\ValueObjects\OperationalReportData;
use Modules\Reporting\Tests\Support\ApiUser;
use Modules\Sessions\Domain\Contracts\SessionAdministrationQueries;
use Modules\Sessions\Domain\Contracts\SessionParticipantAdministrationQueries;
use Modules\Sessions\Domain\ValueObjects\SessionAdministrationData;
use Modules\Sessions\Domain\ValueObjects\SessionParticipantAdministrationData;
use Modules\Staff\Domain\Contracts\StaffQueries;
use Modules\Students\Domain\Contracts\StudentDirectoryQueries;

it('builds one complete row and summary through batched module contracts', function (): void {
    app()->setLocale('en');
    config(['reporting.operational.max_rows' => 100]);

    $session = new SessionAdministrationData(
        id: 'session-1',
        organizationId: 'org-1',
        groupId: 'group-1',
        courseId: 'course-1',
        staffProfileId: 'teacher-actual',
        status: 'completed',
        title: ['ar' => 'الجبر', 'en' => 'Algebra'],
        scheduledStart: '2026-08-31T08:00:00+00:00',
        scheduledEnd: '2026-08-31T09:00:00+00:00',
        originalStaffProfileId: 'teacher-original',
        sessionType: 'regular',
        actualStart: '2026-08-31T08:05:00+00:00',
        actualEnd: '2026-08-31T08:55:00+00:00',
    );
    $participant = new SessionParticipantAdministrationData(
        id: 'participant-1',
        organizationId: 'org-1',
        sessionId: 'session-1',
        studentProfileId: 'student-1',
        enrollmentId: 'enrollment-1',
        courseId: 'course-1',
        groupId: 'group-1',
        staffProfileId: 'teacher-actual',
        sessionTitle: ['en' => 'Algebra'],
        sessionStatus: 'completed',
        scheduledStart: $session->scheduledStart,
        scheduledEnd: $session->scheduledEnd,
        firstJoinedAt: '2026-08-31T08:05:00+00:00',
        lastLeftAt: '2026-08-31T08:55:00+00:00',
        attendedMinutes: 50,
        invitationActive: true,
    );

    $sessions = Mockery::mock(SessionAdministrationQueries::class);
    $sessions->shouldReceive('forReport')->once()->andReturn([$session]);
    $participants = Mockery::mock(SessionParticipantAdministrationQueries::class);
    $participants->shouldReceive('forSessions')->once()->andReturn(['session-1' => [$participant]]);
    $attendances = Mockery::mock(AttendanceAdministrationQueries::class);
    $attendances->shouldReceive('byParticipantIds')->once()->andReturn([
        'participant-1' => new AttendanceAdministrationData(
            id: 'attendance-1',
            sessionParticipantId: 'participant-1',
            status: 'present',
            derivedStatus: 'present',
            attendedMinutes: 50,
            joinedAfterMinutes: 5,
            leftBeforeMinutes: 5,
            confirmedBy: 'teacher-actual',
            confirmedAt: '2026-08-31T09:00:00+00:00',
            overrideReason: null,
        ),
    ]);
    $students = Mockery::mock(StudentDirectoryQueries::class);
    $students->shouldReceive('namesForProfiles')->once()->andReturn(['student-1' => 'Mona Ali']);
    $staff = Mockery::mock(StaffQueries::class);
    $staff->shouldReceive('namesForProfiles')->once()->andReturn([
        'teacher-actual' => 'Sara Hassan',
        'teacher-original' => 'Omar Adel',
    ]);
    $groups = Mockery::mock(GroupAdministrationQueries::class);
    $groups->shouldReceive('groupsByIds')->once()->andReturn([
        'group-1' => new SchedulingGroupData(
            id: 'group-1',
            code: 'G-1',
            name: ['en' => 'First group'],
            status: 'active',
            timezone: 'UTC',
            startsOn: null,
            endsOn: null,
            programIds: [],
            teacherAssignments: [],
        ),
    ]);
    $academics = Mockery::mock(AcademicCatalogQueries::class);
    $academics->shouldReceive('coursesByIds')->once()->andReturn([
        'course-1' => new AcademicCatalogItemData('course-1', 'MATH-1', ['en' => 'Mathematics']),
    ]);
    $sessionReports = Mockery::mock(SessionReportStatusQueries::class);
    $sessionReports->shouldReceive('forSessions')->once()->andReturn([
        'session-1' => new SessionReportStatusData('session-1', '2026-08-31T10:00:00+00:00', true),
    ]);

    $criteria = new OperationalReportCriteria(
        organizationId: 'org-1',
        fromUtc: CarbonImmutable::parse('2026-08-31T00:00:00+00:00'),
        untilUtcExclusive: CarbonImmutable::parse('2026-09-01T00:00:00+00:00'),
        timezone: 'UTC',
        preset: 'custom',
        fromDate: '2026-08-31',
        untilDate: '2026-08-31',
        statuses: ['completed'],
        attendanceStatuses: ['present'],
        sessionTypes: ['regular'],
        studentProfileId: 'student-1',
        staffProfileId: 'teacher-actual',
        groupId: 'group-1',
        courseId: 'course-1',
        originalStaffProfileId: 'teacher-original',
        reportStatus: 'late',
        search: 'algebra',
    );

    $report = (new OperationalReportQueryService(
        $sessions,
        $participants,
        $attendances,
        $students,
        $staff,
        $groups,
        $academics,
        $sessionReports,
    ))->run($criteria);

    expect($report->rows)->toHaveCount(1)
        ->and($report->rows[0]->title)->toBe('Algebra')
        ->and($report->rows[0]->studentsDisplay)->toContain('Mona Ali')
        ->and($report->rows[0]->hasSubstitute)->toBeTrue()
        ->and($report->rows[0]->reportStatus)->toBe('late')
        ->and($report->summary['total'])->toBe(1)
        ->and($report->summary['completed'])->toBe(1)
        ->and($report->summary['present'])->toBe(1)
        ->and($report->summary['attendance_rate'])->toBe(100.0)
        ->and($report->summary['scheduled_minutes'])->toBe(60)
        ->and($report->summary['actual_minutes'])->toBe(50)
        ->and($report->summary['reports_late'])->toBe(1);
});

it('converts an inclusive local date range to a half-open UTC period across DST', function (): void {
    Gate::define('student.view.any', fn (): bool => true);
    Gate::define('staff.view.any', fn (): bool => true);

    $staff = Mockery::mock(StaffQueries::class);
    $criteria = (new OperationalReportCriteriaFactory($staff))->fromInput([
        'preset' => 'custom',
        'from' => '2026-03-29',
        'until' => '2026-03-29',
    ], new ApiUser('actor-1', 'org-1', 'Europe/Berlin'));

    expect($criteria->fromUtc->toIso8601String())->toBe('2026-03-28T23:00:00+00:00')
        ->and($criteria->untilUtcExclusive->toIso8601String())->toBe('2026-03-29T22:00:00+00:00')
        ->and($criteria->fromDate)->toBe('2026-03-29')
        ->and($criteria->untilDate)->toBe('2026-03-29');
});

it('applies derived filters across every source page before enforcing the result limit', function (): void {
    app()->setLocale('en');
    config([
        'reporting.operational.max_rows' => 1,
        'reporting.operational.scan_page_size' => 2,
    ]);

    $makeSession = static fn (string $id, string $title, string $start): SessionAdministrationData => new SessionAdministrationData(
        id: $id,
        organizationId: 'org-1',
        groupId: 'group-1',
        courseId: 'course-1',
        staffProfileId: 'teacher-1',
        status: 'completed',
        title: ['en' => $title],
        scheduledStart: $start,
        scheduledEnd: CarbonImmutable::parse($start)->addHour()->toIso8601String(),
    );
    $first = $makeSession('session-1', 'Grammar', '2026-08-31T08:00:00+00:00');
    $second = $makeSession('session-2', 'Reading', '2026-08-31T09:00:00+00:00');
    $matching = $makeSession('session-3', 'Target algebra', '2026-08-31T10:00:00+00:00');

    $sessions = Mockery::mock(SessionAdministrationQueries::class);
    $sessions->shouldReceive('forReport')->twice()->andReturn([$first, $second], [$matching]);
    $participants = Mockery::mock(SessionParticipantAdministrationQueries::class);
    $participants->shouldReceive('forSessions')->twice()->andReturn([], []);
    $attendances = Mockery::mock(AttendanceAdministrationQueries::class);
    $attendances->shouldReceive('byParticipantIds')->twice()->with('org-1', [])->andReturn([]);
    $students = Mockery::mock(StudentDirectoryQueries::class);
    $students->shouldReceive('namesForProfiles')->twice()->with('org-1', [])->andReturn([]);
    $staff = Mockery::mock(StaffQueries::class);
    $staff->shouldReceive('namesForProfiles')->twice()->andReturn(['teacher-1' => 'Teacher One']);
    $groups = Mockery::mock(GroupAdministrationQueries::class);
    $groups->shouldReceive('groupsByIds')->twice()->andReturn([
        'group-1' => new SchedulingGroupData(
            id: 'group-1',
            code: 'G-1',
            name: ['en' => 'First group'],
            status: 'active',
            timezone: 'UTC',
            startsOn: null,
            endsOn: null,
            programIds: [],
            teacherAssignments: [],
        ),
    ]);
    $academics = Mockery::mock(AcademicCatalogQueries::class);
    $academics->shouldReceive('coursesByIds')->twice()->andReturn([
        'course-1' => new AcademicCatalogItemData('course-1', 'MATH-1', ['en' => 'Mathematics']),
    ]);
    $sessionReports = Mockery::mock(SessionReportStatusQueries::class);
    $sessionReports->shouldReceive('forSessions')->twice()->andReturn([], []);

    $criteria = new OperationalReportCriteria(
        organizationId: 'org-1',
        fromUtc: CarbonImmutable::parse('2026-08-31T00:00:00+00:00'),
        untilUtcExclusive: CarbonImmutable::parse('2026-09-01T00:00:00+00:00'),
        timezone: 'UTC',
        preset: 'custom',
        fromDate: '2026-08-31',
        untilDate: '2026-08-31',
        search: 'target',
    );

    $report = (new OperationalReportQueryService(
        $sessions,
        $participants,
        $attendances,
        $students,
        $staff,
        $groups,
        $academics,
        $sessionReports,
    ))->run($criteria);

    expect($report->rows)->toHaveCount(1)
        ->and($report->rows[0]->id)->toBe('session-3')
        ->and($report->limitExceeded)->toBeFalse();
});

it('exports the filtered report as non-cacheable PDF bytes', function (): void {
    Gate::define('report.export', fn (): bool => true);
    Gate::define('student.view.any', fn (): bool => true);
    Gate::define('staff.view.any', fn (): bool => true);
    $selectedStudentId = (string) str()->ulid();

    $reports = Mockery::mock(OperationalReportQuery::class);
    $reports->shouldReceive('run')->once()->andReturnUsing(
        static fn (OperationalReportCriteria $criteria): OperationalReportData => new OperationalReportData(
            criteria: $criteria,
            rows: [],
            summary: ['total' => 0],
        ),
    );
    $reports->shouldNotReceive('options');
    $reports->shouldReceive('selectedOptions')->once()->andReturn([
        'students' => [$selectedStudentId => 'Mona Ali'],
        'teachers' => [],
        'groups' => [],
        'courses' => [],
    ]);
    $renderer = Mockery::mock(ReportPdfRenderer::class);
    $renderer->shouldReceive('render')
        ->once()
        ->with(Mockery::on(static fn (string $html): bool => str_contains($html, (string) __('reporting::operational.title'))
            && str_contains($html, 'Mona Ali')
            && !str_contains($html, 'summary.total')))
        ->andReturn("%PDF-1.7\nverified-report");

    $this->app->instance(OperationalReportQuery::class, $reports);
    $this->app->instance(ReportPdfRenderer::class, $renderer);

    $response = $this->actingAs(new ApiUser('actor-1', 'org-1', 'Europe/Istanbul'))
        ->get('/reporting/operational-reports/export.pdf?preset=today&student_profile_id='.$selectedStudentId);

    $response->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('x-content-type-options', 'nosniff');

    expect($response->getContent())->toStartWith('%PDF-1.7')
        ->and((string) $response->headers->get('content-disposition'))->toContain('session-report-');
});
