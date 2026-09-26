<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Modules\Sessions\Application\Actions\PingSessionReadyAction;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Events\StudentPingedReady;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Domain\Models\SessionParticipant;
use Shared\Support\BusinessRuleViolation;
use Shared\Testing\Fixtures;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Fixtures::flush();
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-27 10:00:00', 'UTC'));
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

/**
 * @return array{
 *   organizationId: string,
 *   studentUserId: string,
 *   studentProfileId: string,
 *   teacherUserId: string,
 *   session: Session,
 *   participant: SessionParticipant
 * }
 */
function readyPingFixture(): array
{
    $organizationId = Fixtures::organizationId();
    $studentUserId = Fixtures::userId();
    $studentProfileId = Fixtures::studentProfileForUser($studentUserId);
    $staffProfileId = Fixtures::staffProfileId();
    $teacherUserId = (string) DB::table('staff_profiles')->where('id', $staffProfileId)->value('user_id');
    $courseId = Fixtures::courseId();
    $programId = (string) DB::table('courses')
        ->join('levels', 'levels.id', '=', 'courses.level_id')
        ->where('courses.id', $courseId)
        ->value('levels.program_id');
    $now = CarbonImmutable::now('UTC');

    $enrollmentId = (string) Str::ulid();
    DB::table('enrollments')->insert([
        'id' => $enrollmentId,
        'organization_id' => $organizationId,
        'student_profile_id' => $studentProfileId,
        'program_id' => $programId,
        'status' => 'active',
        'applied_at' => $now,
        'activated_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $session = Session::query()->create([
        'organization_id' => $organizationId,
        'course_id' => $courseId,
        'staff_profile_id' => $staffProfileId,
        'session_type' => 'individual',
        'status' => SessionStatus::Scheduled,
        'scheduled_start' => $now->addHours(6),
        'scheduled_end' => $now->addHours(6)->addMinutes(35),
        'title' => ['ar' => 'Ready ping session', 'en' => 'Ready ping session'],
    ]);

    $participant = SessionParticipant::query()->create([
        'session_id' => $session->id,
        'student_profile_id' => $studentProfileId,
        'enrollment_id' => $enrollmentId,
        'join_url_token' => Str::random(64),
        'invited_at' => $now,
        'attended_minutes' => 0,
    ]);

    return compact(
        'organizationId',
        'studentUserId',
        'studentProfileId',
        'teacherUserId',
        'session',
        'participant',
    );
}

it('records the ready ping and notifies the teacher', function (): void {
    Event::fake([StudentPingedReady::class]);
    $context = readyPingFixture();

    $participant = app(PingSessionReadyAction::class)->execute(
        organizationId: $context['organizationId'],
        sessionId: (string) $context['session']->id,
        studentProfileId: $context['studentProfileId'],
        actorId: $context['studentUserId'],
    );

    expect($participant->ready_pinged_at)->not->toBeNull();

    Event::assertDispatched(
        StudentPingedReady::class,
        fn (StudentPingedReady $event): bool => $event->studentProfileId === $context['studentProfileId']
            && $event->teacherUserId === $context['teacherUserId']
            && $event->sessionId === (string) $context['session']->id,
    );
});

it('only keeps the latest ping instead of accumulating history', function (): void {
    $context = readyPingFixture();
    $action = app(PingSessionReadyAction::class);

    $action->execute(
        organizationId: $context['organizationId'],
        sessionId: (string) $context['session']->id,
        studentProfileId: $context['studentProfileId'],
        actorId: $context['studentUserId'],
    );
    $firstPing = $context['participant']->refresh()->ready_pinged_at;

    CarbonImmutable::setTestNow(CarbonImmutable::now('UTC')->addMinutes(10));

    $action->execute(
        organizationId: $context['organizationId'],
        sessionId: (string) $context['session']->id,
        studentProfileId: $context['studentProfileId'],
        actorId: $context['studentUserId'],
    );

    expect($context['participant']->refresh()->ready_pinged_at)->not->toEqual($firstPing);
    expect(DB::table('session_participants')->where('id', $context['participant']->id)->count())->toBe(1);
});

it('never changes the session status or attendance', function (): void {
    $context = readyPingFixture();

    app(PingSessionReadyAction::class)->execute(
        organizationId: $context['organizationId'],
        sessionId: (string) $context['session']->id,
        studentProfileId: $context['studentProfileId'],
        actorId: $context['studentUserId'],
    );

    expect($context['session']->refresh()->status)->toBe(SessionStatus::Scheduled);
});

it('rejects a ready ping for a session that already ended', function (): void {
    $context = readyPingFixture();
    $context['session']->forceFill(['status' => SessionStatus::Completed])->save();

    expect(fn () => app(PingSessionReadyAction::class)->execute(
        organizationId: $context['organizationId'],
        sessionId: (string) $context['session']->id,
        studentProfileId: $context['studentProfileId'],
        actorId: $context['studentUserId'],
    ))->toThrow(BusinessRuleViolation::class);
});

it('rejects a ready ping from a student not registered in the session', function (): void {
    $context = readyPingFixture();
    $otherStudentUserId = Fixtures::userId();
    $otherStudentProfileId = Fixtures::studentProfileForUser($otherStudentUserId);

    expect(fn () => app(PingSessionReadyAction::class)->execute(
        organizationId: $context['organizationId'],
        sessionId: (string) $context['session']->id,
        studentProfileId: $otherStudentProfileId,
        actorId: $otherStudentUserId,
    ))->toThrow(BusinessRuleViolation::class);
});
