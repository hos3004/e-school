<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Modules\Academics\Domain\Models\Course;
use Modules\Academics\Domain\Models\Level;
use Modules\Academics\Domain\Models\Program;
use Modules\Enrollments\Domain\Enums\EnrollmentStatus;
use Modules\Enrollments\Domain\Models\Enrollment;
use Modules\Groups\Domain\Enums\MembershipStatus;
use Modules\Groups\Domain\Models\Group;
use Modules\Groups\Domain\Models\GroupMembership;
use Modules\Reporting\Domain\Contracts\ClosureSnapshotQueries;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Models\Session;
use Modules\Students\Domain\Models\StudentProfile;

/** يبني برنامجًا بمستوى وكورس واحد داخل نفس المؤسسة.
 *
 * @return array{Program, Level, Course}
 */
function closureProgramFixture(): array
{
    $program = Program::factory()->create();
    $level = Level::factory()->for($program, 'program')->create();
    $course = Course::factory()->create([
        'organization_id' => $program->organization_id,
        'level_id' => $level->getKey(),
    ]);

    return [$program, $level, $course];
}

function closureSession(Program $program, Course $course, SessionStatus $status, int $daysAgo): Session
{
    $start = CarbonImmutable::now('UTC')->subDays($daysAgo);

    return Session::factory()->create([
        'organization_id' => $program->organization_id,
        'course_id' => $course->getKey(),
        'status' => $status,
        'scheduled_start' => $start,
        'scheduled_end' => $start->addMinutes(60),
    ]);
}

it('counts a program\'s real structure, sessions and enrollments', function (): void {
    [$program, , $course] = closureProgramFixture();

    closureSession($program, $course, SessionStatus::Completed, 30);
    closureSession($program, $course, SessionStatus::Completed, 20);
    closureSession($program, $course, SessionStatus::CancelledBySchool, 10);

    $student = StudentProfile::factory()->create(['organization_id' => $program->organization_id]);
    Enrollment::query()->create([
        'organization_id' => $program->organization_id,
        'student_profile_id' => $student->getKey(),
        'program_id' => $program->getKey(),
        'status' => EnrollmentStatus::Completed,
        'applied_at' => CarbonImmutable::now('UTC')->subMonths(3),
    ]);

    $snapshot = app(ClosureSnapshotQueries::class)
        ->forProgram((string) $program->organization_id, (string) $program->getKey());

    expect($snapshot->summary['kind'])->toBe('program')
        ->and($snapshot->summary['levels_total'])->toBe(1)
        ->and($snapshot->summary['courses_total'])->toBe(1)
        ->and($snapshot->summary['sessions_total'])->toBe(3)
        ->and($snapshot->summary['sessions_completed'])->toBe(2)
        ->and($snapshot->summary['sessions_cancelled'])->toBe(1)
        ->and($snapshot->summary['enrollments_total'])->toBe(1)
        ->and($snapshot->summary['enrollments_completed'])->toBe(1)
        ->and($snapshot->summary['students_distinct'])->toBe(1)
        ->and($snapshot->summary['first_session_at'])->not->toBeNull()
        ->and($snapshot->summary['last_session_at'])->not->toBeNull();

    // لا شيء قائم: كورس غير نشط، ولا قيد حي، ولا حصة مفتوحة.
    $course->update(['is_active' => false]);

    expect(app(ClosureSnapshotQueries::class)
        ->forProgram((string) $program->organization_id, (string) $program->getKey())
        ->isBlocked())->toBeFalse();
});

it('blocks a program that still has a live enrollment', function (): void {
    [$program, , $course] = closureProgramFixture();
    $course->update(['is_active' => false]);

    $student = StudentProfile::factory()->create(['organization_id' => $program->organization_id]);
    Enrollment::query()->create([
        'organization_id' => $program->organization_id,
        'student_profile_id' => $student->getKey(),
        'program_id' => $program->getKey(),
        'status' => EnrollmentStatus::Active,
        'applied_at' => CarbonImmutable::now('UTC')->subMonths(1),
    ]);

    $snapshot = app(ClosureSnapshotQueries::class)
        ->forProgram((string) $program->organization_id, (string) $program->getKey());

    expect($snapshot->isBlocked())->toBeTrue()
        ->and($snapshot->blockers)->toHaveKey('enrollments_live')
        ->and($snapshot->blockers['enrollments_live'])->toBe(1);
});

it('blocks a program whose sessions have not reached a final state', function (): void {
    [$program, , $course] = closureProgramFixture();
    $course->update(['is_active' => false]);

    closureSession($program, $course, SessionStatus::Scheduled, -5);

    $snapshot = app(ClosureSnapshotQueries::class)
        ->forProgram((string) $program->organization_id, (string) $program->getKey());

    expect($snapshot->blockers)->toHaveKey('sessions_open')
        ->and($snapshot->blockers['sessions_open'])->toBe(1);
});

it('ignores sessions that are in the trash', function (): void {
    [$program, , $course] = closureProgramFixture();

    $session = closureSession($program, $course, SessionStatus::Completed, 5);
    closureSession($program, $course, SessionStatus::Completed, 4);
    $session->delete();

    $snapshot = app(ClosureSnapshotQueries::class)
        ->forProgram((string) $program->organization_id, (string) $program->getKey());

    expect($snapshot->summary['sessions_total'])->toBe(1);
});

it('measures a group by its members, teachers and sessions', function (): void {
    $group = Group::factory()->create();

    GroupMembership::factory()->count(2)->create([
        'group_id' => $group->getKey(),
        'status' => MembershipStatus::Left,
        'left_at' => CarbonImmutable::now('UTC')->subDay(),
    ]);

    $course = Course::factory()->create(['organization_id' => $group->organization_id]);

    $start = CarbonImmutable::now('UTC')->subDays(3);
    Session::factory()->create([
        'organization_id' => $group->organization_id,
        'group_id' => $group->getKey(),
        'course_id' => $course->getKey(),
        'status' => SessionStatus::Completed,
        'scheduled_start' => $start,
        'scheduled_end' => $start->addMinutes(60),
    ]);

    $snapshot = app(ClosureSnapshotQueries::class)
        ->forGroup((string) $group->organization_id, (string) $group->getKey());

    expect($snapshot->summary['kind'])->toBe('group')
        ->and($snapshot->summary['members_total'])->toBe(2)
        ->and($snapshot->summary['sessions_total'])->toBe(1)
        ->and($snapshot->summary['sessions_completed'])->toBe(1)
        ->and($snapshot->summary['capacity'])->toBe($group->capacity)
        ->and($snapshot->isBlocked())->toBeFalse();
});

it('blocks a group that still holds active members', function (): void {
    $group = Group::factory()->create();

    GroupMembership::factory()->create([
        'group_id' => $group->getKey(),
        'status' => MembershipStatus::Active,
    ]);

    $snapshot = app(ClosureSnapshotQueries::class)
        ->forGroup((string) $group->organization_id, (string) $group->getKey());

    expect($snapshot->blockers)->toHaveKey('members_active')
        ->and($snapshot->blockers['members_active'])->toBe(1);
});

it('keeps each organization\'s numbers to itself', function (): void {
    [$program, , $course] = closureProgramFixture();
    closureSession($program, $course, SessionStatus::Completed, 3);

    $otherOrganizationId = (string) Str::ulid();

    $snapshot = app(ClosureSnapshotQueries::class)
        ->forProgram($otherOrganizationId, (string) $program->getKey());

    expect($snapshot->summary['courses_total'])->toBe(0)
        ->and($snapshot->summary['sessions_total'])->toBe(0)
        ->and($snapshot->summary['enrollments_total'])->toBe(0);
});

it('records past sessions that were never closed without letting them block the archive', function (): void {
    [$program, , $course] = closureProgramFixture();
    $course->update(['is_active' => false]);

    // حصة مضى موعدها ولم يفتحها أحد: مخلَّفات لا شغل قائم.
    closureSession($program, $course, SessionStatus::Scheduled, 40);
    closureSession($program, $course, SessionStatus::AwaitingReview, 12);

    $snapshot = app(ClosureSnapshotQueries::class)
        ->forProgram((string) $program->organization_id, (string) $program->getKey());

    expect($snapshot->summary['sessions_stale'])->toBe(2)
        ->and($snapshot->summary['sessions_completed'])->toBe(0)
        ->and($snapshot->blockers)->not->toHaveKey('sessions_open')
        ->and($snapshot->isBlocked())->toBeFalse();
});

it('measures a level by the courses under it and their sessions', function (): void {
    [$program, $level, $course] = closureProgramFixture();
    $course->update(['is_active' => false]);

    closureSession($program, $course, SessionStatus::Completed, 9);
    closureSession($program, $course, SessionStatus::Completed, 4);

    $snapshot = app(ClosureSnapshotQueries::class)
        ->forLevel((string) $program->organization_id, (string) $level->getKey());

    expect($snapshot->summary['kind'])->toBe('level')
        ->and($snapshot->summary['program_id'])->toBe((string) $program->getKey())
        ->and($snapshot->summary['courses_total'])->toBe(1)
        ->and($snapshot->summary['sessions_total'])->toBe(2)
        ->and($snapshot->summary['sessions_completed'])->toBe(2)
        ->and($snapshot->isBlocked())->toBeFalse();
});

it('blocks a level whose course is still active', function (): void {
    [$program, $level] = closureProgramFixture();

    $snapshot = app(ClosureSnapshotQueries::class)
        ->forLevel((string) $program->organization_id, (string) $level->getKey());

    expect($snapshot->blockers)->toHaveKey('courses_active')
        ->and($snapshot->blockers['courses_active'])->toBe(1);
});

it('keeps another organization out of a level snapshot', function (): void {
    [, $level] = closureProgramFixture();

    $snapshot = app(ClosureSnapshotQueries::class)
        ->forLevel((string) Str::ulid(), (string) $level->getKey());

    expect($snapshot->summary['program_id'])->toBeNull()
        ->and($snapshot->summary['courses_total'])->toBe(0);
});
