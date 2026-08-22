<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\AcademicReports\Application\Actions\SubmitSessionReportAction;
use Modules\AcademicReports\Database\Factories\SessionReportFactory;
use Modules\AcademicReports\Domain\Events\SessionReportSubmitted;
use Modules\AcademicReports\Domain\Models\SessionReport;
use Modules\AcademicReports\Domain\Models\SessionReportStudent;
use Shared\Support\BusinessRuleViolation;
use Shared\Testing\Fixtures;

uses(RefreshDatabase::class);

function submitReportPayload(): array
{
    return [
        [
            'student_profile_id' => Fixtures::studentProfileId(),
            'participation' => 4,
            'performance' => 3,
            'commitment' => 5,
            'strengths' => 'مشاركة جيدة',
            'weaknesses' => null,
            'note' => null,
        ],
    ];
}

it('submits a session report with student evaluations and publishes the event', function (): void {
    Event::fake([SessionReportSubmitted::class]);

    $context = SessionReportFactory::createSessionContext();
    $sessionId = $context['session_id'];
    $staffProfileId = $context['staff_profile_id'];

    $report = app(SubmitSessionReportAction::class)->execute(
        sessionId: $sessionId,
        staffProfileId: $staffProfileId,
        students: submitReportPayload(),
        submittedAt: CarbonImmutable::now('UTC'),
        topicsCovered: 'المواضيع',
    );

    expect($report->session_id)->toBe($sessionId)
        ->and($report->is_late)->toBeFalse()
        ->and($report->students()->count())->toBe(1)
        ->and(SessionReport::query()->where('session_id', $sessionId)->count())->toBe(1);

    Event::assertDispatched(
        SessionReportSubmitted::class,
        fn (SessionReportSubmitted $event): bool => $event->sessionId === $sessionId
            && $event->staffProfileId === $staffProfileId
            && $event->isLate === false
            && $event->studentCount === 1,
    );
});

it('marks the report late when submitted after the configured sla window', function (): void {
    config()->set('academic.session_report.sla_hours', 24);

    $endedAt = CarbonImmutable::now('UTC')->subHours(30);
    $context = SessionReportFactory::createSessionContext();

    $report = app(SubmitSessionReportAction::class)->execute(
        sessionId: $context['session_id'],
        staffProfileId: $context['staff_profile_id'],
        students: submitReportPayload(),
        submittedAt: CarbonImmutable::now('UTC'),
        sessionEndedAt: $endedAt,
    );

    expect($report->is_late)->toBeTrue();
});

it('marks the report on time when submitted within the configured sla window', function (): void {
    config()->set('academic.session_report.sla_hours', 24);

    $endedAt = CarbonImmutable::now('UTC')->subHours(10);
    $context = SessionReportFactory::createSessionContext();

    $report = app(SubmitSessionReportAction::class)->execute(
        sessionId: $context['session_id'],
        staffProfileId: $context['staff_profile_id'],
        students: submitReportPayload(),
        submittedAt: CarbonImmutable::now('UTC'),
        sessionEndedAt: $endedAt,
    );

    expect($report->is_late)->toBeFalse();
});

it('rejects a second report for the same session', function (): void {
    $context = SessionReportFactory::createSessionContext();
    $action = app(SubmitSessionReportAction::class);

    $action->execute($context['session_id'], $context['staff_profile_id'], submitReportPayload());

    $action->execute($context['session_id'], $context['staff_profile_id'], submitReportPayload());
})->throws(BusinessRuleViolation::class);

it('rejects an empty students payload', function (): void {
    $context = SessionReportFactory::createSessionContext();

    app(SubmitSessionReportAction::class)->execute(
        sessionId: $context['session_id'],
        staffProfileId: $context['staff_profile_id'],
        students: [],
    );
})->throws(BusinessRuleViolation::class);

it('rejects the same student evaluated twice', function (): void {
    $payload = submitReportPayload();
    $payload[] = [
        'student_profile_id' => $payload[0]['student_profile_id'],
        'participation' => 3,
        'performance' => 3,
        'commitment' => 3,
    ];

    $context = SessionReportFactory::createSessionContext();

    app(SubmitSessionReportAction::class)->execute(
        sessionId: $context['session_id'],
        staffProfileId: $context['staff_profile_id'],
        students: $payload,
    );
})->throws(BusinessRuleViolation::class);

it('rejects scores outside the allowed scale', function (): void {
    $payload = submitReportPayload();
    $payload[0]['performance'] = 6;

    $context = SessionReportFactory::createSessionContext();

    try {
        app(SubmitSessionReportAction::class)->execute(
            sessionId: $context['session_id'],
            staffProfileId: $context['staff_profile_id'],
            students: $payload,
        );

        $this->fail('Expected BusinessRuleViolation was not thrown.');
    } catch (BusinessRuleViolation $violation) {
        expect($violation->rule)->toBe('academicreports.session_report.score_out_of_range')
            ->and($violation->context['max'])->toBe(SessionReportStudent::MAX_SCORE);
    }
});
