<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Identity\Domain\Models\User;
use Modules\Staff\Domain\Enums\TeacherAvailabilityApprovalStatus;
use Tests\TestCase;

/**
 * مسارات الكتابة في بوابتَي الطالب والمعلم.
 *
 * سبب وجود هذه المسارات أصلًا: مجموعة `api` لا تحمل جلسة ولا كوكيز، فمتحكمات
 * الموديولات خلف `auth:sanctum` لا تتعرّف على مستخدم البوابة، وتُرجع JSON لا
 * تفهمه Inertia. الاختبارات هنا تثبت أن المسارات الجديدة تحفظ فعلًا، وتحترم
 * الصلاحيات، ولا تسمح لمعلم بالكتابة على ملف زميله.
 */
final class PortalWriteRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_submits_an_assignment_and_the_submission_is_persisted(): void
    {
        Gate::define('assignment.submit', static fn (): bool => true);
        Gate::define('view', static fn (): bool => true);

        $context = $this->academicContext();

        $this->actingAs($context['student_user'])
            ->from('/student/assignments')
            ->post('/student/assignments/'.$context['assignment_id'].'/submit', [
                'content' => 'حفظت من الآية الأولى إلى العاشرة.',
            ])
            ->assertRedirect('/student/assignments')
            ->assertSessionHas('success');

        $submission = DB::table('assignment_submissions')
            ->where('assignment_id', $context['assignment_id'])
            ->where('student_profile_id', $context['student_profile_id'])
            ->first();

        $this->assertNotNull($submission);
        $this->assertSame('submitted', $submission->status);
        $this->assertSame('حفظت من الآية الأولى إلى العاشرة.', $submission->content);
        $this->assertNotNull($submission->submitted_at);
    }

    public function test_a_student_outside_the_assignment_audience_is_forbidden(): void
    {
        Gate::define('assignment.submit', static fn (): bool => true);
        Gate::define('view', static fn (): bool => true);

        $context = $this->academicContext();

        // مستخدم بلا ملف طالب ولا مشاركة في حصص الدورة.
        $outsider = $this->persistedUser($context['organization_id'], [
            'email' => 'outsider@example.test',
        ]);

        $this->actingAs($outsider)
            ->post('/student/assignments/'.$context['assignment_id'].'/submit', [
                'content' => 'محاولة من خارج جمهور التكليف.',
            ])
            ->assertForbidden();

        $this->assertSame(
            0,
            DB::table('assignment_submissions')
                ->where('assignment_id', $context['assignment_id'])
                ->count(),
        );
    }

    public function test_empty_submission_is_rejected_by_validation(): void
    {
        Gate::define('assignment.submit', static fn (): bool => true);
        Gate::define('view', static fn (): bool => true);

        $context = $this->academicContext();

        $this->actingAs($context['student_user'])
            ->from('/student/assignments')
            ->post('/student/assignments/'.$context['assignment_id'].'/submit', [
                'content' => '',
            ])
            ->assertRedirect('/student/assignments')
            ->assertSessionHasErrors('content');
    }

    public function test_teacher_availability_is_written_to_the_own_profile_only(): void
    {
        Gate::define('staff.availability.create', static fn (): bool => true);

        $context = $this->academicContext();
        $otherStaffProfileId = $this->createStaffProfile(
            $context['organization_id'],
            'OTHER-TEACHER',
            'other.teacher@example.test',
        );

        $this->actingAs($context['teacher_user'])
            ->from('/teacher/availability')
            ->post('/teacher/availability', [
                // قيمة مدسوسة تستهدف ملف معلم آخر — يجب أن تُتجاهل تمامًا.
                'staff_profile_id' => $otherStaffProfileId,
                'weekday' => 2,
                'start_time' => '16:00',
                'end_time' => '18:00',
                'timezone' => 'Africa/Cairo',
                'effective_from' => CarbonImmutable::now('UTC')->toDateString(),
            ])
            ->assertRedirect('/teacher/availability')
            ->assertSessionHas('success');

        $this->assertSame(
            1,
            DB::table('teacher_availability')
                ->where('staff_profile_id', $context['staff_profile_id'])
                ->count(),
        );

        $this->assertSame(
            0,
            DB::table('teacher_availability')
                ->where('staff_profile_id', $otherStaffProfileId)
                ->count(),
        );
    }

    public function test_availability_requires_the_permission(): void
    {
        Gate::define('staff.availability.create', static fn (): bool => false);

        $context = $this->academicContext();

        $this->actingAs($context['teacher_user'])
            ->post('/teacher/availability', [
                'weekday' => 1,
                'start_time' => '10:00',
                'end_time' => '11:00',
                'timezone' => 'UTC',
                'effective_from' => CarbonImmutable::now('UTC')->toDateString(),
            ])
            ->assertForbidden();

        $this->assertSame(0, DB::table('teacher_availability')->count());
    }

    public function test_teacher_cannot_delete_approved_availability(): void
    {
        Gate::define('staff.availability.create', static fn (): bool => true);

        $context = $this->academicContext();

        $pendingId = $this->createAvailability(
            $context['staff_profile_id'],
            TeacherAvailabilityApprovalStatus::Pending,
        );
        $approvedId = $this->createAvailability(
            $context['staff_profile_id'],
            TeacherAvailabilityApprovalStatus::Approved,
            weekday: 3,
        );

        $this->actingAs($context['teacher_user'])
            ->from('/teacher/availability')
            ->delete('/teacher/availability/'.$pendingId)
            ->assertRedirect('/teacher/availability')
            ->assertSessionHas('success');

        $this->assertNull(DB::table('teacher_availability')->find($pendingId));

        // المعتمدة داخلة في الجدولة، فسحبها قرار إشرافي لا تراجع ذاتي.
        $this->actingAs($context['teacher_user'])
            ->from('/teacher/availability')
            ->delete('/teacher/availability/'.$approvedId)
            ->assertRedirect('/teacher/availability')
            ->assertSessionHas('error');

        $this->assertNotNull(DB::table('teacher_availability')->find($approvedId));
    }

    public function test_teacher_cannot_delete_another_teachers_availability(): void
    {
        Gate::define('staff.availability.create', static fn (): bool => true);

        $context = $this->academicContext();
        $otherStaffProfileId = $this->createStaffProfile(
            $context['organization_id'],
            'OTHER-TEACHER',
            'other.teacher@example.test',
        );
        $foreignId = $this->createAvailability(
            $otherStaffProfileId,
            TeacherAvailabilityApprovalStatus::Pending,
        );

        $this->actingAs($context['teacher_user'])
            ->delete('/teacher/availability/'.$foreignId)
            ->assertNotFound();

        $this->assertNotNull(DB::table('teacher_availability')->find($foreignId));
    }

    public function test_student_submits_a_session_apology_through_the_portal(): void
    {
        Gate::define('session.postpone.request', static fn (): bool => true);

        $context = $this->academicContext();

        $this->actingAs($context['student_user'])
            ->from('/student/sessions/'.$context['session_id'])
            ->post('/student/sessions/'.$context['session_id'].'/apologies', [
                'reason' => 'موعد طبي معروف قبل الحصة.',
            ])
            ->assertRedirect('/student/sessions/'.$context['session_id'])
            ->assertSessionHas('success');

        $participant = DB::table('session_participants')
            ->where('session_id', $context['session_id'])
            ->where('student_profile_id', $context['student_profile_id'])
            ->sole();

        $this->assertNotNull($participant->excused_at);
        $this->assertSame((string) $context['student_user']->getKey(), $participant->excused_by);
        $this->assertSame('موعد طبي معروف قبل الحصة.', $participant->excuse_reason);
        $this->assertSame('scheduled', DB::table('sessions')->find($context['session_id'])->status);
    }

    public function test_student_session_apology_requires_the_postponement_request_permission(): void
    {
        Gate::define('session.postpone.request', static fn (): bool => false);

        $context = $this->academicContext();

        $this->actingAs($context['student_user'])
            ->post('/student/sessions/'.$context['session_id'].'/apologies', [
                'reason' => 'محاولة بلا صلاحية.',
            ])
            ->assertForbidden();

        $this->actingAs($context['student_user'])
            ->post('/student/postponements/'.Str::ulid().'/accept-alternative')
            ->assertForbidden();

        $this->assertNull(
            DB::table('session_participants')
                ->where('session_id', $context['session_id'])
                ->value('excused_at'),
        );
    }

    public function test_user_without_a_student_profile_cannot_apologize_for_a_session(): void
    {
        Gate::define('session.postpone.request', static fn (): bool => true);

        $context = $this->academicContext();
        $outsider = $this->persistedUser($context['organization_id'], [
            'email' => 'apology.outsider@example.test',
        ]);

        $this->actingAs($outsider)
            ->post('/student/sessions/'.$context['session_id'].'/apologies', [
                'reason' => 'محاولة اعتذار عن طالب آخر.',
            ])
            ->assertForbidden();

        $this->assertNull(
            DB::table('session_participants')
                ->where('session_id', $context['session_id'])
                ->value('excused_at'),
        );
    }

    public function test_student_requests_postponement_and_assigned_teacher_approves_it(): void
    {
        Gate::define('session.postpone.request', static fn (): bool => true);
        Gate::define('session.postpone.approve', static fn (): bool => true);

        $context = $this->academicContext();
        $proposedStart = CarbonImmutable::now('UTC')->addDays(2);

        $this->actingAs($context['student_user'])
            ->from('/student/sessions/'.$context['session_id'])
            ->post('/student/sessions/'.$context['session_id'].'/postponement-requests', [
                'proposed_start' => $proposedStart->toIso8601String(),
                'reason' => 'لدي اختبار في الموعد الأصلي.',
            ])
            ->assertRedirect('/student/sessions/'.$context['session_id'])
            ->assertSessionHas('success');

        $request = DB::table('postponement_requests')
            ->where('session_id', $context['session_id'])
            ->sole();

        $this->assertSame('requested', $request->status);
        $this->assertFalse((bool) $request->requires_admin_review);

        $this->actingAs($context['teacher_user'])
            ->from('/teacher/postponements')
            ->post('/teacher/postponements/'.$request->id.'/approve')
            ->assertRedirect('/teacher/postponements')
            ->assertSessionHas('success');

        $approved = DB::table('postponement_requests')->find($request->id);

        $this->assertSame('scheduled', $approved->status);
        $this->assertNotNull($approved->makeup_session_id);
        $this->assertSame('postponed', DB::table('sessions')->find($context['session_id'])->status);
    }

    public function test_assigned_teacher_postpones_a_session_without_admin_approval(): void
    {
        Gate::define('session.postpone.request', static fn (): bool => true);

        $context = $this->academicContext();
        $proposedStart = CarbonImmutable::now('UTC')->addDays(2);

        $this->actingAs($context['teacher_user'])
            ->from('/teacher/sessions/'.$context['session_id'])
            ->post('/teacher/sessions/'.$context['session_id'].'/postponement-requests', [
                'proposed_start' => $proposedStart->toIso8601String(),
                'reason' => 'ارتباط طارئ للمعلم.',
            ])
            ->assertRedirect('/teacher/sessions/'.$context['session_id'])
            ->assertSessionHas('success');

        $request = DB::table('postponement_requests')
            ->where('session_id', $context['session_id'])
            ->sole();

        $this->assertSame('scheduled', $request->status);
        $this->assertFalse((bool) $request->requires_admin_review);
        $this->assertNull($request->requested_for_student_id);
        $this->assertNotNull($request->makeup_session_id);
        $this->assertSame('postponed', DB::table('sessions')->find($context['session_id'])->status);
    }

    public function test_portal_profile_update_persists_the_editable_fields_only(): void
    {
        $context = $this->academicContext();
        $user = $context['student_user'];
        $originalEmail = $user->email;

        $this->actingAs($user)
            ->from('/student/profile')
            ->patch('/student/profile', [
                'name' => 'الاسم الجديد',
                'phone' => '+201234567890',
                'locale' => 'en',
                'timezone' => 'Europe/Paris',
                // محاولة تعديل حقل غير مسموح — يجب ألا يمر.
                'email' => 'hijacked@example.test',
            ])
            ->assertRedirect('/student/profile')
            ->assertSessionHas('success');

        $fresh = $user->fresh();

        $this->assertSame('الاسم الجديد', $fresh->name);
        $this->assertSame('+201234567890', $fresh->phone);
        $this->assertSame('en', $fresh->locale);
        $this->assertSame('Europe/Paris', $fresh->timezone);
        $this->assertSame($originalEmail, $fresh->email);
    }

    public function test_password_change_requires_the_current_password(): void
    {
        $context = $this->academicContext();
        $user = $context['student_user'];

        $user->forceFill(['password' => Hash::make('current-password-1')])->save();

        $this->actingAs($user->fresh())
            ->from('/student/profile')
            ->put('/student/profile/password', [
                'current_password' => 'wrong-password',
                'password' => 'a-brand-new-password-9',
                'password_confirmation' => 'a-brand-new-password-9',
            ])
            ->assertSessionHasErrors();

        $this->assertTrue(Hash::check('current-password-1', (string) $user->fresh()->password));

        $this->actingAs($user->fresh())
            ->from('/student/profile')
            ->put('/student/profile/password', [
                'current_password' => 'current-password-1',
                'password' => 'a-brand-new-password-9',
                'password_confirmation' => 'a-brand-new-password-9',
            ])
            ->assertRedirect('/student/profile')
            ->assertSessionHas('success');

        $this->assertTrue(Hash::check('a-brand-new-password-9', (string) $user->fresh()->password));
    }

    /**
     * منظومة صغيرة متسقة: مؤسسة · برنامج · مستوى · دورة · مجموعة · معلم · طالب
     * · حصة يشارك فيها الطالب · تكليف على نفس الدورة والمجموعة.
     *
     * جمهور التكليف يُشتق من مشاركة الطالب في حصص الدورة، لذلك لا يكفي إنشاء
     * التكليف وحده لجعل الطالب مخوّلًا بالتسليم.
     *
     * @return array<string, mixed>
     */
    private function academicContext(): array
    {
        $organizationId = $this->createOrganization();

        $studentUser = $this->persistedUser($organizationId, [
            'email' => 'portal.student@example.test',
        ]);
        $teacherUser = $this->persistedUser($organizationId, [
            'email' => 'portal.teacher@example.test',
        ]);

        $studentProfileId = (string) Str::ulid();
        DB::table('student_profiles')->insert([
            'id' => $studentProfileId,
            'organization_id' => $organizationId,
            'user_id' => (string) $studentUser->getKey(),
            'student_code' => 'PW-STUDENT-001',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $staffProfileId = $this->createStaffProfile(
            $organizationId,
            'PW-TEACHER-001',
            null,
            (string) $teacherUser->getKey(),
        );

        $programId = (string) Str::ulid();
        DB::table('programs')->insert([
            'id' => $programId,
            'organization_id' => $organizationId,
            'code' => 'PW-PROG-'.Str::upper(Str::random(6)),
            'name' => json_encode(['ar' => 'برنامج', 'en' => 'Program'], JSON_THROW_ON_ERROR),
            'default_session_minutes' => 60,
            'currency' => 'EGP',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $levelId = (string) Str::ulid();
        DB::table('levels')->insert([
            'id' => $levelId,
            'program_id' => $programId,
            'code' => 'L1',
            'name' => json_encode(['ar' => 'المستوى الأول', 'en' => 'Level 1'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);

        $courseId = (string) Str::ulid();
        DB::table('courses')->insert([
            'id' => $courseId,
            'organization_id' => $organizationId,
            'level_id' => $levelId,
            'code' => 'PW-COURSE-'.Str::upper(Str::random(6)),
            'name' => json_encode(['ar' => 'دورة', 'en' => 'Course'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $groupId = (string) Str::ulid();
        DB::table('groups')->insert([
            'id' => $groupId,
            'organization_id' => $organizationId,
            'code' => 'PW-GROUP-'.Str::upper(Str::random(6)),
            'name' => json_encode(['ar' => 'مجموعة', 'en' => 'Group'], JSON_THROW_ON_ERROR),
            'capacity' => 6,
            'timezone' => 'Africa/Cairo',
            'status' => 'active',
            'starts_on' => CarbonImmutable::now('UTC')->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('group_memberships')->insert([
            'id' => (string) Str::ulid(),
            'group_id' => $groupId,
            'student_profile_id' => $studentProfileId,
            'joined_at' => now(),
            'status' => 'active',
            'created_at' => now(),
        ]);

        DB::table('group_teachers')->insert([
            'id' => (string) Str::ulid(),
            'group_id' => $groupId,
            'staff_profile_id' => $staffProfileId,
            'course_id' => $courseId,
            'role' => 'lead',
            'assigned_from' => CarbonImmutable::now('UTC')->toDateString(),
            'created_at' => now(),
        ]);

        $enrollmentId = (string) Str::ulid();
        DB::table('enrollments')->insert([
            'id' => $enrollmentId,
            'organization_id' => $organizationId,
            'student_profile_id' => $studentProfileId,
            'program_id' => $programId,
            'status' => 'active',
            'applied_at' => now(),
            'activated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sessionId = (string) Str::ulid();
        DB::table('sessions')->insert([
            'id' => $sessionId,
            'organization_id' => $organizationId,
            'group_id' => $groupId,
            'course_id' => $courseId,
            'staff_profile_id' => $staffProfileId,
            'original_teacher_id' => $staffProfileId,
            'session_type' => 'group',
            'title' => json_encode(['ar' => 'حصة', 'en' => 'Session'], JSON_THROW_ON_ERROR),
            'status' => 'scheduled',
            'scheduled_start' => CarbonImmutable::now('UTC')->addDay(),
            'scheduled_end' => CarbonImmutable::now('UTC')->addDay()->addHour(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('session_participants')->insert([
            'id' => (string) Str::ulid(),
            'session_id' => $sessionId,
            'student_profile_id' => $studentProfileId,
            'enrollment_id' => $enrollmentId,
            'join_url_token' => (string) Str::ulid(),
            'invited_at' => now(),
            'created_at' => now(),
        ]);

        $assignmentId = (string) Str::ulid();
        DB::table('assignments')->insert([
            'id' => $assignmentId,
            'organization_id' => $organizationId,
            'course_id' => $courseId,
            'group_id' => $groupId,
            'staff_profile_id' => $staffProfileId,
            'title' => json_encode(['ar' => 'تكليف', 'en' => 'Assignment'], JSON_THROW_ON_ERROR),
            'instructions' => json_encode(['ar' => 'احفظ', 'en' => 'Memorise'], JSON_THROW_ON_ERROR),
            'attachments' => json_encode([], JSON_THROW_ON_ERROR),
            'assigned_at' => CarbonImmutable::now('UTC')->subDay(),
            'due_at' => CarbonImmutable::now('UTC')->addDays(3),
            'max_score' => 10,
            'allows_late' => true,
            'late_penalty_percent' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'organization_id' => $organizationId,
            'student_user' => $studentUser,
            'teacher_user' => $teacherUser,
            'student_profile_id' => $studentProfileId,
            'staff_profile_id' => $staffProfileId,
            'course_id' => $courseId,
            'group_id' => $groupId,
            'session_id' => $sessionId,
            'assignment_id' => $assignmentId,
        ];
    }

    private function createStaffProfile(
        string $organizationId,
        string $staffCode,
        ?string $email = null,
        ?string $userId = null,
    ): string {
        if ($userId === null) {
            $userId = (string) $this->persistedUser($organizationId, [
                'email' => $email ?? Str::lower(Str::random(10)).'@example.test',
            ])->getKey();
        }

        $staffProfileId = (string) Str::ulid();

        DB::table('staff_profiles')->insert([
            'id' => $staffProfileId,
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'staff_code' => $staffCode.'-'.Str::upper(Str::random(5)),
            'employment_type' => 'part_time',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $staffProfileId;
    }

    private function createAvailability(
        string $staffProfileId,
        TeacherAvailabilityApprovalStatus $status,
        int $weekday = 1,
    ): string {
        $id = (string) Str::ulid();

        DB::table('teacher_availability')->insert([
            'id' => $id,
            'staff_profile_id' => $staffProfileId,
            'weekday' => $weekday,
            'start_time' => '09:00',
            'end_time' => '10:00',
            'timezone' => 'UTC',
            'effective_from' => CarbonImmutable::now('UTC')->toDateString(),
            'approval_status' => $status->value,
            'created_at' => now(),
        ]);

        return $id;
    }

    private function createOrganization(): string
    {
        $id = (string) Str::ulid();

        DB::table('organizations')->insert([
            'id' => $id,
            'name' => json_encode(['ar' => 'مدرسة', 'en' => 'School'], JSON_THROW_ON_ERROR),
            'slug' => 'portal-write-'.strtolower((string) Str::ulid()),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function persistedUser(string $organizationId, array $attributes = []): User
    {
        return User::factory()
            ->inOrganization($organizationId)
            ->create($attributes);
    }
}
