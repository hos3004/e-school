<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Attendance\Application\Actions\RecordAttendanceSheetAction;
use Modules\Attendance\Domain\Enums\AttendanceStatus;
use Modules\Attendance\Tests\Concerns\CreatesSessionParticipant;
use Shared\Support\BusinessRuleViolation;

uses(RefreshDatabase::class, CreatesSessionParticipant::class);

it('rejects a sheet submitted by a teacher not assigned to the session', function (): void {
    $participantId = $this->createSessionParticipant(); // @phpstan-ignore method.notFound
    $participant = DB::table('session_participants')->where('id', $participantId)->first();
    $session = DB::table('sessions')->where('id', $participant->session_id)->first();

    app(RecordAttendanceSheetAction::class)->execute(
        organizationId: (string) $this->organizationId, // @phpstan-ignore property.notFound
        sessionId: (string) $session->id,
        staffProfileId: (string) str()->ulid(),
        statuses: [
            (string) $participant->student_profile_id => AttendanceStatus::Present->value,
        ],
        actorId: (string) str()->ulid(),
        reason: null,
    );
})->throws(BusinessRuleViolation::class);

it('allows the assigned teacher to confirm the derived status', function (): void {
    $participantId = $this->createSessionParticipant(); // @phpstan-ignore method.notFound
    $participant = DB::table('session_participants')->where('id', $participantId)->first();
    $session = DB::table('sessions')->where('id', $participant->session_id)->first();
    $teacherUserId = DB::table('staff_profiles')->where('id', $session->staff_profile_id)->value('user_id');

    recordAttendanceSheetTeacherPresence(
        (string) $session->id,
        (string) $teacherUserId,
        CarbonImmutable::parse($session->scheduled_start, 'UTC')->addMinutes(5),
    );

    $result = app(RecordAttendanceSheetAction::class)->execute(
        organizationId: (string) $this->organizationId, // @phpstan-ignore property.notFound
        sessionId: (string) $session->id,
        staffProfileId: (string) $session->staff_profile_id,
        statuses: [(string) $participant->student_profile_id => AttendanceStatus::Present->value],
        actorId: (string) $teacherUserId,
    );

    expect($result)->toMatchArray(['recorded' => 1, 'confirmed' => 1, 'overridden' => 0]);
});

it('rejects the assigned teacher when no room presence was recorded', function (): void {
    $participantId = $this->createSessionParticipant(); // @phpstan-ignore method.notFound
    $participant = DB::table('session_participants')->where('id', $participantId)->first();
    $session = DB::table('sessions')->where('id', $participant->session_id)->first();
    $teacherUserId = DB::table('staff_profiles')->where('id', $session->staff_profile_id)->value('user_id');

    app(RecordAttendanceSheetAction::class)->execute(
        organizationId: (string) $this->organizationId, // @phpstan-ignore property.notFound
        sessionId: (string) $session->id,
        staffProfileId: (string) $session->staff_profile_id,
        statuses: [(string) $participant->student_profile_id => AttendanceStatus::Present->value],
        actorId: (string) $teacherUserId,
    );
})->throws(BusinessRuleViolation::class);

it('rejects a teacher join recorded only after the official session end', function (): void {
    $participantId = $this->createSessionParticipant(); // @phpstan-ignore method.notFound
    $participant = DB::table('session_participants')->where('id', $participantId)->first();
    $session = DB::table('sessions')->where('id', $participant->session_id)->first();
    $teacherUserId = DB::table('staff_profiles')->where('id', $session->staff_profile_id)->value('user_id');

    recordAttendanceSheetTeacherPresence(
        (string) $session->id,
        (string) $teacherUserId,
        CarbonImmutable::parse($session->scheduled_end, 'UTC')->addMinute(),
    );

    app(RecordAttendanceSheetAction::class)->execute(
        organizationId: (string) $this->organizationId, // @phpstan-ignore property.notFound
        sessionId: (string) $session->id,
        staffProfileId: (string) $session->staff_profile_id,
        statuses: [(string) $participant->student_profile_id => AttendanceStatus::Present->value],
        actorId: (string) $teacherUserId,
    );
})->throws(BusinessRuleViolation::class);

function recordAttendanceSheetTeacherPresence(
    string $sessionId,
    string $teacherUserId,
    CarbonImmutable $joinedAt,
): void {
    $classroomId = (string) Str::ulid();
    $now = CarbonImmutable::now('UTC');

    DB::table('classrooms')->insert([
        'id' => $classroomId,
        'session_id' => $sessionId,
        'provider' => 'bigbluebutton',
        'external_id' => 'ATTENDANCE-'.$sessionId,
        'moderator_secret' => 'moderator-secret',
        'attendee_secret' => 'attendee-secret',
        'created_remote_at' => $now,
        'status' => 'running',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('classroom_events')->insert([
        'id' => (string) Str::ulid(),
        'classroom_id' => $classroomId,
        'idempotency_key' => hash('sha256', $classroomId.'|'.$teacherUserId),
        'event_type' => 'participant_joined',
        'external_user_id' => $teacherUserId,
        'user_id' => $teacherUserId,
        'occurred_at' => $joinedAt,
        'payload' => json_encode(['role' => 'moderator'], JSON_THROW_ON_ERROR),
        'created_at' => $now,
    ]);
}
