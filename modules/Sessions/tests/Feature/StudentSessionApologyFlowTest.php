<?php

declare(strict_types=1);

use App\Listeners\FinalizeClassroomAttendance;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Modules\Attendance\Domain\Enums\AttendanceStatus;
use Modules\Attendance\Domain\Models\Attendance;
use Modules\Sessions\Application\Actions\SubmitStudentSessionApologyAction;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Events\StudentSessionApologized;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Domain\Models\SessionParticipant;
use Modules\VirtualClassroom\Domain\Events\ClassroomEnded;
use Shared\Support\BusinessRuleViolation;
use Shared\Testing\Fixtures;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Fixtures::flush();
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-06 10:00:00', 'UTC'));
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

/**
 * @return array{
 *   organizationId: string,
 *   studentUserId: string,
 *   studentProfileId: string,
 *   session: Session,
 *   participant: SessionParticipant
 * }
 */
function studentApologyFixture(bool $groupSession): array
{
    $organizationId = Fixtures::organizationId();
    $studentUserId = Fixtures::userId();
    $studentProfileId = Fixtures::studentProfileForUser($studentUserId);
    $staffProfileId = Fixtures::staffProfileId();
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

    $groupId = null;
    if ($groupSession) {
        $groupId = (string) Str::ulid();
        DB::table('groups')->insert([
            'id' => $groupId,
            'organization_id' => $organizationId,
            'code' => 'APOLOGY-'.strtoupper(substr($groupId, -8)),
            'name' => json_encode(['ar' => 'Apology group', 'en' => 'Apology group'], JSON_UNESCAPED_UNICODE),
            'capacity' => 10,
            'timezone' => 'UTC',
            'status' => 'active',
            'starts_on' => $now->toDateString(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    $session = Session::query()->create([
        'organization_id' => $organizationId,
        'group_id' => $groupId,
        'course_id' => $courseId,
        'staff_profile_id' => $staffProfileId,
        'session_type' => $groupSession ? 'group' : 'individual',
        'status' => SessionStatus::Scheduled,
        'scheduled_start' => $now->addHours(2),
        'scheduled_end' => $now->addHours(3),
        'title' => ['ar' => 'Apology session', 'en' => 'Apology session'],
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
        'session',
        'participant',
    );
}

it('records a group student apology without cancelling the teacher session', function (): void {
    Event::fake([StudentSessionApologized::class]);
    $context = studentApologyFixture(groupSession: true);

    app(SubmitStudentSessionApologyAction::class)->execute(
        organizationId: $context['organizationId'],
        sessionId: (string) $context['session']->id,
        studentProfileId: $context['studentProfileId'],
        actorId: $context['studentUserId'],
        reason: 'Family circumstance',
    );

    expect($context['participant']->refresh()->excused_at)->not->toBeNull()
        ->and($context['participant']->excuse_reason)->toBe('Family circumstance')
        ->and($context['session']->refresh()->status)->toBe(SessionStatus::Scheduled);

    Event::assertDispatched(
        StudentSessionApologized::class,
        fn (StudentSessionApologized $event): bool => $event->groupSession
            && $event->studentProfileId === $context['studentProfileId'],
    );
});

it('marks an individual session excused when its student apologizes', function (): void {
    $context = studentApologyFixture(groupSession: false);

    app(SubmitStudentSessionApologyAction::class)->execute(
        organizationId: $context['organizationId'],
        sessionId: (string) $context['session']->id,
        studentProfileId: $context['studentProfileId'],
        actorId: $context['studentUserId'],
        reason: 'Medical appointment',
    );

    expect($context['session']->refresh()->status)->toBe(SessionStatus::Excused)
        ->and($context['participant']->refresh()->excused_at)->not->toBeNull();
});

it('rejects a student apology inside the configured one hour notice', function (): void {
    $context = studentApologyFixture(groupSession: true);
    $context['session']->forceFill([
        'scheduled_start' => CarbonImmutable::now('UTC')->addMinutes(59),
        'scheduled_end' => CarbonImmutable::now('UTC')->addMinutes(119),
    ])->save();

    expect(fn () => app(SubmitStudentSessionApologyAction::class)->execute(
        organizationId: $context['organizationId'],
        sessionId: (string) $context['session']->id,
        studentProfileId: $context['studentProfileId'],
        actorId: $context['studentUserId'],
        reason: 'Late apology',
    ))->toThrow(BusinessRuleViolation::class);
});

it('finalizes an apologizing group student as excused rather than absent', function (): void {
    $context = studentApologyFixture(groupSession: true);

    app(SubmitStudentSessionApologyAction::class)->execute(
        organizationId: $context['organizationId'],
        sessionId: (string) $context['session']->id,
        studentProfileId: $context['studentProfileId'],
        actorId: $context['studentUserId'],
        reason: 'Advance excuse',
    );

    app(FinalizeClassroomAttendance::class)->handle(new ClassroomEnded(
        classroomId: (string) Str::ulid(),
        sessionId: (string) $context['session']->id,
        provider: 'bigbluebutton',
        endedAt: $context['session']->scheduled_end->addMinutes(15)->toIso8601String(),
        maxConcurrentParticipants: 1,
    ));

    $attendance = Attendance::query()
        ->where('session_participant_id', $context['participant']->id)
        ->sole();

    expect($attendance->derived_status)->toBe(AttendanceStatus::Excused)
        ->and($attendance->status)->toBe(AttendanceStatus::Excused);
});
