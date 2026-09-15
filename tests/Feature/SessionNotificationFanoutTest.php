<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Notifications\Domain\Contracts\DomainEventRecipientResolver;
use Shared\Testing\Fixtures;

/**
 * حصة فردية جمهورها المشارك فيها ومعلمها. التوسّع على كل طلاب الدورة
 * حوّل حصة واحدة إلى إشعار لكل طالب في البرنامج (حادثة 15 سبتمبر 2026).
 */
function fanoutEnrolledStudent(string $courseId, string $programId): string
{
    $studentProfileId = Fixtures::studentProfileId();

    DB::table('enrollments')->insert([
        'id' => (string) Str::ulid(),
        'organization_id' => Fixtures::organizationId(),
        'student_profile_id' => $studentProfileId,
        'program_id' => $programId,
        'status' => 'active',
        'applied_at' => now(),
        'activated_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $studentProfileId;
}

function fanoutScheduleSession(string $courseId, string $staffProfileId, ?string $participantProfileId): string
{
    $sessionId = (string) Str::ulid();

    DB::table('sessions')->insert([
        'id' => $sessionId,
        'organization_id' => Fixtures::organizationId(),
        'group_id' => null,
        'course_id' => $courseId,
        'staff_profile_id' => $staffProfileId,
        'original_teacher_id' => $staffProfileId,
        'session_type' => 'individual',
        'status' => 'scheduled',
        'scheduled_start' => now()->addDay(),
        'scheduled_end' => now()->addDay()->addMinutes(25),
        'title' => json_encode(['ar' => 'حصة', 'en' => 'Session'], JSON_UNESCAPED_UNICODE),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    if ($participantProfileId !== null) {
        $enrollmentId = DB::table('enrollments')
            ->where('student_profile_id', $participantProfileId)
            ->value('id');

        DB::table('session_participants')->insert([
            'id' => (string) Str::ulid(),
            'session_id' => $sessionId,
            'student_profile_id' => $participantProfileId,
            'enrollment_id' => (string) $enrollmentId,
            'join_url_token' => (string) Str::ulid(),
            'invited_at' => now(),
            'attended_minutes' => 0,
            'attended_seconds' => 0,
            'created_at' => now(),
        ]);
    }

    return $sessionId;
}

it('notifies only the session participant and teacher, not every student in the course', function (): void {
    $organizationId = Fixtures::organizationId();
    $courseId = Fixtures::courseId();
    $programId = DB::table('courses')
        ->join('levels', 'levels.id', '=', 'courses.level_id')
        ->where('courses.id', $courseId)
        ->value('levels.program_id');

    $participantProfileId = fanoutEnrolledStudent($courseId, (string) $programId);
    $bystanderProfileId = fanoutEnrolledStudent($courseId, (string) $programId);

    $staffProfileId = Fixtures::staffProfileId();
    $sessionId = fanoutScheduleSession($courseId, $staffProfileId, $participantProfileId);

    $participantUserId = DB::table('student_profiles')->where('id', $participantProfileId)->value('user_id');
    $bystanderUserId = DB::table('student_profiles')->where('id', $bystanderProfileId)->value('user_id');
    $teacherUserId = DB::table('staff_profiles')->where('id', $staffProfileId)->value('user_id');

    $recipients = app(DomainEventRecipientResolver::class)->resolve(
        eventKey: 'session.scheduled',
        audiences: ['student', 'guardian', 'teacher'],
        recipientFields: ['student_user_ids', 'guardian_user_ids', 'teacher_user_id'],
        payload: [
            'organization_id' => $organizationId,
            'session_id' => $sessionId,
            'course_id' => $courseId,
            'staff_profile_id' => $staffProfileId,
            'group_id' => null,
        ],
    );

    expect($recipients)->toContain((string) $participantUserId)
        ->and($recipients)->toContain((string) $teacherUserId)
        ->and($recipients)->not->toContain((string) $bystanderUserId)
        ->and($recipients)->toHaveCount(2);
});

it('still reaches the whole course for course-scoped events that carry no session', function (): void {
    $organizationId = Fixtures::organizationId();
    $courseId = Fixtures::courseId();
    $programId = DB::table('courses')
        ->join('levels', 'levels.id', '=', 'courses.level_id')
        ->where('courses.id', $courseId)
        ->value('levels.program_id');

    $firstProfileId = fanoutEnrolledStudent($courseId, (string) $programId);
    $secondProfileId = fanoutEnrolledStudent($courseId, (string) $programId);

    $firstUserId = DB::table('student_profiles')->where('id', $firstProfileId)->value('user_id');
    $secondUserId = DB::table('student_profiles')->where('id', $secondProfileId)->value('user_id');

    $recipients = app(DomainEventRecipientResolver::class)->resolve(
        eventKey: 'course.material.uploaded',
        audiences: ['student', 'guardian'],
        recipientFields: [],
        payload: [
            'organization_id' => $organizationId,
            'course_id' => $courseId,
            'group_id' => null,
        ],
    );

    expect($recipients)->toContain((string) $firstUserId)
        ->and($recipients)->toContain((string) $secondUserId);
});

it('keeps schedule events to their named recipients instead of the whole course', function (): void {
    $organizationId = Fixtures::organizationId();
    $courseId = Fixtures::courseId();
    $programId = DB::table('courses')
        ->join('levels', 'levels.id', '=', 'courses.level_id')
        ->where('courses.id', $courseId)
        ->value('levels.program_id');

    $namedProfileId = fanoutEnrolledStudent($courseId, (string) $programId);
    $bystanderProfileId = fanoutEnrolledStudent($courseId, (string) $programId);

    $namedUserId = (string) DB::table('student_profiles')->where('id', $namedProfileId)->value('user_id');
    $bystanderUserId = (string) DB::table('student_profiles')->where('id', $bystanderProfileId)->value('user_id');

    $staffProfileId = Fixtures::staffProfileId();
    $teacherUserId = (string) DB::table('staff_profiles')->where('id', $staffProfileId)->value('user_id');

    $recipients = app(DomainEventRecipientResolver::class)->resolve(
        eventKey: 'schedule.created',
        audiences: ['student', 'teacher'],
        recipientFields: ['student_user_ids', 'teacher_user_id'],
        payload: [
            'organization_id' => $organizationId,
            'schedule_id' => (string) Str::ulid(),
            'course_id' => $courseId,
            'staff_profile_id' => $staffProfileId,
            'student_user_ids' => [$namedUserId],
            'teacher_user_id' => $teacherUserId,
        ],
    );

    expect($recipients)->toContain($namedUserId)
        ->and($recipients)->toContain($teacherUserId)
        ->and($recipients)->not->toContain($bystanderUserId);
});
