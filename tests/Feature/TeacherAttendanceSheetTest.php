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
use Tests\TestCase;

/**
 * كشف حضور الحصة في بوابة المعلم.
 *
 * كانت الصفحة ترسله بـPUT إلى `api/sessions/{session}/attendance` — مسار POST
 * يرصد دخول مشارك واحد ويعيد JSON. ثلاثة اختلافات في نداء واحد: الفعل (405)،
 * والشكل (participant_id + type مقابل خريطة حالات)، والرد (JSON لا تفهمه
 * Inertia). هذه الاختبارات تحرس المسار البديل.
 */
final class TeacherAttendanceSheetTest extends TestCase
{
    use RefreshDatabase;

    public function test_put_is_rejected_as_the_page_used_to_send_it(): void
    {
        $context = $this->sessionContext();

        $this->actingAs($context['teacher_user'])
            ->put('/teacher/sessions/'.$context['session_id'].'/attendance', ['statuses' => []])
            ->assertStatus(405);
    }

    public function test_the_sheet_is_saved_and_redirects_instead_of_returning_json(): void
    {
        Gate::define('attendance.record', static fn (): bool => true);

        $context = $this->sessionContext();

        $this->actingAs($context['teacher_user'])
            ->from('/teacher/sessions/'.$context['session_id'])
            ->post('/teacher/sessions/'.$context['session_id'].'/attendance', [
                'statuses' => [$context['student_profile_id'] => AttendanceStatus::Late->value],
                'reason' => 'تأخر بسبب انقطاع الإنترنت، أكّده ولي الأمر.',
            ])
            ->assertRedirect('/teacher/sessions/'.$context['session_id'])
            ->assertSessionHas('success');

        $attendance = Attendance::query()
            ->where('session_participant_id', $context['participant_id'])
            ->firstOrFail();

        $this->assertSame(AttendanceStatus::Late, $attendance->status);
    }

    public function test_overriding_a_recorded_status_without_a_reason_is_refused(): void
    {
        Gate::define('attendance.record', static fn (): bool => true);

        $context = $this->sessionContext();

        // رصد أولي حاضر، ثم محاولة قلبه إلى غائب بلا سبب.
        $this->actingAs($context['teacher_user'])->post(
            '/teacher/sessions/'.$context['session_id'].'/attendance',
            ['statuses' => [$context['student_profile_id'] => AttendanceStatus::Present->value]],
        );

        $this->actingAs($context['teacher_user'])->post(
            '/teacher/sessions/'.$context['session_id'].'/attendance',
            ['statuses' => [$context['student_profile_id'] => AttendanceStatus::Absent->value]],
        )->assertRedirect()->assertSessionHasErrors();

        $attendance = Attendance::query()
            ->where('session_participant_id', $context['participant_id'])
            ->firstOrFail();

        $this->assertSame(AttendanceStatus::Present, $attendance->status);
    }

    public function test_a_student_outside_the_session_is_refused(): void
    {
        Gate::define('attendance.record', static fn (): bool => true);

        $context = $this->sessionContext();

        $this->actingAs($context['teacher_user'])->post(
            '/teacher/sessions/'.$context['session_id'].'/attendance',
            [
                'statuses' => [(string) Str::ulid() => AttendanceStatus::Present->value],
                'reason' => 'محاولة رصد طالب من مجموعة أخرى.',
            ],
        )->assertRedirect()->assertSessionHasErrors();
    }

    public function test_assigned_teacher_without_verified_room_presence_cannot_change_the_sheet(): void
    {
        Gate::define('attendance.record', static fn (): bool => true);

        $context = $this->sessionContext(teacherPresent: false);

        $this->actingAs($context['teacher_user'])
            ->from('/teacher/sessions/'.$context['session_id'])
            ->post('/teacher/sessions/'.$context['session_id'].'/attendance', [
                'statuses' => [$context['student_profile_id'] => AttendanceStatus::Present->value],
            ])
            ->assertRedirect('/teacher/sessions/'.$context['session_id'])
            ->assertSessionHasErrors('business_rule');

        $this->assertFalse(
            Attendance::query()->where('session_participant_id', $context['participant_id'])->exists(),
        );
    }

    public function test_a_status_outside_the_enum_is_refused(): void
    {
        Gate::define('attendance.record', static fn (): bool => true);

        $context = $this->sessionContext();

        $this->actingAs($context['teacher_user'])->post(
            '/teacher/sessions/'.$context['session_id'].'/attendance',
            ['statuses' => [$context['student_profile_id'] => 'teleported']],
        )->assertSessionHasErrors('statuses.'.$context['student_profile_id']);
    }

    /**
     * @return array<string, string>
     */
    private function sessionContext(bool $teacherPresent = true): array
    {
        $now = CarbonImmutable::now('UTC');
        $organizationId = (string) Str::ulid();

        DB::table('organizations')->insert([
            'id' => $organizationId,
            'name' => json_encode(['ar' => 'أكاديمية الاختبار'], JSON_UNESCAPED_UNICODE),
            'slug' => 'attendance-sheet-'.Str::lower(Str::random(8)),
            'default_timezone' => 'UTC',
            'default_currency' => 'EGP',
            'default_locale' => 'ar',
            'week_starts_on' => 'saturday',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $teacherUserId = $this->user($organizationId, 'teacher.sheet');
        $studentUserId = $this->user($organizationId, 'student.sheet');

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
            'status' => 'completed',
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
                'external_id' => 'ATTENDANCE-'.$sessionId,
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
            'teacher_user' => User::query()->findOrFail($teacherUserId),
            'session_id' => $sessionId,
            'participant_id' => $participantId,
            'student_profile_id' => $studentProfileId,
        ];
    }

    private function user(string $organizationId, string $prefix): string
    {
        $id = (string) Str::ulid();
        $suffix = Str::lower(Str::random(6));

        DB::table('users')->insert([
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
}
