<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\AcademicReports\Application\Actions\SubmitSessionReportAction;
use Modules\AcademicReports\Database\Factories\SessionReportFactory;
use Modules\AcademicReports\Domain\Models\SessionReport;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Sessions\Domain\Contracts\SessionAdministrationQueries;
use Modules\Sessions\Domain\Contracts\SessionParticipantAdministrationQueries;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\ValueObjects\SessionAdministrationData;
use Modules\Sessions\Domain\ValueObjects\SessionParticipantAdministrationData;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;
use Shared\Testing\Fixtures;

/*
 * الحصة التي مضى موعدها ولم يفتح المعلم غرفتها من المنصة تبقى `scheduled` إلى
 * الأبد: لا `sessions:end-elapsed` يلتقطها ولا `sessions:finalize-due`. وقد
 * تكون دُرِّست على وسيط خارجي، فكان المعلم يملك الدليل ولا يملك مكانًا يضعه فيه.
 *
 * ما تحرسه هذه الاختبارات ليس فتح الباب فحسب، بل حدوده: الحصة المؤجَّلة أو
 * المعتذَر عنها قرار موثَّق بأنها لم تُقَم فلا تقبل تقريرًا، والحصة التي لم
 * ينتهِ موعدها بعد لا تُروى قبل أن تقع، وحالة الحصة لا تتغير بالتقرير أبدًا
 * فيبقى اعتماد الإدارة وحده هو ما يفتح قيدة المستحقات.
 */

/** @return array{0: array<string, mixed>, 1: string, 2: string, 3: string} */
function offPlatformContext(): array
{
    $context = SessionReportFactory::createSessionContext();
    $row = DB::table('sessions')->where('id', $context['session_id'])->first();

    return [$context, (string) $row->organization_id, (string) $row->course_id, Fixtures::userId()];
}

/**
 * @param array<string, mixed> $context
 */
function offPlatformSession(
    array $context,
    string $organizationId,
    string $courseId,
    string $status,
    CarbonImmutable $end,
): SessionAdministrationData {
    return new SessionAdministrationData(
        id: $context['session_id'],
        organizationId: $organizationId,
        groupId: (string) Str::ulid(),
        courseId: $courseId,
        staffProfileId: $context['staff_profile_id'],
        status: $status,
        title: ['ar' => 'حصة'],
        scheduledStart: $end->subMinutes(25)->toIso8601String(),
        scheduledEnd: $end->toIso8601String(),
    );
}

/**
 * التقييمات يجب أن تطابق طلاب الحصة بالضبط، والمُثبِّت ينشئ طالبًا جديدًا في
 * كل نداء — فتُبنى الحمولة مرة واحدة وتُمرَّر إلى المشارك المُحاكى وإلى الفعل معًا.
 *
 * @return list<array<string, mixed>>
 */
function offPlatformPayload(): array
{
    return [[
        'student_profile_id' => Fixtures::studentProfileId(),
        'participation' => 4,
        'performance' => 4,
        'commitment' => 4,
        'strengths' => null,
        'weaknesses' => null,
        'note' => 'دُرِّست على وسيط خارجي.',
    ]];
}

/** @param list<array<string, mixed>> $payload */
function offPlatformAction(
    SessionAdministrationData $session,
    bool $expectParticipants,
    array $payload,
): SubmitSessionReportAction {
    $sessions = Mockery::mock(SessionAdministrationQueries::class);
    $sessions->shouldReceive('findForOrganization')->once()->andReturn($session);

    $participants = Mockery::mock(SessionParticipantAdministrationQueries::class);
    $participants->shouldReceive('forSession')
        ->times($expectParticipants ? 1 : 0)
        ->andReturn([new SessionParticipantAdministrationData(
            id: (string) Str::ulid(),
            organizationId: $session->organizationId,
            sessionId: $session->id,
            studentProfileId: (string) $payload[0]['student_profile_id'],
            enrollmentId: (string) Str::ulid(),
            courseId: $session->courseId,
            groupId: null,
            staffProfileId: $session->staffProfileId,
            sessionTitle: ['ar' => 'حصة'],
            sessionStatus: $session->status,
            scheduledStart: $session->scheduledStart,
            scheduledEnd: $session->scheduledEnd,
            firstJoinedAt: null,
            lastLeftAt: null,
            attendedMinutes: 0,
            invitationActive: true,
        )]);

    $audit = Mockery::mock(AuditRecorder::class);
    $audit->shouldReceive('record')->andReturn((string) Str::ulid());

    return new SubmitSessionReportAction(
        app(Transaction::class),
        app(Dispatcher::class),
        $sessions,
        $participants,
        $audit,
    );
}

it('accepts a report for a past session the teacher never opened from the platform', function (): void {
    [$context, $organizationId, $courseId, $actorId] = offPlatformContext();
    $session = offPlatformSession(
        $context,
        $organizationId,
        $courseId,
        SessionStatus::Scheduled->value,
        CarbonImmutable::now('UTC')->subHours(3),
    );

    $payload = offPlatformPayload();
    $statusBefore = DB::table('sessions')->where('id', $context['session_id'])->value('status');
    $report = offPlatformAction($session, true, $payload)->executeForTeacher(
        organizationId: $organizationId,
        sessionId: $context['session_id'],
        staffProfileId: $context['staff_profile_id'],
        actorId: $actorId,
        students: $payload,
        topicsCovered: 'مراجعة سورة البقرة.',
    );

    expect($report->session_id)->toBe($context['session_id'])
        ->and(SessionReport::query()->where('session_id', $context['session_id'])->count())->toBe(1);

    /*
     * الحصة لم تتحرك: التقرير إقرار لا قرار. لو غيّر حالتها لالتقطها
     * `sessions:finalize-due` بعد اثنتي عشرة ساعة وأقفلها بلا مرور على
     * الإدارة، وهو بالضبط ما لا يجوز لحصة لا نعرف عنها إلا كلام معلمها.
     */
    expect(DB::table('sessions')->where('id', $context['session_id'])->value('status'))
        ->toBe($statusBefore)
        ->and(DB::table('session_status_history')->where('session_id', $context['session_id'])->count())
        ->toBe(0);
});

it('refuses a report for a session whose time has not come yet', function (): void {
    [$context, $organizationId, $courseId, $actorId] = offPlatformContext();
    $session = offPlatformSession(
        $context,
        $organizationId,
        $courseId,
        SessionStatus::Scheduled->value,
        CarbonImmutable::now('UTC')->addHours(3),
    );

    try {
        offPlatformAction($session, false, offPlatformPayload())->executeForTeacher(
            organizationId: $organizationId,
            sessionId: $context['session_id'],
            staffProfileId: $context['staff_profile_id'],
            actorId: $actorId,
            students: offPlatformPayload(),
        );

        $this->fail('Expected BusinessRuleViolation was not thrown.');
    } catch (BusinessRuleViolation $violation) {
        expect($violation->rule)->toBe('academicreports.session_report.session_not_ended');
    }

    expect(SessionReport::query()->where('session_id', $context['session_id'])->count())->toBe(0);
});

it('refuses a report for a session already decided not to have been held', function (string $status): void {
    [$context, $organizationId, $courseId, $actorId] = offPlatformContext();
    $session = offPlatformSession(
        $context,
        $organizationId,
        $courseId,
        $status,
        CarbonImmutable::now('UTC')->subHours(3),
    );

    try {
        offPlatformAction($session, false, offPlatformPayload())->executeForTeacher(
            organizationId: $organizationId,
            sessionId: $context['session_id'],
            staffProfileId: $context['staff_profile_id'],
            actorId: $actorId,
            students: offPlatformPayload(),
        );

        $this->fail('Expected BusinessRuleViolation was not thrown.');
    } catch (BusinessRuleViolation $violation) {
        expect($violation->rule)->toBe('academicreports.session_report.invalid_session_state');
    }

    expect(SessionReport::query()->where('session_id', $context['session_id'])->count())->toBe(0);
})->with([
    SessionStatus::Postponed->value,
    SessionStatus::Excused->value,
    SessionStatus::CancelledBySchool->value,
    SessionStatus::CancelledByTeacher->value,
]);
