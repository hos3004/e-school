<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Attendance\Domain\Enums\AttendanceStatus;
use Modules\Attendance\Domain\Models\Attendance;
use Modules\Identity\Domain\Models\User;

/**
 * تفاصيل حصة المعلم للموبايل: عرض، رصد كشف حضور، تسليم تقرير — نفس
 * البيانات القاعدية اللي يبنيها tests/Feature/TeacherAttendanceSheetTest.php
 * لأن نفس الأفعال (RecordAttendanceSheetAction وSubmitSessionReportAction)
 * تشترط نفس الشروط بالضبط (حضور المعلم الفعلي في الفصل، تسجيل مسبق، إلخ).
 */
uses(RefreshDatabase::class);

function mobileSessionDetailUser(string $organizationId, string $prefix): string
{
    $id = (string) Str::ulid();
    $suffix = Str::lower(Str::random(6));

    DB::table('users')->insert([
        'profile_completed_at' => now(),
        'id' => $id,
        'organization_id' => $organizationId,
        'name' => $prefix,
        'email' => $prefix.'.'.$suffix.'@example.test',
        'username' => $prefix.'.'.$suffix,
        'password' => Hash::make('secret-password'),
        'locale' => 'ar',
        'timezone' => 'UTC',
        'status' => 'active',
        'created_at' => CarbonImmutable::now('UTC'),
        'updated_at' => CarbonImmutable::now('UTC'),
    ]);

    return $id;
}

/**
 * @return array<string, mixed>
 */
function mobileSessionDetailContext(
    bool $teacherPresent = true,
    string $status = 'completed',
    ?string $organizationId = null,
): array {
    $now = CarbonImmutable::now('UTC');
    $organizationId ??= (string) Str::ulid();
    $isNewOrganization = !DB::table('organizations')->where('id', $organizationId)->exists();

    if ($isNewOrganization) {
        DB::table('organizations')->insert([
            'id' => $organizationId,
            'name' => json_encode(['ar' => 'أكاديمية الاختبار'], JSON_UNESCAPED_UNICODE),
            'slug' => 'mobile-session-detail-'.Str::lower(Str::random(8)),
            'default_timezone' => 'UTC',
            'default_currency' => 'EGP',
            'default_locale' => 'ar',
            'week_starts_on' => 'saturday',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    $teacherUserId = mobileSessionDetailUser($organizationId, 'teacher.detail');
    $studentUserId = mobileSessionDetailUser($organizationId, 'student.detail');

    $staffProfileId = (string) Str::ulid();
    DB::table('staff_profiles')->insert([
        'id' => $staffProfileId,
        'organization_id' => $organizationId,
        'user_id' => $teacherUserId,
        'staff_code' => 'T-'.Str::upper(Str::random(6)),
        'employment_type' => 'part_time',
        'gender' => 'male',
        'hired_at' => $now->subYear()->toDateString(),
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $studentProfileId = (string) Str::ulid();
    DB::table('student_profiles')->insert([
        'id' => $studentProfileId,
        'organization_id' => $organizationId,
        'user_id' => $studentUserId,
        'student_code' => 'S-'.Str::upper(Str::random(6)),
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $programId = (string) Str::ulid();
    DB::table('programs')->insert([
        'id' => $programId,
        'organization_id' => $organizationId,
        'code' => 'P-'.Str::upper(Str::random(5)),
        'name' => json_encode(['ar' => 'برنامج الاختبار'], JSON_UNESCAPED_UNICODE),
        'default_session_minutes' => 60,
        'currency' => 'EGP',
        'is_active' => true,
        'sort_order' => 1,
        'program_type' => 'ongoing',
        'target_gender' => 'all',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $levelId = (string) Str::ulid();
    DB::table('levels')->insert([
        'id' => $levelId,
        'program_id' => $programId,
        'code' => 'L-'.Str::upper(Str::random(5)),
        'name' => json_encode(['ar' => 'المستوى الأول'], JSON_UNESCAPED_UNICODE),
        'sort_order' => 1,
        'created_at' => $now,
    ]);

    $courseId = (string) Str::ulid();
    DB::table('courses')->insert([
        'id' => $courseId,
        'organization_id' => $organizationId,
        'level_id' => $levelId,
        'code' => 'C-'.Str::upper(Str::random(5)),
        'name' => json_encode(['ar' => 'كورس الاختبار'], JSON_UNESCAPED_UNICODE),
        'is_active' => true,
        'session_mode' => 'group',
        'default_duration_minutes' => 60,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $enrollmentId = (string) Str::ulid();
    DB::table('enrollments')->insert([
        'id' => $enrollmentId,
        'organization_id' => $organizationId,
        'student_profile_id' => $studentProfileId,
        'program_id' => $programId,
        'status' => 'active',
        'applied_at' => $now->subMonth(),
        'activated_at' => $now->subMonth(),
        'current_level_id' => $levelId,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $sessionId = (string) Str::ulid();
    DB::table('sessions')->insert([
        'id' => $sessionId,
        'organization_id' => $organizationId,
        'course_id' => $courseId,
        'staff_profile_id' => $staffProfileId,
        'original_teacher_id' => $staffProfileId,
        'session_type' => 'group',
        'status' => $status,
        'scheduled_start' => $now->subHours(3),
        'scheduled_end' => $now->subHours(2),
        'title' => json_encode(['ar' => 'حصة الاختبار'], JSON_UNESCAPED_UNICODE),
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $participantId = (string) Str::ulid();
    DB::table('session_participants')->insert([
        'id' => $participantId,
        'session_id' => $sessionId,
        'student_profile_id' => $studentProfileId,
        'enrollment_id' => $enrollmentId,
        'join_url_token' => Str::random(64),
        'invited_at' => $now->subDay(),
        'attended_minutes' => 0,
        'created_at' => $now,
    ]);

    if ($teacherPresent) {
        $classroomId = (string) Str::ulid();
        DB::table('classrooms')->insert([
            'id' => $classroomId,
            'session_id' => $sessionId,
            'provider' => 'bigbluebutton',
            'external_id' => 'MOBILE-DETAIL-'.$sessionId,
            'moderator_secret' => 'moderator-secret',
            'attendee_secret' => 'attendee-secret',
            'created_remote_at' => $now->subHours(3),
            'status' => 'ended',
            'started_at' => $now->subHours(3),
            'ended_at' => $now->subHours(2),
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
            'occurred_at' => $now->subHours(3)->addMinutes(5),
            'payload' => json_encode(['role' => 'moderator'], JSON_THROW_ON_ERROR),
            'created_at' => $now,
        ]);
    }

    return [
        'organization_id' => $organizationId,
        'teacher_user' => User::query()->findOrFail($teacherUserId),
        'session_id' => $sessionId,
        'participant_id' => $participantId,
        'student_profile_id' => $studentProfileId,
    ];
}

it('shows session details with the students name, course name and attendance sheet', function (): void {
    Gate::define('attendance.record', fn (): bool => true);
    $context = mobileSessionDetailContext();

    $response = test()->actingAs($context['teacher_user'])
        ->getJson('/api/teacher/sessions/'.$context['session_id']);

    $response->assertOk()
        ->assertJsonPath('session.id', $context['session_id'])
        ->assertJsonPath('session.subject', 'كورس الاختبار')
        ->assertJsonCount(1, 'attendance')
        ->assertJsonPath('attendance.0.studentId', $context['student_profile_id'])
        ->assertJsonPath('attendance.0.status', 'pending');

    expect($response->json('attendance_statuses'))->toBeArray()->not->toBeEmpty();
});

it('returns 404 for a session that does not belong to the caller', function (): void {
    Gate::define('attendance.record', fn (): bool => true);
    $context = mobileSessionDetailContext();
    $other = mobileSessionDetailContext();

    test()->actingAs($context['teacher_user'])
        ->getJson('/api/teacher/sessions/'.$other['session_id'])
        ->assertNotFound();
});

it('records the attendance sheet when the teacher was actually present', function (): void {
    Gate::define('attendance.record', fn (): bool => true);
    $context = mobileSessionDetailContext();

    test()->actingAs($context['teacher_user'])
        ->postJson('/api/teacher/sessions/'.$context['session_id'].'/attendance', [
            'statuses' => [$context['student_profile_id'] => AttendanceStatus::Late->value],
            'reason' => 'تأخر بسبب انقطاع الإنترنت.',
        ])
        ->assertOk()
        ->assertJsonStructure(['tally' => ['recorded', 'overridden', 'confirmed']]);

    $attendance = Attendance::query()->where('session_participant_id', $context['participant_id'])->firstOrFail();
    expect($attendance->status)->toBe(AttendanceStatus::Late);
});

it('rejects recording attendance when the teacher was never verified present in the room', function (): void {
    Gate::define('attendance.record', fn (): bool => true);
    $context = mobileSessionDetailContext(teacherPresent: false);

    test()->actingAs($context['teacher_user'])
        ->postJson('/api/teacher/sessions/'.$context['session_id'].'/attendance', [
            'statuses' => [$context['student_profile_id'] => AttendanceStatus::Present->value],
        ])
        ->assertUnprocessable();

    expect(Attendance::query()->where('session_participant_id', $context['participant_id'])->exists())->toBeFalse();
});

it('submits a session report for the assigned teacher', function (): void {
    Gate::define('session_report.create', fn (): bool => true);
    $context = mobileSessionDetailContext();

    test()->actingAs($context['teacher_user'])
        ->postJson('/api/teacher/sessions/'.$context['session_id'].'/report', [
            'summary' => 'راجعنا قواعد الجمع والطرح مع تدريبات تطبيقية.',
            'notes' => null,
            'students' => [[
                'student_profile_id' => $context['student_profile_id'],
                'participation' => 4,
                'performance' => 4,
                'commitment' => 5,
            ]],
        ])
        ->assertOk()
        ->assertJsonPath('status', 'submitted');
});

it('moves a scheduled session to awaiting review when the teacher marks it held off-platform', function (): void {
    Gate::define('session_report.create', fn (): bool => true);
    $context = mobileSessionDetailContext(teacherPresent: false, status: 'scheduled');

    test()->actingAs($context['teacher_user'])
        ->postJson('/api/teacher/sessions/'.$context['session_id'].'/report', [
            'summary' => 'الحصة اتعقدت على واتساب بسبب عطل في المنصة.',
            'notes' => null,
            'held_off_platform' => true,
            'students' => [[
                'student_profile_id' => $context['student_profile_id'],
                'participation' => 4,
                'performance' => 4,
                'commitment' => 5,
            ]],
        ])
        ->assertOk()
        ->assertJsonPath('status', 'submitted');

    $status = DB::table('sessions')->where('id', $context['session_id'])->value('status');
    expect($status)->toBe('awaiting_review');
});

it('leaves a scheduled session untouched when held_off_platform is not set', function (): void {
    // يحافظ على القرار الموثّق: التقرير وحده لا يُغيّر حالة الحصة أبدًا —
    // انظر SubmitSessionReportAction::executeForTeacher.
    Gate::define('session_report.create', fn (): bool => true);
    $context = mobileSessionDetailContext(teacherPresent: false, status: 'scheduled');

    test()->actingAs($context['teacher_user'])
        ->postJson('/api/teacher/sessions/'.$context['session_id'].'/report', [
            'summary' => 'راجعنا قواعد الجمع والطرح مع تدريبات تطبيقية.',
            'notes' => null,
            'students' => [[
                'student_profile_id' => $context['student_profile_id'],
                'participation' => 4,
                'performance' => 4,
                'commitment' => 5,
            ]],
        ])
        ->assertOk();

    $status = DB::table('sessions')->where('id', $context['session_id'])->value('status');
    expect($status)->toBe('scheduled');
});

it('forbids marking a colleagues session held off-platform', function (): void {
    // كان يمكن قبل هذا الفحص لمعلم أن ينقل حصة زميله لبانتظار المراجعة
    // بمجرّد معرفة معرّفها، حتى مع رفض التقرير نفسه لاحقًا لعدم التطابق.
    Gate::define('session_report.create', fn (): bool => true);
    $mine = mobileSessionDetailContext(teacherPresent: false, status: 'scheduled');
    $colleagues = mobileSessionDetailContext(
        teacherPresent: false,
        status: 'scheduled',
        organizationId: $mine['organization_id'],
    );

    test()->actingAs($mine['teacher_user'])
        ->postJson('/api/teacher/sessions/'.$colleagues['session_id'].'/report', [
            'summary' => 'محاولة غير مصرّح بها.',
            'notes' => null,
            'held_off_platform' => true,
            'students' => [[
                'student_profile_id' => $colleagues['student_profile_id'],
                'participation' => 4,
                'performance' => 4,
                'commitment' => 5,
            ]],
        ])
        ->assertForbidden();

    $status = DB::table('sessions')->where('id', $colleagues['session_id'])->value('status');
    expect($status)->toBe('scheduled');
});

it('rejects marking a session held off-platform before its scheduled end has passed', function (): void {
    Gate::define('session_report.create', fn (): bool => true);
    $context = mobileSessionDetailContext(teacherPresent: false, status: 'scheduled');

    DB::table('sessions')->where('id', $context['session_id'])->update([
        'scheduled_start' => CarbonImmutable::now('UTC')->addHours(2),
        'scheduled_end' => CarbonImmutable::now('UTC')->addHours(3),
    ]);

    test()->actingAs($context['teacher_user'])
        ->postJson('/api/teacher/sessions/'.$context['session_id'].'/report', [
            'summary' => 'محاولة مبكرة.',
            'notes' => null,
            'held_off_platform' => true,
            'students' => [[
                'student_profile_id' => $context['student_profile_id'],
                'participation' => 4,
                'performance' => 4,
                'commitment' => 5,
            ]],
        ])
        ->assertUnprocessable();

    $status = DB::table('sessions')->where('id', $context['session_id'])->value('status');
    expect($status)->toBe('scheduled');
});

it('requires authentication for every teacher session detail route', function (): void {
    $context = mobileSessionDetailContext();

    test()->getJson('/api/teacher/sessions/'.$context['session_id'])->assertUnauthorized();
    test()->postJson('/api/teacher/sessions/'.$context['session_id'].'/attendance')->assertUnauthorized();
    test()->postJson('/api/teacher/sessions/'.$context['session_id'].'/report')->assertUnauthorized();
});
