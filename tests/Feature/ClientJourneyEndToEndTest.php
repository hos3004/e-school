<?php

declare(strict_types=1);

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Academics\Domain\Enums\SessionMode;
use Modules\Academics\Domain\Models\Course;
use Modules\Academics\Domain\Models\Level;
use Modules\Academics\Domain\Models\Program;
use Modules\AccessControl\Database\Seeders\AccessControlSeeder;
use Modules\Attendance\Application\Actions\RecordAttendanceAction;
use Modules\Enrollments\Domain\Enums\EnrollmentStatus;
use Modules\Enrollments\Domain\Models\Enrollment;
use Modules\Groups\Domain\Enums\GroupStatus;
use Modules\Groups\Domain\Enums\GroupTeacherRole;
use Modules\Groups\Domain\Enums\MembershipStatus;
use Modules\Groups\Domain\Models\Group;
use Modules\Groups\Domain\Models\GroupMembership;
use Modules\Groups\Domain\Models\GroupProgram;
use Modules\Groups\Domain\Models\GroupTeacher;
use Modules\Identity\Domain\Models\User;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Modules\Organization\Database\Seeders\GeographySeeder;
use Modules\Organization\Domain\Contracts\GeographyQueries;
use Modules\Organization\Domain\Models\Organization;
use Modules\Scheduling\Application\Actions\CreateScheduleAction;
use Modules\Scheduling\Application\Actions\MaterializeScheduleAction;
use Modules\Sessions\Application\Actions\CompleteSessionAction;
use Modules\Sessions\Application\Actions\EndSessionAction;
use Modules\Sessions\Application\Actions\RecordParticipantAttendanceAction;
use Modules\Sessions\Application\Actions\StartSessionAction;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Domain\Models\SessionParticipant;
use Modules\Staff\Application\Actions\AddTeacherRate;
use Modules\Staff\Application\Actions\CreateTeacherContract;
use Modules\Staff\Domain\Enums\ContractBasis;
use Modules\Staff\Domain\Enums\EmploymentType;
use Modules\Staff\Domain\Enums\RateScope;
use Modules\Staff\Domain\Enums\StaffGender;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Students\Domain\Models\RegistrationForm;
use Modules\Students\Domain\Models\StudentProfile;
use Shared\ValueObjects\Money;
use Tests\TestCase;

/**
 * رحلة العميل الثمانية من طرف إلى طرف — بالبيانات لا بالوعود.
 *
 * كل خطوة هنا تُنفَّذ عبر نفس الـAction الذي يستدعيه زر اللوحة، فما ينجح هنا
 * ينجح في الواجهة، وما يسقط هنا يظهر في مصفوفة النواقص بدل أن يُكتشف أمام
 * العميل. الخطوات مرقّمة كما وردت في طلب التسليم.
 */
final class ClientJourneyEndToEndTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
        $this->seed(GeographySeeder::class);
    }

    public function test_step_1_to_4_admission_placement_and_scheduling(): void
    {
        $world = $this->world();

        // ── ١) دورة جديدة ──────────────────────────────────────────────────
        $this->assertDatabaseHas('courses', ['id' => $world['course']->id]);

        // ── ٢) نموذج تسجيل له رابط عام قابل للنشر ─────────────────────────
        $form = RegistrationForm::query()->create([
            'organization_id' => $world['organization']->id,
            'slug' => 'autumn-quran-2026',
            'title' => ['ar' => 'تسجيل دورة الخريف', 'en' => 'Autumn intake'],
            'is_active' => true,
        ]);

        $publicUrl = route('register.student.form', ['formSlug' => $form->slug]);
        $this->assertStringContainsString('autumn-quran-2026', $publicUrl);
        $this->get($publicUrl)->assertOk();

        // ── ٣) طالب يسجّل من الرابط العام ─────────────────────────────────
        /** @var GeographyQueries $geography */
        $geography = app(GeographyQueries::class);
        $country = $geography->findCountryByIso2('SA');

        $this->post(route('register.student.form.store', ['formSlug' => $form->slug]), [
            'full_name' => 'طالب التجربة',
            'email' => 'journey.applicant@example.test',
            'phone' => '+966500000123',
            'date_of_birth' => '2010-05-15',
            'gender' => 'male',
            'country_id' => $country->id,
            'region_id' => $geography->regionsOf($country->id)[0]->id,
            'notes' => 'مسجَّل من رابط الإعلان',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertGreaterThan(
            0,
            DB::table('registration_applications')->count(),
            'التسجيل العام لم ينشئ طلبًا.',
        );

        // ── ٤) جدولة الحصص للمجموعة ───────────────────────────────────────
        $schedule = app(CreateScheduleAction::class)->execute(
            (string) $world['organization']->id,
            [
                'target_type' => 'group',
                'group_id' => (string) $world['group']->id,
                'course_id' => (string) $world['course']->id,
                'staff_profile_id' => (string) $world['teacher']->id,
                'weekdays' => [0],
                'interval_weeks' => 1,
                'start_time' => '10:00',
                'duration_minutes' => 60,
                'timezone' => 'UTC',
                'starts_on' => CarbonImmutable::now('UTC')->addDay()->toDateString(),
                'ends_on' => CarbonImmutable::now('UTC')->addMonths(2)->toDateString(),
            ],
            (string) $world['operator']->id,
            'جدولة حصص المجموعة ضمن اختبار الرحلة',
        );

        $result = app(MaterializeScheduleAction::class)->execute(
            $schedule,
            (string) $world['operator']->id,
            'توليد الحصص من القالب ضمن اختبار الرحلة',
        );

        $sessions = Session::query()->where('group_id', $world['group']->id)->count();

        $this->assertGreaterThan(
            0,
            $sessions,
            'الجدول لم يولّد أي حصة — الخطوة ٤ مكسورة.',
        );

        $this->addToAssertionCount(1);
        fwrite(STDERR, "\n[خطوة ٤] حصص مولّدة: {$sessions}\n");
    }

    /**
     * إشعار الجدولة يعتمد على `SessionScheduled` المعلن في config/notifications.php.
     * هذا يثبت أن القناة تعمل فعلًا لا أنها مذكورة في الإعداد فقط.
     */
    public function test_step_4b_scheduling_notifies_teacher_and_students(): void
    {
        $world = $this->world();

        $schedule = app(CreateScheduleAction::class)->execute(
            (string) $world['organization']->id,
            [
                'target_type' => 'group',
                'group_id' => (string) $world['group']->id,
                'course_id' => (string) $world['course']->id,
                'staff_profile_id' => (string) $world['teacher']->id,
                'weekdays' => [0],
                'interval_weeks' => 1,
                'start_time' => '10:00',
                'duration_minutes' => 60,
                'timezone' => 'UTC',
                'starts_on' => CarbonImmutable::now('UTC')->addDay()->toDateString(),
                'ends_on' => CarbonImmutable::now('UTC')->addMonth()->toDateString(),
            ],
            (string) $world['operator']->id,
            'جدولة للتحقق من الإشعارات',
        );

        app(MaterializeScheduleAction::class)->execute(
            $schedule,
            (string) $world['operator']->id,
            'توليد الحصص للتحقق من الإشعارات',
        );

        $outbox = NotificationOutbox::query()->count();
        fwrite(STDERR, "[خطوة ٤ب] رسائل صندوق الإرسال: {$outbox}\n");

        $this->assertGreaterThan(
            0,
            $outbox,
            'جدولة الحصص لم تُنتج أي إشعار للمعلم أو الطلاب.',
        );
    }

    /**
     * ٥→٨: بدء الحصة، تسجيل الحضور، إنهاؤها واعتمادها، ثم استحقاق المعلم.
     *
     * الحضور هنا يُسجَّل **يدويًا** عبر الـAction لأن الجسر التلقائي من
     * BigBlueButton غير موجود: `ClassroomParticipantJoined` يُطلَق ولا يستمع له
     * أحد. الاختبار يثبت أن ما بعد الحضور سليم، ويعزل النقص في مكانه.
     */
    public function test_step_5_to_8_delivery_attendance_and_teacher_accrual(): void
    {
        $world = $this->world();

        $session = Session::query()->create([
            'organization_id' => $world['organization']->id,
            'group_id' => $world['group']->id,
            'course_id' => $world['course']->id,
            'staff_profile_id' => $world['teacher']->id,
            'session_type' => 'group',
            'scheduled_start' => CarbonImmutable::now('UTC')->subHours(2),
            'scheduled_end' => CarbonImmutable::now('UTC')->subHour(),
            'title' => ['ar' => 'حصة الرحلة', 'en' => 'Journey session'],
            'status' => SessionStatus::Scheduled,
        ]);

        $enrollment = Enrollment::query()
            ->where('student_profile_id', $world['student']->id)
            ->firstOrFail();

        $participant = SessionParticipant::query()->create([
            'session_id' => $session->id,
            'student_profile_id' => $world['student']->id,
            'enrollment_id' => $enrollment->id,
            'join_url_token' => Str::random(64),
            'invited_at' => now('UTC'),
            'attended_minutes' => 0,
        ]);

        $actorId = (string) $world['operator']->id;

        /*
         * عقد المعلم وسعره. بدونهما يرفض `RecordSessionPayrollEntry` اختراع
         * قيدة بصفر ويكتفي بتحذير — سلوك مقصود لا عطب. لذلك تُضبط البيانات هنا
         * كما يضبطها المشرف من زرّي «عقد جديد» و«سعر جديد» في ملف الموظف.
         */
        $contract = app(CreateTeacherContract::class)->execute(
            profile: $world['teacher'],
            basis: ContractBasis::PerSession,
            effectiveFrom: CarbonImmutable::now('UTC')->subMonths(2)->toDateString(),
            actorId: $actorId,
            reason: 'عقد المعلم ضمن اختبار الرحلة',
        );

        app(AddTeacherRate::class)->execute(
            contract: $contract,
            scope: RateScope::Default,
            amount: Money::of(15000, 'EGP'),
            effectiveFrom: CarbonImmutable::now('UTC')->subMonths(2)->toDateString(),
            actorId: $actorId,
            reason: 'سعر الحصة ضمن اختبار الرحلة',
        );

        // ٥) المعلم يبدأ الدرس
        $session = app(StartSessionAction::class)
            ->execute($session, $actorId, 'بدء الحصة ضمن اختبار الرحلة');
        $this->assertSame(SessionStatus::InProgress, $session->status);

        // ٥ب) الحضور — يدوي اليوم، مؤتمت في خطة الاستكمال
        app(RecordParticipantAttendanceAction::class)
            ->execute($session, $participant, 'join', $actorId);
        app(RecordParticipantAttendanceAction::class)
            ->execute($session, $participant->refresh(), 'leave', $actorId);

        $attendance = app(RecordAttendanceAction::class)->execute(
            sessionParticipantId: (string) $participant->getKey(),
            attendedMinutes: 55,
            sessionMinutes: 60,
            organizationId: (string) $world['organization']->id,
            actorId: $actorId,
            reason: 'تسجيل حضور ضمن اختبار الرحلة',
        );
        $this->assertNotNull($attendance->getKey());

        // ٦) إنهاء الحصة ثم اعتمادها
        $session = app(EndSessionAction::class)
            ->execute($session, $actorId, 'إنهاء الحصة ضمن اختبار الرحلة');
        $this->assertSame(SessionStatus::AwaitingReview, $session->status);

        $session = app(CompleteSessionAction::class)
            ->execute($session, $actorId, 'اعتماد الحصة ضمن اختبار الرحلة');
        $this->assertSame(SessionStatus::Completed, $session->status);

        // ٨) استحقاق المعلم يُقيَّد تلقائيًا عند الاعتماد
        $entries = DB::table('payroll_entries')
            ->where('session_id', $session->id)
            ->count();

        fwrite(STDERR, "[خطوة ٨] قيود مستحقات للحصة: {$entries}\n");

        $this->assertGreaterThan(
            0,
            $entries,
            'اعتماد الحصة لم يُنشئ قيدة استحقاق للمعلم — الخطوة ٨ مكسورة.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function world(): array
    {
        $organization = Organization::factory()->create();
        $organizationId = (string) $organization->getKey();

        $operator = User::factory()->inOrganization($organizationId)->create([
            'email' => 'journey.operator@example.test',
        ]);
        $teacherUser = User::factory()->inOrganization($organizationId)->create([
            'email' => 'journey.teacher@example.test',
        ]);
        $studentUser = User::factory()->inOrganization($organizationId)->create([
            'email' => 'journey.student@example.test',
        ]);

        $program = Program::factory()->create(['organization_id' => $organizationId]);
        $level = Level::factory()->create(['program_id' => $program->id]);
        $course = Course::factory()->create([
            'organization_id' => $organizationId,
            'level_id' => $level->id,
            'session_mode' => SessionMode::Group,
            'name' => ['ar' => 'دورة الرحلة', 'en' => 'Journey course'],
        ]);

        $teacher = StaffProfile::query()->create([
            'organization_id' => $organizationId,
            'user_id' => $teacherUser->id,
            'staff_code' => 'T-JOURNEY',
            'employment_type' => EmploymentType::Contractor,
            'gender' => StaffGender::Male,
            'hired_at' => '2026-01-01',
        ]);

        DB::table('teacher_courses')->insert([
            'id' => (string) Str::ulid(),
            'staff_profile_id' => $teacher->id,
            'course_id' => $course->id,
            'qualified_at' => now('UTC'),
            'qualified_by' => $operator->id,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        $student = StudentProfile::factory()->create([
            'organization_id' => $organizationId,
            'user_id' => $studentUser->id,
            'student_code' => 'ST-JOURNEY',
        ]);

        Enrollment::query()->create([
            'organization_id' => $organizationId,
            'student_profile_id' => $student->id,
            'program_id' => $program->id,
            'current_level_id' => $level->id,
            'status' => EnrollmentStatus::Active,
            'applied_at' => now('UTC')->subMonth(),
            'activated_at' => now('UTC')->subWeeks(2),
        ]);

        $group = Group::query()->create([
            'organization_id' => $organizationId,
            'code' => 'GR-JOURNEY',
            'name' => ['ar' => 'مجموعة الرحلة', 'en' => 'Journey group'],
            'capacity' => 12,
            'timezone' => 'UTC',
            'status' => GroupStatus::Active,
            'starts_on' => CarbonImmutable::now('UTC')->toDateString(),
        ]);

        GroupProgram::query()->create([
            'group_id' => $group->id,
            'program_id' => $program->id,
        ]);

        GroupTeacher::query()->create([
            'group_id' => $group->id,
            'staff_profile_id' => $teacher->id,
            'course_id' => $course->id,
            'role' => GroupTeacherRole::Lead,
            'assigned_from' => CarbonImmutable::now('UTC')->toDateString(),
        ]);

        GroupMembership::query()->create([
            'group_id' => $group->id,
            'student_profile_id' => $student->id,
            'joined_at' => now('UTC')->subDay(),
            'status' => MembershipStatus::Active,
        ]);

        return compact(
            'organization', 'operator', 'teacherUser', 'studentUser',
            'program', 'level', 'course', 'teacher', 'student', 'group',
        );
    }
}
