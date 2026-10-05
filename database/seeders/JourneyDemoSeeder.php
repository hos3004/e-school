<?php

declare(strict_types=1);

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Academics\Domain\Models\Course;
use Modules\Academics\Domain\Models\Level;
use Modules\Attendance\Application\Actions\RecordAttendanceAction;
use Modules\Enrollments\Domain\Enums\EnrollmentStatus;
use Modules\Enrollments\Domain\Models\Enrollment;
use Modules\Groups\Domain\Enums\GroupTeacherRole;
use Modules\Groups\Domain\Enums\MembershipStatus;
use Modules\Groups\Domain\Models\Group;
use Modules\Groups\Domain\Models\GroupMembership;
use Modules\Groups\Domain\Models\GroupProgram;
use Modules\Groups\Domain\Models\GroupTeacher;
use Modules\Identity\Domain\Models\User;
use Modules\Scheduling\Application\Actions\CreateScheduleAction;
use Modules\Scheduling\Application\Actions\MaterializeScheduleAction;
use Modules\Scheduling\Domain\Models\Schedule;
use Modules\Sessions\Application\Actions\CompleteSessionAction;
use Modules\Sessions\Application\Actions\EndSessionAction;
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
use Modules\Students\Domain\Models\StudentProfile;
use Shared\ValueObjects\Money;
use Throwable;

/**
 * بيانات عرض تُكمل رحلة العميل الثمانية فوق ما زرعه `DemoDataSeeder`.
 *
 * سبب وجوده: البذور الأساسية تنتج مؤسسة وبرامج وكورسات وطلابًا، لكن **بلا
 * معلمين ولا قيود ولا جداول ولا حصص** — فتفتح اللوحة على بطاقات صفرية و«أقرب
 * الحصص» فارغة، وهو أسوأ انطباع أول ممكن أمام عميل.
 *
 * كل خطوة هنا تمر عبر **نفس الـAction الذي يستدعيه زر اللوحة** لا عبر إدراج
 * مباشر: لو انكسر مسار حقيقي انكسرت البذرة معه بدل أن تخفيه ببيانات مصطنعة.
 *
 * قابل لإعادة التشغيل: يتعرّف على ما أنشأه سابقًا فلا يضاعفه.
 */
final class JourneyDemoSeeder extends Seeder
{
    private const TEACHER_EMAIL = 'teacher.demo@eschool.test';

    private const STAFF_CODE = 'T-DEMO-01';

    public function run(): void
    {
        // المجموعة في حالة `planning` ترفض الجدولة، فنختار نشطة صراحةً.
        $group = Group::query()->where('status', 'active')->first();

        if (!$group instanceof Group) {
            $this->command?->warn('JourneyDemoSeeder: لا توجد مجموعة نشطة — شغّل DemoDataSeeder أولًا.');

            return;
        }

        $organizationId = (string) $group->organization_id;

        $course = Course::query()
            ->where('organization_id', $organizationId)
            ->first();

        if ($course === null) {
            $this->command?->warn('JourneyDemoSeeder: لا يوجد كورس في المؤسسة.');

            return;
        }

        $teacher = $this->teacher($organizationId);
        $this->qualify($teacher, (string) $course->getKey());
        $this->linkGroupToProgram($group, (string) $course->level_id);
        $this->assignToGroup($group, $teacher, (string) $course->getKey());
        $this->contract($teacher);

        $students = $this->enrolledStudents($organizationId, $group, (string) $course->level_id);

        if ($students === []) {
            $this->command?->warn('JourneyDemoSeeder: لا طلاب لإكمال الرحلة.');

            return;
        }

        $this->schedule($organizationId, $group, $course, $teacher);
        $this->deliverOnePastSession($organizationId, $group, $course, $teacher, $students[0]);

        $this->command?->info(sprintf(
            'JourneyDemoSeeder: معلم واحد · %d قيدًا · %d حصة · حصة مكتملة بحضور ومستحق.',
            count($students),
            Session::query()->where('group_id', $group->getKey())->count(),
        ));
    }

    private function teacher(string $organizationId): StaffProfile
    {
        $user = User::query()->firstOrCreate(
            ['email' => self::TEACHER_EMAIL],
            [
                'organization_id' => $organizationId,
                'name' => 'أ. محمود الديب',
                'password' => bcrypt('password'),
                'email_verified_at' => now('UTC'),
            ],
        );

        $profile = StaffProfile::query()->where('user_id', $user->getKey())->first();

        if ($profile instanceof StaffProfile) {
            return $profile;
        }

        return StaffProfile::query()->create([
            'organization_id' => $organizationId,
            'user_id' => $user->getKey(),
            'staff_code' => self::STAFF_CODE,
            'employment_type' => EmploymentType::Contractor,
            'gender' => StaffGender::Male,
            'hired_at' => CarbonImmutable::now('UTC')->subYear()->toDateString(),
        ]);
    }

    /** تأهيل المعلم على الكورس — بدونه ترفض إسنادَه قواعدُ المجموعة. */
    private function qualify(StaffProfile $teacher, string $courseId): void
    {
        $exists = DB::table('teacher_courses')
            ->where('staff_profile_id', $teacher->getKey())
            ->where('course_id', $courseId)
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('teacher_courses')->insert([
            'id' => (string) Str::ulid(),
            'staff_profile_id' => $teacher->getKey(),
            'course_id' => $courseId,
            'qualified_at' => now('UTC'),
            'qualified_by' => $teacher->user_id,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);
    }

    /**
     * الجدولة ترفض مجموعةً غير مرتبطة ببرنامج الكورس، و`DemoDataSeeder` ينشئ
     * المجموعات بلا ربط. الربط هنا لا في البذرة الأساسية حتى تبقى تلك كما هي.
     */
    private function linkGroupToProgram(Group $group, string $levelId): void
    {
        $programId = Level::query()
            ->find($levelId)?->program_id;

        if ($programId === null) {
            return;
        }

        GroupProgram::query()->firstOrCreate([
            'group_id' => $group->getKey(),
            'program_id' => $programId,
        ]);
    }

    private function assignToGroup(Group $group, StaffProfile $teacher, string $courseId): void
    {
        GroupTeacher::query()->firstOrCreate(
            [
                'group_id' => $group->getKey(),
                'staff_profile_id' => $teacher->getKey(),
                'course_id' => $courseId,
            ],
            [
                'role' => GroupTeacherRole::Lead,
                'assigned_from' => CarbonImmutable::now('UTC')->subMonth()->toDateString(),
            ],
        );
    }

    /**
     * العقد والسعر شرط لقيد الاستحقاق: بدونهما يرفض `RecordSessionPayrollEntry`
     * اختراع قيدة بصفر ويكتفي بتحذير، فتبدو الخطوة الثامنة مكسورة وهي سليمة.
     */
    private function contract(StaffProfile $teacher): void
    {
        $existing = DB::table('teacher_contracts')
            ->where('staff_profile_id', $teacher->getKey())
            ->first();

        if ($existing !== null) {
            return;
        }

        $from = CarbonImmutable::now('UTC')->subMonths(6)->toDateString();

        $contract = app(CreateTeacherContract::class)->execute(
            profile: $teacher,
            basis: ContractBasis::PerSession,
            effectiveFrom: $from,
            actorId: (string) $teacher->user_id,
            reason: 'عقد المعلم ضمن بيانات العرض',
        );

        app(AddTeacherRate::class)->execute(
            contract: $contract,
            scope: RateScope::Default,
            amount: Money::of(15000, 'EGP'),
            effectiveFrom: $from,
            actorId: (string) $teacher->user_id,
            reason: 'سعر الحصة ضمن بيانات العرض',
        );
    }

    /**
     * @return list<StudentProfile>
     */
    private function enrolledStudents(string $organizationId, Group $group, string $levelId): array
    {
        $level = Level::query()->find($levelId);
        $programId = $level?->program_id;

        if ($programId === null) {
            return [];
        }

        $students = StudentProfile::query()
            ->where('organization_id', $organizationId)
            ->limit(5)
            ->get()
            ->all();

        foreach ($students as $student) {
            Enrollment::query()->firstOrCreate(
                [
                    'organization_id' => $organizationId,
                    'student_profile_id' => $student->getKey(),
                    'program_id' => $programId,
                ],
                [
                    'current_level_id' => $levelId,
                    'status' => EnrollmentStatus::Active,
                    'applied_at' => CarbonImmutable::now('UTC')->subMonths(2),
                    'activated_at' => CarbonImmutable::now('UTC')->subMonth(),
                ],
            );

            GroupMembership::query()->firstOrCreate(
                [
                    'group_id' => $group->getKey(),
                    'student_profile_id' => $student->getKey(),
                ],
                [
                    'joined_at' => CarbonImmutable::now('UTC')->subMonth(),
                    'status' => MembershipStatus::Active,
                ],
            );
        }

        return $students;
    }

    private function schedule(
        string $organizationId,
        Group $group,
        object $course,
        StaffProfile $teacher,
    ): void {
        // الفحص على وجود قالب جدول لا على وجود حصة: الحصة الماضية المكتملة
        // تُنشأ مباشرةً بلا جدول، فلو حرسنا بها لما تولّدت الحصص القادمة أبدًا.
        $hasSchedule = Schedule::query()
            ->where('group_id', $group->getKey())
            ->exists();

        if ($hasSchedule) {
            return;
        }

        try {
            $schedule = app(CreateScheduleAction::class)->execute(
                $organizationId,
                [
                    'target_type' => 'group',
                    'group_id' => (string) $group->getKey(),
                    'course_id' => (string) $course->getKey(),
                    'staff_profile_id' => (string) $teacher->getKey(),
                    'weekdays' => [0, 3],
                    'interval_weeks' => 1,
                    'start_time' => '17:00',
                    'duration_minutes' => 60,
                    'timezone' => 'Africa/Cairo',
                    'starts_on' => CarbonImmutable::now('UTC')->addDay()->toDateString(),
                    'ends_on' => CarbonImmutable::now('UTC')->addMonths(2)->toDateString(),
                ],
                (string) $teacher->user_id,
                'جدول العرض التجريبي',
            );

            app(MaterializeScheduleAction::class)->execute(
                $schedule,
                (string) $teacher->user_id,
                'توليد حصص العرض التجريبي',
            );
        } catch (Throwable $e) {
            $this->command?->warn('JourneyDemoSeeder: تعذّرت الجدولة — '.$e->getMessage());
        }
    }

    /**
     * حصة ماضية مكتملة: تملأ نسبة الحضور ومستحقات الشهر في لوحة المعلومات،
     * وتعطي التقارير مادةً تُبنى عليها.
     */
    private function deliverOnePastSession(
        string $organizationId,
        Group $group,
        object $course,
        StaffProfile $teacher,
        StudentProfile $student,
    ): void {
        $alreadyDone = Session::query()
            ->where('group_id', $group->getKey())
            ->where('status', SessionStatus::Completed)
            ->exists();

        if ($alreadyDone) {
            return;
        }

        try {
            $session = Session::query()->create([
                'organization_id' => $organizationId,
                'group_id' => $group->getKey(),
                'course_id' => $course->getKey(),
                'staff_profile_id' => $teacher->getKey(),
                'session_type' => 'group',
                /*
                 * اليوم لا الأمس: بطاقات «حصص اليوم» و«نسبة الحضور هذا الشهر»
                 * و«مستحقات الشهر» تقرأ الشهر الجاري، وتاريخٌ قبل يومين يقع في
                 * الشهر السابق حين نكون في أول أيام الشهر فتظهر البطاقات صفرية
                 * رغم سلامة البيانات.
                 */
                'scheduled_start' => CarbonImmutable::now('UTC')->subHours(3),
                'scheduled_end' => CarbonImmutable::now('UTC')->subHours(2),
                'title' => ['ar' => 'حصة تمهيدية', 'en' => 'Intro session'],
                'status' => SessionStatus::Scheduled,
            ]);

            $enrollment = Enrollment::query()
                ->where('student_profile_id', $student->getKey())
                ->firstOrFail();

            $participant = SessionParticipant::query()->create([
                'session_id' => $session->getKey(),
                'student_profile_id' => $student->getKey(),
                'enrollment_id' => $enrollment->getKey(),
                'join_url_token' => Str::random(64),
                'invited_at' => CarbonImmutable::now('UTC')->subDays(3),
                'attended_minutes' => 0,
            ]);

            $actorId = (string) $teacher->user_id;

            $session = app(StartSessionAction::class)->execute($session, $actorId, 'بدء حصة العرض');

            app(RecordAttendanceAction::class)->execute(
                sessionParticipantId: (string) $participant->getKey(),
                attendedMinutes: 55,
                sessionMinutes: 60,
                organizationId: $organizationId,
                actorId: $actorId,
                reason: 'حضور ضمن بيانات العرض',
            );

            $session = app(EndSessionAction::class)->execute($session, $actorId, 'إنهاء حصة العرض');
            app(CompleteSessionAction::class)->execute($session, $actorId, 'اعتماد حصة العرض');
        } catch (Throwable $e) {
            $this->command?->warn('JourneyDemoSeeder: تعذّر إكمال الحصة — '.$e->getMessage());
        }
    }
}
