<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Attendance\Tests\Concerns\CreatesSessionParticipant;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Models\Session;
use Modules\VirtualClassroom\Domain\Enums\JoinRole;
use Modules\VirtualClassroom\Domain\Events\ClassroomParticipantJoined;

uses(RefreshDatabase::class, CreatesSessionParticipant::class);

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

/** @return array{session_id: string, teacher_user_id: string, student_user_id: string} */
function lifecycleContext(string $participantId): array
{
    $participant = DB::table('session_participants')->where('id', $participantId)->first();
    $session = DB::table('sessions')->where('id', $participant->session_id)->first();
    DB::table('sessions')->where('id', $session->id)->update(['status' => SessionStatus::Scheduled->value]);

    return [
        'session_id' => (string) $session->id,
        'teacher_user_id' => (string) DB::table('staff_profiles')->where('id', $session->staff_profile_id)->value('user_id'),
        'student_user_id' => (string) DB::table('student_profiles')->where('id', $participant->student_profile_id)->value('user_id'),
    ];
}

function joinAs(string $sessionId, string $userId, JoinRole $role): void
{
    event(new ClassroomParticipantJoined(
        classroomId: (string) str()->ulid(),
        sessionId: $sessionId,
        provider: 'bigbluebutton',
        externalUserId: $userId,
        userId: $userId,
        role: $role,
        occurredAt: CarbonImmutable::now('UTC')->toIso8601String(),
    ));
}

it('starts the session when the teacher enters the live classroom', function (): void {
    $context = lifecycleContext($this->createSessionParticipant()); // @phpstan-ignore method.notFound

    joinAs($context['session_id'], $context['teacher_user_id'], JoinRole::Moderator);

    $session = Session::query()->findOrFail($context['session_id']);
    expect($session->status)->toBe(SessionStatus::InProgress)
        ->and($session->actual_start)->not->toBeNull()
        ->and(DB::table('session_status_history')
            ->where('session_id', $context['session_id'])
            ->where('to_status', SessionStatus::InProgress->value)
            ->value('changed_by'))->toBe($context['teacher_user_id']);
});

it('does not start the session when only a student enters', function (): void {
    $context = lifecycleContext($this->createSessionParticipant()); // @phpstan-ignore method.notFound

    joinAs($context['session_id'], $context['student_user_id'], JoinRole::Viewer);

    expect(Session::query()->findOrFail($context['session_id'])->status)->toBe(SessionStatus::Scheduled);
});

it('treats a teacher reconnect on a running session as a no-op', function (): void {
    $context = lifecycleContext($this->createSessionParticipant()); // @phpstan-ignore method.notFound

    joinAs($context['session_id'], $context['teacher_user_id'], JoinRole::Moderator);
    joinAs($context['session_id'], $context['teacher_user_id'], JoinRole::Moderator);

    expect(Session::query()->findOrFail($context['session_id'])->status)->toBe(SessionStatus::InProgress)
        ->and(DB::table('session_status_history')
            ->where('session_id', $context['session_id'])
            ->where('to_status', SessionStatus::InProgress->value)
            ->count())->toBe(1);
});

it('ends an elapsed running session for review after the configured grace', function (): void {
    $context = lifecycleContext($this->createSessionParticipant()); // @phpstan-ignore method.notFound
    joinAs($context['session_id'], $context['teacher_user_id'], JoinRole::Moderator);
    $end = CarbonImmutable::parse((string) DB::table('sessions')->where('id', $context['session_id'])->value('scheduled_end'), 'UTC');
    $grace = (int) config('scheduling.auto_end.after_minutes');

    CarbonImmutable::setTestNow($end->addMinutes($grace - 1));
    $this->artisan('sessions:end-elapsed')->assertSuccessful(); // @phpstan-ignore method.notFound
    expect(Session::query()->findOrFail($context['session_id'])->status)->toBe(SessionStatus::InProgress);

    CarbonImmutable::setTestNow($end->addMinutes($grace + 1));
    $this->artisan('sessions:end-elapsed')->assertSuccessful(); // @phpstan-ignore method.notFound

    expect(Session::query()->findOrFail($context['session_id'])->status)->toBe(SessionStatus::AwaitingReview)
        ->and(DB::table('session_status_history')
            ->where('session_id', $context['session_id'])
            ->where('to_status', SessionStatus::AwaitingReview->value)
            ->value('changed_by'))->toBe($context['teacher_user_id'])
        ->and(DB::table('audit_log')
            ->where('auditable_id', $context['session_id'])
            ->where('action', 'sessions.session_ended')
            ->value('actor_type'))->toBe('system');
});
