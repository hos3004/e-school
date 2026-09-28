<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Modules\Identity\Domain\Models\User;
use Modules\Scheduling\Application\Actions\ProposePostponementAlternative;
use Modules\Scheduling\Application\Actions\RequestPostponement;
use Modules\Scheduling\Domain\Enums\PostponementStatus;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Shared\Testing\Fixtures;

/**
 * طلب التأجيل والاعتذار للطالب على الموبايل — مرآة
 * Portal\SessionPostponementRequestController::student()/acceptAlternative()
 * وPortal\StudentSessionApologyController بالضبط، نفس الأفعال والقيود التي
 * يثبتها modules/Sessions/tests/Feature/StudentSessionApologyFlowTest.php
 * ومحاكاة modules/Scheduling/tests/Feature/SchedulingOperationsTest.php
 * لدورة اقتراح/قبول البديل.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    Fixtures::flush();
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-06 10:00:00', 'UTC'));
    Gate::define('session.postpone.request', fn (): bool => true);
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

/**
 * @return array{
 *   organizationId: string,
 *   studentUser: User,
 *   studentProfileId: string,
 *   sessionId: string,
 * }
 */
function mobileStudentPostponementFixture(bool $groupSession = true): array
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
            'code' => 'MPOST-'.strtoupper(substr($groupId, -8)),
            'name' => json_encode(['ar' => 'مجموعة الاختبار', 'en' => 'Test group'], JSON_UNESCAPED_UNICODE),
            'capacity' => 10,
            'timezone' => 'UTC',
            'status' => 'active',
            'starts_on' => $now->toDateString(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    $sessionId = (string) Str::ulid();
    DB::table('sessions')->insert([
        'id' => $sessionId,
        'organization_id' => $organizationId,
        'group_id' => $groupId,
        'course_id' => $courseId,
        'staff_profile_id' => $staffProfileId,
        'original_teacher_id' => $staffProfileId,
        'session_type' => $groupSession ? 'group' : 'individual',
        'status' => 'scheduled',
        'scheduled_start' => $now->addHours(2),
        'scheduled_end' => $now->addHours(3),
        'title' => json_encode(['ar' => 'حصة الاختبار'], JSON_UNESCAPED_UNICODE),
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('session_participants')->insert([
        'id' => (string) Str::ulid(),
        'session_id' => $sessionId,
        'student_profile_id' => $studentProfileId,
        'enrollment_id' => $enrollmentId,
        'join_url_token' => Str::random(64),
        'invited_at' => $now,
        'attended_minutes' => 0,
        'created_at' => $now,
    ]);

    return [
        'organizationId' => $organizationId,
        'studentUser' => User::query()->findOrFail($studentUserId),
        'studentProfileId' => $studentProfileId,
        'sessionId' => $sessionId,
    ];
}

it('requests a postponement for the students own upcoming session', function (): void {
    $context = mobileStudentPostponementFixture();

    test()->actingAs($context['studentUser'])
        ->postJson('/api/student/sessions/'.$context['sessionId'].'/postponement-requests', [
            'proposed_start' => CarbonImmutable::now('UTC')->addDays(2)->toIso8601String(),
            'reason' => 'عندي موعد طبي في نفس وقت الحصة.',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'requested');

    expect(DB::table('postponement_requests')
        ->where('session_id', $context['sessionId'])
        ->where('requested_for_student_id', $context['studentProfileId'])
        ->exists())->toBeTrue();
});

it('rejects a postponement request without a reason', function (): void {
    $context = mobileStudentPostponementFixture();

    test()->actingAs($context['studentUser'])
        ->postJson('/api/student/sessions/'.$context['sessionId'].'/postponement-requests', [
            'proposed_start' => CarbonImmutable::now('UTC')->addDays(2)->toIso8601String(),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reason']);
});

it('rejects requesting a postponement for a session the student is not enrolled in', function (): void {
    // RequestPostponement::execute نفسها ترفض هذا بـBusinessRuleViolation (422) —
    // نفس سلوك Portal\SessionPostponementRequestController::student() على الويب
    // بالضبط، لا فحص تفويض منفصل قبلها.
    $mine = mobileStudentPostponementFixture();
    $someoneElses = mobileStudentPostponementFixture();

    test()->actingAs($mine['studentUser'])
        ->postJson('/api/student/sessions/'.$someoneElses['sessionId'].'/postponement-requests', [
            'proposed_start' => CarbonImmutable::now('UTC')->addDays(2)->toIso8601String(),
            'reason' => 'محاولة غير مصرّح بها.',
        ])
        ->assertUnprocessable();

    expect(DB::table('postponement_requests')->where('session_id', $someoneElses['sessionId'])->exists())
        ->toBeFalse();
});

it('submits a group session apology without cancelling the teacher session', function (): void {
    $context = mobileStudentPostponementFixture(groupSession: true);

    test()->actingAs($context['studentUser'])
        ->postJson('/api/student/sessions/'.$context['sessionId'].'/apologies', [
            'reason' => 'ظرف عائلي طارئ.',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'submitted');

    $participant = DB::table('session_participants')->where('session_id', $context['sessionId'])->first();
    expect($participant->excused_at)->not->toBeNull()
        ->and(DB::table('sessions')->where('id', $context['sessionId'])->value('status'))->toBe('scheduled');
});

it('marks an individual session excused when the student apologizes', function (): void {
    $context = mobileStudentPostponementFixture(groupSession: false);

    test()->actingAs($context['studentUser'])
        ->postJson('/api/student/sessions/'.$context['sessionId'].'/apologies', [
            'reason' => 'موعد طبي.',
        ])
        ->assertOk();

    expect(DB::table('sessions')->where('id', $context['sessionId'])->value('status'))
        ->toBe(SessionStatus::Excused->value);
});

it('lets the student accept a teacher-proposed alternative time', function (): void {
    $context = mobileStudentPostponementFixture();

    $request = app(RequestPostponement::class)->execute(
        organizationId: $context['organizationId'],
        sessionId: $context['sessionId'],
        requestedBy: (string) $context['studentUser']->id,
        studentProfileId: $context['studentProfileId'],
        proposedStart: CarbonImmutable::now('UTC')->addDays(2),
        reason: 'عندي موعد طبي.',
    );

    $teacherProposedStart = CarbonImmutable::now('UTC')->addDays(3);
    app(ProposePostponementAlternative::class)->execute(
        $context['organizationId'],
        (string) $request->id,
        (string) $request->requested_by,
        $teacherProposedStart,
        'الموعد المقترح غير متاح، هذا بديل.',
    );

    test()->actingAs($context['studentUser'])
        ->postJson('/api/student/postponements/'.$request->id.'/accept-alternative')
        ->assertOk()
        ->assertJsonPath('status', 'approved');

    expect($request->fresh()->status)->toBe(PostponementStatus::Scheduled);
});

it('rejects accepting an alternative for a postponement that does not belong to the student', function (): void {
    $mine = mobileStudentPostponementFixture();
    $someoneElses = mobileStudentPostponementFixture();

    $request = app(RequestPostponement::class)->execute(
        organizationId: $someoneElses['organizationId'],
        sessionId: $someoneElses['sessionId'],
        requestedBy: (string) $someoneElses['studentUser']->id,
        studentProfileId: $someoneElses['studentProfileId'],
        proposedStart: CarbonImmutable::now('UTC')->addDays(2),
        reason: 'سبب.',
    );
    app(ProposePostponementAlternative::class)->execute(
        $someoneElses['organizationId'],
        (string) $request->id,
        (string) $request->requested_by,
        CarbonImmutable::now('UTC')->addDays(3),
        'بديل.',
    );

    test()->actingAs($mine['studentUser'])
        ->postJson('/api/student/postponements/'.$request->id.'/accept-alternative')
        ->assertForbidden();
});

it('requires authentication for every student postponement and apology route', function (): void {
    $context = mobileStudentPostponementFixture();

    test()->postJson('/api/student/sessions/'.$context['sessionId'].'/postponement-requests')->assertUnauthorized();
    test()->postJson('/api/student/sessions/'.$context['sessionId'].'/apologies')->assertUnauthorized();
    test()->postJson('/api/student/postponements/'.(string) Str::ulid().'/accept-alternative')->assertUnauthorized();
});
