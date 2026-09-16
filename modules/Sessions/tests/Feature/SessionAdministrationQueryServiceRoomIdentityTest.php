<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Attendance\Tests\Concerns\CreatesSessionParticipant;
use Modules\Sessions\Domain\Contracts\SessionAdministrationQueries;

uses(RefreshDatabase::class, CreatesSessionParticipant::class);

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

/** يربط الحصة التي أنشأها createSessionParticipant() بجدول متكرر جديد. */
function attachScheduleToSession(string $organizationId, string $sessionId): string
{
    $scheduleId = (string) Str::ulid();
    $session = DB::table('sessions')->where('id', $sessionId)->first(['course_id', 'staff_profile_id', 'session_type']);
    $studentProfileId = DB::table('session_participants')->where('session_id', $sessionId)->value('student_profile_id');

    DB::table('schedules')->insert([
        'id' => $scheduleId,
        'organization_id' => $organizationId,
        'student_profile_id' => $studentProfileId,
        'course_id' => $session->course_id,
        'staff_profile_id' => $session->staff_profile_id,
        'session_type' => $session->session_type,
        'rrule' => 'FREQ=WEEKLY',
        'start_time' => '10:00:00',
        'duration_minutes' => 60,
        'timezone' => 'UTC',
        'starts_on' => now()->utc()->toDateString(),
        'materialized_until' => now()->utc()->toDateString(),
        'is_active' => true,
        'created_by' => DB::table('users')->where('organization_id', $organizationId)->value('id'),
    ]);

    DB::table('sessions')->where('id', $sessionId)->update(['schedule_id' => $scheduleId]);

    return $scheduleId;
}

it('resolves the room identity to the schedule when the session belongs to one', function (): void {
    $participantId = $this->createSessionParticipant(); // @phpstan-ignore method.notFound
    $sessionId = (string) DB::table('session_participants')->where('id', $participantId)->value('session_id');
    $scheduleId = attachScheduleToSession($this->organizationId, $sessionId);

    $identity = app(SessionAdministrationQueries::class)->roomIdentityForSession($this->organizationId, $sessionId);

    expect($identity)->toBe($scheduleId);
});

it('resolves the room identity for a makeup session by following it back to the original schedule', function (): void {
    $participantId = $this->createSessionParticipant(); // @phpstan-ignore method.notFound
    $originalSessionId = (string) DB::table('session_participants')->where('id', $participantId)->value('session_id');
    $scheduleId = attachScheduleToSession($this->organizationId, $originalSessionId);

    $makeupSessionId = (string) Str::ulid();
    $original = DB::table('sessions')->where('id', $originalSessionId)->first();
    DB::table('sessions')->insert([
        'id' => $makeupSessionId,
        'organization_id' => $original->organization_id,
        'course_id' => $original->course_id,
        'staff_profile_id' => $original->staff_profile_id,
        'original_teacher_id' => $original->staff_profile_id,
        'makeup_for_session_id' => $originalSessionId,
        'session_type' => 'makeup',
        'status' => 'awaiting_review',
        'scheduled_start' => now()->utc()->addWeek(),
        'scheduled_end' => now()->utc()->addWeek()->addHour(),
        'title' => $original->title,
        'created_at' => now()->utc(),
        'updated_at' => now()->utc(),
    ]);

    $identity = app(SessionAdministrationQueries::class)->roomIdentityForSession($this->organizationId, $makeupSessionId);

    expect($identity)->toBe($scheduleId);
});

it('returns null for a one-off session with neither a schedule nor a makeup origin', function (): void {
    $participantId = $this->createSessionParticipant(); // @phpstan-ignore method.notFound
    $sessionId = (string) DB::table('session_participants')->where('id', $participantId)->value('session_id');

    $identity = app(SessionAdministrationQueries::class)->roomIdentityForSession($this->organizationId, $sessionId);

    expect($identity)->toBeNull();
});

it('finds the currently joinable session for a schedule, including a makeup standing in for it', function (): void {
    $participantId = $this->createSessionParticipant(); // @phpstan-ignore method.notFound
    $originalSessionId = (string) DB::table('session_participants')->where('id', $participantId)->value('session_id');
    $scheduleId = attachScheduleToSession($this->organizationId, $originalSessionId);

    // الحصة الأصلية بعيدة عن الآن؛ حصة تلافٍ على نفس الجدول تقع الآن ضمن نافذة الدخول.
    $now = CarbonImmutable::now('UTC');
    DB::table('sessions')->where('id', $originalSessionId)->update([
        'status' => 'postponed',
        'scheduled_start' => $now->subWeek(),
        'scheduled_end' => $now->subWeek()->addHour(),
    ]);

    $makeupSessionId = (string) Str::ulid();
    $original = DB::table('sessions')->where('id', $originalSessionId)->first();
    DB::table('sessions')->insert([
        'id' => $makeupSessionId,
        'organization_id' => $original->organization_id,
        'course_id' => $original->course_id,
        'staff_profile_id' => $original->staff_profile_id,
        'original_teacher_id' => $original->staff_profile_id,
        'makeup_for_session_id' => $originalSessionId,
        'session_type' => 'makeup',
        'status' => 'confirmed',
        'scheduled_start' => $now->subMinutes(5),
        'scheduled_end' => $now->addMinutes(55),
        'title' => $original->title,
        'created_at' => now()->utc(),
        'updated_at' => now()->utc(),
    ]);

    $current = app(SessionAdministrationQueries::class)->currentJoinableForSchedule(
        $this->organizationId,
        $scheduleId,
        $now,
        beforeMinutes: 15,
        afterMinutes: 15,
    );

    expect($current)->not->toBeNull()
        ->and($current->id)->toBe($makeupSessionId);
});

it('returns null when no session on the schedule falls within the join window', function (): void {
    $participantId = $this->createSessionParticipant(); // @phpstan-ignore method.notFound
    $sessionId = (string) DB::table('session_participants')->where('id', $participantId)->value('session_id');
    $scheduleId = attachScheduleToSession($this->organizationId, $sessionId);

    $current = app(SessionAdministrationQueries::class)->currentJoinableForSchedule(
        $this->organizationId,
        $scheduleId,
        CarbonImmutable::now('UTC')->addDays(3),
        beforeMinutes: 15,
        afterMinutes: 15,
    );

    expect($current)->toBeNull();
});
