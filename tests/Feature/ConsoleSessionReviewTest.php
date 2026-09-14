<?php

declare(strict_types=1);

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Identity\Domain\Models\User;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Tests\TestCase;

/**
 * الطرف البشري والآلي من سلسلة المستحقات.
 *
 * الحصة لا تولّد قيدة إلا في حالة نهائية، ولم يكن هناك أي مسار يوصلها إليها:
 * لا زر في Console ولا أمر مجدول. فكانت الحصص تتراكم في `awaiting_review`،
 * والحصص التي لم يفتحها المعلم من المنصة تبقى `scheduled` إلى الأبد، وكلاهما
 * يساوي صفرًا في كل عدّاد بينما العمل قد تم.
 */
final class ConsoleSessionReviewTest extends TestCase
{
    use RefreshDatabase;

    private int $slot = 0;

    /** @var list<string> */
    private array $permissions = ['admin.panel.access', 'session.view', 'session.finalize', 'attendance.record', 'session.cancel', 'student.view.any', 'staff.view.any'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['console.enabled' => true]);
        CarbonImmutable::setTestNow('2026-09-14T18:00:00Z');
        foreach ($this->permissions as $permission) {
            Gate::define($permission, fn (): bool => true);
        }
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_the_screen_lists_both_pending_review_and_sessions_that_never_started(): void
    {
        $context = $this->context();
        $awaiting = $this->makeSession($context, SessionStatus::AwaitingReview, CarbonImmutable::now('UTC')->subHours(3));
        $stuck = $this->makeSession($context, SessionStatus::Scheduled, CarbonImmutable::now('UTC')->subDay());
        $future = $this->makeSession($context, SessionStatus::Scheduled, CarbonImmutable::now('UTC')->addDay());

        $this->actingAs($context['actor'])
            ->get('/manage/sessions/review')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Console/SessionReview')
                ->has('rows', 2)
                ->where('rows.0.id', $stuck)
                ->where('rows.0.never_started', true)
                ->where('rows.1.id', $awaiting)
                ->where('rows.1.never_started', false));

        self::assertNotSame($future, $stuck);
    }

    public function test_a_decision_needs_a_reason_and_moves_the_session_to_a_terminal_state(): void
    {
        $context = $this->context();
        $sessionId = $this->makeSession($context, SessionStatus::AwaitingReview, CarbonImmutable::now('UTC')->subHours(3));

        $this->actingAs($context['actor'])
            ->from('/manage/sessions/review')
            ->post("/manage/sessions/{$sessionId}/review", [
                'decision' => 'complete',
                'expected_status' => SessionStatus::AwaitingReview->value,
                'reason' => '',
            ])
            ->assertSessionHasErrors('reason');

        self::assertSame(
            SessionStatus::AwaitingReview->value,
            DB::table('sessions')->where('id', $sessionId)->value('status'),
        );

        $this->actingAs($context['actor'])
            ->from('/manage/sessions/review')
            ->post("/manage/sessions/{$sessionId}/review", [
                'decision' => 'complete',
                'expected_status' => SessionStatus::AwaitingReview->value,
                'reason' => 'الحصة أُقيمت وحضر الطرفان.',
            ])
            ->assertRedirect('/manage/sessions/review');

        self::assertSame(
            SessionStatus::Completed->value,
            DB::table('sessions')->where('id', $sessionId)->value('status'),
        );
        self::assertSame(
            1,
            DB::table('audit_log')->where('action', 'sessions.session_completed')->where('auditable_id', $sessionId)->count(),
        );
    }

    public function test_a_decision_built_on_a_stale_screen_is_refused(): void
    {
        $context = $this->context();
        $sessionId = $this->makeSession($context, SessionStatus::AwaitingReview, CarbonImmutable::now('UTC')->subHours(3));

        $this->actingAs($context['actor'])
            ->from('/manage/sessions/review')
            ->post("/manage/sessions/{$sessionId}/review", [
                'decision' => 'complete',
                // زميل اعتمدها بالفعل، فالشاشة التي أمام هذا المستخدم قديمة.
                'expected_status' => SessionStatus::Scheduled->value,
                'reason' => 'اعتماد مبني على شاشة قديمة.',
            ])
            ->assertSessionHasErrors('decision');

        self::assertSame(
            SessionStatus::AwaitingReview->value,
            DB::table('sessions')->where('id', $sessionId)->value('status'),
        );
    }

    public function test_the_scheduled_command_finalizes_only_what_has_a_report_and_full_attendance(): void
    {
        $context = $this->context();
        $complete = $this->makeSession($context, SessionStatus::AwaitingReview, CarbonImmutable::now('UTC')->subDay());
        $noReport = $this->makeSession($context, SessionStatus::AwaitingReview, CarbonImmutable::now('UTC')->subDay());

        $this->participant($context, $complete, 'present');
        $this->report($context, $complete);
        $this->participant($context, $noReport, 'present');
        $this->endedBy($context, $complete);
        $this->endedBy($context, $noReport);

        $this->artisan('sessions:finalize-due')->assertExitCode(0);

        self::assertSame(
            SessionStatus::Completed->value,
            DB::table('sessions')->where('id', $complete)->value('status'),
        );
        self::assertSame(
            SessionStatus::AwaitingReview->value,
            DB::table('sessions')->where('id', $noReport)->value('status'),
            'حصة بلا تقرير اعتُمدت آليًا — الاعتماد الآلي تجاوز شرط اكتمال الدليل.',
        );
    }

    public function test_a_session_whose_participants_were_all_absent_closes_as_no_show_not_completed(): void
    {
        $context = $this->context();
        $sessionId = $this->makeSession($context, SessionStatus::AwaitingReview, CarbonImmutable::now('UTC')->subDay());
        $this->participant($context, $sessionId, 'no_show');
        $this->report($context, $sessionId);
        $this->endedBy($context, $sessionId);

        $this->artisan('sessions:finalize-due')->assertExitCode(0);

        self::assertSame(
            SessionStatus::NoShow->value,
            DB::table('sessions')->where('id', $sessionId)->value('status'),
            'حصة لم يحضرها أحد أُقفلت «مكتملة»، فأثبتت للطالب حضورًا لم يحدث.',
        );
    }

    public function test_the_screen_is_closed_to_a_user_without_the_finalize_permission(): void
    {
        $context = $this->context();
        Gate::define('session.finalize', fn (): bool => false);

        $this->actingAs($context['actor'])->get('/manage/sessions/review')->assertForbidden();
    }

    public function test_a_session_that_never_started_can_still_be_recorded_as_held(): void
    {
        $context = $this->context();
        $sessionId = $this->makeSession($context, SessionStatus::Scheduled, CarbonImmutable::now('UTC')->subDay());

        $this->actingAs($context['actor'])
            ->from('/manage/sessions/review')
            ->post("/manage/sessions/{$sessionId}/review", [
                'decision' => 'complete',
                'expected_status' => SessionStatus::Scheduled->value,
                'reason' => 'الحصة أُقيمت خارج المنصة وأكّدها المعلم والطالب.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/manage/sessions/review');

        $session = DB::table('sessions')->where('id', $sessionId)->first();

        self::assertSame(
            SessionStatus::Completed->value,
            $session->status,
            'حصة لم تُفتح من المنصة تعذّر اعتمادها — وهي أغلب ما تعرضه الشاشة.',
        );
        self::assertSame(
            (string) $session->scheduled_start,
            (string) $session->actual_start,
            'وقت التنفيذ خُتم بلحظة الضغط بدل الموعد المجدول.',
        );

        // المسار المشروع محفوظ خطوة بخطوة، لا قفزة من scheduled إلى completed.
        self::assertSame(
            [SessionStatus::InProgress->value, SessionStatus::AwaitingReview->value, SessionStatus::Completed->value],
            DB::table('session_status_history')->where('session_id', $sessionId)
                ->orderBy('changed_at')->orderBy('id')->pluck('to_status')->all(),
        );
        self::assertSame(
            1,
            DB::table('audit_log')->where('action', 'sessions.session_recorded_off_platform')
                ->where('auditable_id', $sessionId)->count(),
        );
    }

    public function test_a_session_marked_not_held_is_never_finalized_automatically(): void
    {
        $context = $this->context();
        $sessionId = $this->makeSession($context, SessionStatus::AwaitingReview, CarbonImmutable::now('UTC')->subDay());
        $this->participant($context, $sessionId, 'not_held');
        $this->report($context, $sessionId);
        $this->endedBy($context, $sessionId);

        $this->artisan('sessions:finalize-due')->assertExitCode(0);

        self::assertSame(
            SessionStatus::AwaitingReview->value,
            DB::table('sessions')->where('id', $sessionId)->value('status'),
            'حصة مرصودة «لم تُقَم» اعتُمدت آليًا، فدُفع أجر عن حصة لم تحدث.',
        );
    }

    public function test_the_decision_is_refused_without_the_permission_it_needs(): void
    {
        $context = $this->context();
        $sessionId = $this->makeSession($context, SessionStatus::AwaitingReview, CarbonImmutable::now('UTC')->subHours(3));
        Gate::define('session.finalize', fn (): bool => false);

        $this->actingAs($context['actor'])
            ->from('/manage/sessions/review')
            ->post("/manage/sessions/{$sessionId}/review", [
                'decision' => 'complete',
                'expected_status' => SessionStatus::AwaitingReview->value,
                'reason' => 'اعتماد بلا صلاحية.',
            ])
            ->assertForbidden();

        self::assertSame(
            SessionStatus::AwaitingReview->value,
            DB::table('sessions')->where('id', $sessionId)->value('status'),
        );
    }

    /**
     * قاعدة الإنتاج تمنع حجز المعلم مرتين في نفس الفترة (exclusion constraint)،
     * فكل حصة في الاختبار تأخذ فتحة زمنية خاصة بها.
     *
     * @param array<string, mixed> $context
     */
    private function makeSession(array $context, SessionStatus $status, CarbonImmutable $start): string
    {
        $start = $start->addMinutes(30 * $this->slot++);
        $id = (string) Str::ulid();
        DB::table('sessions')->insert([
            'id' => $id,
            'organization_id' => $context['organization_id'],
            'course_id' => $context['course_id'],
            'staff_profile_id' => $context['staff_profile_id'],
            'original_teacher_id' => $context['staff_profile_id'],
            'session_type' => 'individual',
            'title' => json_encode(['ar' => 'حصة', 'en' => 'Session'], JSON_THROW_ON_ERROR),
            'status' => $status->value,
            'scheduled_start' => $start,
            'scheduled_end' => $start->addMinutes(25),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    /** @param array<string, mixed> $context */
    private function participant(array $context, string $sessionId, string $attendanceStatus): void
    {
        $participantId = (string) Str::ulid();
        DB::table('session_participants')->insert([
            'id' => $participantId,
            'session_id' => $sessionId,
            'student_profile_id' => $context['student_profile_id'],
            'enrollment_id' => $context['enrollment_id'],
            'join_url_token' => Str::random(64),
            'invited_at' => now(),
            'created_at' => now(),
        ]);

        DB::table('attendances')->insert([
            'id' => (string) Str::ulid(),
            'session_participant_id' => $participantId,
            'status' => $attendanceStatus,
            'derived_status' => $attendanceStatus,
            'attended_minutes' => $attendanceStatus === 'no_show' ? 0 : 25,
            'joined_after_minutes' => 0,
            'left_before_minutes' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $context */
    private function report(array $context, string $sessionId): void
    {
        DB::table('session_reports')->insert([
            'id' => (string) Str::ulid(),
            'session_id' => $sessionId,
            'staff_profile_id' => $context['staff_profile_id'],
            'topics_covered' => 'مراجعة.',
            'submitted_at' => now(),
            'is_late' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** المنفّذ الذي أنهى الحصة — الأمر المجدول ينسب إليه الانتقال. */
    private function endedBy(array $context, string $sessionId): void
    {
        DB::table('session_status_history')->insert([
            'id' => (string) Str::ulid(),
            'session_id' => $sessionId,
            'from_status' => SessionStatus::InProgress->value,
            'to_status' => SessionStatus::AwaitingReview->value,
            'changed_by' => $context['actor']->getKey(),
            'changed_at' => now(),
            'reason' => 'انتهت الحصة.',
        ]);
    }

    /** @return array<string, mixed> */
    private function context(): array
    {
        $organizationId = (string) Str::ulid();
        DB::table('organizations')->insert([
            'id' => $organizationId,
            'name' => json_encode(['ar' => 'مدرسة', 'en' => 'School'], JSON_THROW_ON_ERROR),
            'slug' => 'review-'.strtolower((string) Str::ulid()),
            'default_timezone' => 'Africa/Cairo',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $actor = User::query()->create([
            'organization_id' => $organizationId,
            'name' => 'مشرف',
            'email' => Str::lower(Str::random(10)).'@example.test',
            'password' => bcrypt('password-for-tests'),
            'locale' => 'ar',
            'timezone' => 'Africa/Cairo',
            'status' => 'active',
        ]);

        $teacherUserId = (string) Str::ulid();
        DB::table('users')->insert([
            'id' => $teacherUserId, 'organization_id' => $organizationId, 'name' => 'معلم',
            'email' => Str::lower(Str::random(10)).'@example.test',
            'password' => bcrypt('password-for-tests'),
            'locale' => 'ar', 'timezone' => 'UTC', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $staffProfileId = (string) Str::ulid();
        DB::table('staff_profiles')->insert([
            'id' => $staffProfileId, 'organization_id' => $organizationId, 'user_id' => $teacherUserId,
            'staff_code' => 'RV-'.Str::upper(Str::random(8)), 'employment_type' => 'part_time',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $programId = (string) Str::ulid();
        DB::table('programs')->insert([
            'id' => $programId, 'organization_id' => $organizationId,
            'code' => 'RV-PROG-'.Str::upper(Str::random(6)),
            'name' => json_encode(['ar' => 'برنامج', 'en' => 'Program'], JSON_THROW_ON_ERROR),
            'default_session_minutes' => 25, 'currency' => 'EGP',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $levelId = (string) Str::ulid();
        DB::table('levels')->insert([
            'id' => $levelId, 'program_id' => $programId, 'code' => 'L1',
            'name' => json_encode(['ar' => 'المستوى', 'en' => 'Level'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);

        $courseId = (string) Str::ulid();
        DB::table('courses')->insert([
            'id' => $courseId, 'organization_id' => $organizationId, 'level_id' => $levelId,
            'code' => 'RV-COURSE-'.Str::upper(Str::random(6)),
            'name' => json_encode(['ar' => 'دورة', 'en' => 'Course'], JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $studentUserId = (string) Str::ulid();
        DB::table('users')->insert([
            'id' => $studentUserId, 'organization_id' => $organizationId, 'name' => 'طالب',
            'email' => Str::lower(Str::random(10)).'@example.test',
            'password' => bcrypt('password-for-tests'),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $studentProfileId = (string) Str::ulid();
        DB::table('student_profiles')->insert([
            'id' => $studentProfileId, 'organization_id' => $organizationId, 'user_id' => $studentUserId,
            'student_code' => 'RV-ST-'.Str::upper(Str::random(6)),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $enrollmentId = (string) Str::ulid();
        DB::table('enrollments')->insert([
            'id' => $enrollmentId, 'organization_id' => $organizationId,
            'student_profile_id' => $studentProfileId, 'program_id' => $programId,
            'status' => 'active', 'applied_at' => now()->subMonth(), 'activated_at' => now()->subMonth(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return [
            'organization_id' => $organizationId,
            'actor' => $actor,
            'staff_profile_id' => $staffProfileId,
            'course_id' => $courseId,
            'student_profile_id' => $studentProfileId,
            'enrollment_id' => $enrollmentId,
        ];
    }
}
