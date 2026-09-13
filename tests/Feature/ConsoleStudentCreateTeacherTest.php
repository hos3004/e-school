<?php

declare(strict_types=1);

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Modules\Academics\Domain\Enums\SessionMode;
use Modules\Academics\Domain\Enums\TargetGender;
use Modules\Academics\Domain\Models\ProgramEligibility;
use Modules\AccessControl\Database\Seeders\AccessControlSeeder;
use Modules\Enrollments\Domain\Enums\EnrollmentStatus;
use Modules\Groups\Domain\Enums\GroupStatus;
use Modules\Groups\Domain\Models\Group;
use Modules\Identity\Domain\Events\UserRegistered;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Database\Seeders\GeographySeeder;
use Modules\Organization\Domain\Contracts\GeographyQueries;
use Modules\Scheduling\Domain\Models\Schedule;
use Modules\Staff\Domain\Enums\TeacherAvailabilityApprovalStatus;
use Modules\Staff\Domain\Models\TeacherAvailability;
use Modules\Students\Domain\Events\RegistrationAccepted;
use Modules\Students\Domain\Events\RegistrationSubmitted;
use Modules\Students\Domain\Models\StudentProfile;
use Shared\Testing\Fixtures;
use Tests\TestCase;

/**
 * اختيار معلم الطالب أثناء تسجيله من لوحة الإدارة.
 *
 * المحروس: التسجيل يقيّد الطالب في برنامجه، والمعلم وحده يحفظ رابطًا معلقًا،
 * وإضافة الموعد تنشئ الجدول، وفشل الإسناد لا يُضيّع الطالب، وحقول المعلم
 * مرفوضة على الخادم ممن لا يملك إدارة الجداول.
 */
final class ConsoleStudentCreateTeacherTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $permissions = [
        'admin.panel.access', 'student.view.any', 'student.create',
        'enrollment.create', 'enrollment.view', 'schedule.manage', 'schedule.view',
        'group.view', 'group.manage',
    ];

    private string $organizationId;

    private string $courseId;

    private string $programId;

    private string $countryId;

    private string $regionId;

    protected function setUp(): void
    {
        parent::setUp();
        config(['console.enabled' => true]);
        $this->withoutVite();
        Http::preventStrayRequests();
        Http::fake(['api.pwnedpasswords.com/*' => Http::response('', 200)]);
        $this->seed([GeographySeeder::class, AccessControlSeeder::class]);
        Event::fake([UserRegistered::class, RegistrationSubmitted::class, RegistrationAccepted::class]);
        foreach ($this->permissions as $ability) {
            Gate::define($ability, fn (): bool => in_array($ability, $this->permissions, true));
        }

        $this->organizationId = Fixtures::organizationId();
        $this->courseId = Fixtures::courseId();
        DB::table('courses')->where('id', $this->courseId)
            ->update(['session_mode' => SessionMode::Individual->value]);
        $this->programId = (string) DB::table('courses')
            ->join('levels', 'courses.level_id', '=', 'levels.id')
            ->where('courses.id', $this->courseId)
            ->value('levels.program_id');

        $geography = app(GeographyQueries::class);
        $country = $geography->findCountryByIso2('EG');
        self::assertNotNull($country);
        $this->countryId = (string) $country->id;
        $this->regionId = (string) $geography->regionsOf($this->countryId)[0]->id;
    }

    public function test_registration_enrols_the_student_even_without_a_teacher(): void
    {
        $this->actingAs($this->admin(), 'web')
            ->post('/manage/students', $this->studentData())
            ->assertSessionHasNoErrors()->assertSessionMissing('error')->assertRedirect();

        $student = $this->createdStudent();
        $this->assertDatabaseHas('enrollments', [
            'student_profile_id' => (string) $student->getKey(),
            'program_id' => $this->programId,
            'status' => EnrollmentStatus::Active->value,
        ]);
        $this->assertDatabaseCount('schedules', 0);
        $this->assertDatabaseCount('pending_teaching_assignments', 0);
    }

    public function test_a_teacher_without_a_slot_is_kept_as_a_pending_link(): void
    {
        $teacher = $this->teacher();

        $this->actingAs($this->admin(), 'web')->post('/manage/students', [
            ...$this->studentData(),
            'teaching_staff_profile_id' => $teacher,
            'teaching_duration_minutes' => 25,
        ])->assertSessionHasNoErrors()->assertSessionMissing('error')->assertRedirect();

        $student = $this->createdStudent();
        $this->assertDatabaseCount('schedules', 0);
        $this->assertDatabaseHas('pending_teaching_assignments', [
            'organization_id' => $this->organizationId,
            'student_profile_id' => (string) $student->getKey(),
            'staff_profile_id' => $teacher,
            'course_id' => $this->courseId,
            'session_type' => 'individual',
            'duration_minutes' => 25,
        ]);
    }

    public function test_a_teacher_with_a_slot_creates_the_schedule_at_registration(): void
    {
        $teacher = $this->teacher();

        $this->actingAs($this->admin(), 'web')->post('/manage/students', [
            ...$this->studentData(),
            'teaching_staff_profile_id' => $teacher,
            'teaching_duration_minutes' => 25,
            'teaching_weekday' => 1,
            'teaching_start_time' => '10:00',
            'teaching_starts_on' => CarbonImmutable::now('UTC')->toDateString(),
        ])->assertSessionHasNoErrors()->assertSessionMissing('error')->assertRedirect();

        $student = $this->createdStudent();
        $schedule = Schedule::query()
            ->where('student_profile_id', (string) $student->getKey())->firstOrFail();
        self::assertSame($teacher, (string) $schedule->staff_profile_id);
        self::assertSame($this->courseId, (string) $schedule->course_id);
        self::assertSame('individual', (string) $schedule->session_type);
        self::assertSame(25, (int) $schedule->duration_minutes);
        self::assertTrue((bool) $schedule->is_active);
        $this->assertDatabaseCount('pending_teaching_assignments', 0);
    }

    public function test_an_unqualified_teacher_reports_the_reason_without_losing_the_student(): void
    {
        $teacher = Fixtures::staffProfileId();

        $this->actingAs($this->admin(), 'web')->post('/manage/students', [
            ...$this->studentData(),
            'teaching_staff_profile_id' => $teacher,
            'teaching_duration_minutes' => 25,
            'teaching_weekday' => 1,
            'teaching_start_time' => '10:00',
            'teaching_starts_on' => CarbonImmutable::now('UTC')->toDateString(),
        ])->assertSessionHasNoErrors()->assertSessionHas('error')->assertRedirect();

        $student = $this->createdStudent();
        $this->assertDatabaseHas('enrollments', [
            'student_profile_id' => (string) $student->getKey(),
            'status' => EnrollmentStatus::Active->value,
        ]);
        $this->assertDatabaseCount('schedules', 0);
    }

    public function test_teaching_fields_are_refused_without_schedule_management(): void
    {
        $teacher = $this->teacher();
        $this->permissions = ['admin.panel.access', 'student.view.any', 'student.create', 'enrollment.create'];

        $this->actingAs($this->admin(), 'web')->post('/manage/students', [
            ...$this->studentData(),
            'teaching_staff_profile_id' => $teacher,
            'teaching_duration_minutes' => 25,
        ])->assertSessionHasErrors('teaching_staff_profile_id');

        $this->assertDatabaseCount('pending_teaching_assignments', 0);
        $this->assertDatabaseCount('schedules', 0);
    }

    public function test_teacher_options_are_served_for_the_chosen_course_only_with_the_permission(): void
    {
        $teacher = $this->teacher();
        $admin = $this->admin();

        $this->actingAs($admin, 'web')
            ->getJson('/manage/students/form-options?course_id='.$this->courseId)
            ->assertOk()->assertJsonPath('teachers.0.value', $teacher);

        $this->permissions = ['admin.panel.access', 'student.view.any', 'student.create'];
        $this->getJson('/manage/students/form-options?course_id='.$this->courseId)
            ->assertOk()->assertJsonPath('teachers', []);
    }

    public function test_a_pending_link_is_refused_for_a_teacher_who_cannot_teach_the_course(): void
    {
        $teacher = Fixtures::staffProfileId();

        $this->actingAs($this->admin(), 'web')->post('/manage/students', [
            ...$this->studentData(),
            'teaching_staff_profile_id' => $teacher,
            'teaching_duration_minutes' => 25,
        ])->assertSessionHasNoErrors()->assertSessionHas('error')->assertRedirect();

        $this->assertDatabaseCount('pending_teaching_assignments', 0);
        $this->assertDatabaseCount('schedules', 0);
    }

    public function test_a_group_course_places_the_student_in_an_existing_open_group(): void
    {
        $this->groupCourse();
        $group = $this->group();

        $this->actingAs($this->admin(), 'web')->post('/manage/students', [
            ...$this->studentData(),
            'placement_mode' => 'existing',
            'placement_group_id' => (string) $group->getKey(),
        ])->assertSessionHasNoErrors()->assertSessionMissing('error')->assertRedirect();

        $student = $this->createdStudent();
        $this->assertDatabaseHas('group_memberships', [
            'group_id' => (string) $group->getKey(),
            'student_profile_id' => (string) $student->getKey(),
            'left_at' => null,
        ]);
        $this->assertDatabaseHas('enrollments', [
            'student_profile_id' => (string) $student->getKey(),
            'program_id' => $this->programId,
            'status' => EnrollmentStatus::Active->value,
        ]);
    }

    public function test_a_new_group_is_created_inside_the_program_and_the_student_placed_in_it(): void
    {
        $this->groupCourse();

        $this->actingAs($this->admin(), 'web')->post('/manage/students', [
            ...$this->studentData(),
            'placement_mode' => 'new',
            'placement_group_code' => 'G-NEW-1',
            'placement_group_name' => 'مجموعة الأحد',
            'placement_group_capacity' => 8,
        ])->assertSessionHasNoErrors()->assertSessionMissing('error')->assertRedirect();

        $student = $this->createdStudent();
        $group = Group::query()->where('code', 'G-NEW-1')->firstOrFail();
        self::assertSame($this->organizationId, (string) $group->organization_id);
        self::assertSame(GroupStatus::Planning, $group->status);
        self::assertSame(8, (int) $group->capacity);
        $this->assertDatabaseHas('group_programs', [
            'group_id' => (string) $group->getKey(),
            'program_id' => $this->programId,
        ]);
        $this->assertDatabaseHas('group_memberships', [
            'group_id' => (string) $group->getKey(),
            'student_profile_id' => (string) $student->getKey(),
            'left_at' => null,
        ]);
    }

    public function test_a_teacher_and_a_group_are_refused_together(): void
    {
        $this->groupCourse();
        $group = $this->group();

        $this->actingAs($this->admin(), 'web')->post('/manage/students', [
            ...$this->studentData(),
            'teaching_staff_profile_id' => Fixtures::staffProfileId(),
            'teaching_duration_minutes' => 25,
            'placement_mode' => 'existing',
            'placement_group_id' => (string) $group->getKey(),
        ])->assertSessionHasErrors('placement_mode');

        // الرفض عند التحقق، فلا يُنشأ حساب ولا ملف أصلًا.
        $this->assertDatabaseCount('group_memberships', 0);
        $this->assertDatabaseCount('student_profiles', 0);
    }

    public function test_placement_fields_are_refused_without_group_management(): void
    {
        $this->groupCourse();
        $group = $this->group();
        $this->permissions = [
            'admin.panel.access', 'student.view.any', 'student.create', 'enrollment.create',
        ];

        $this->actingAs($this->admin(), 'web')->post('/manage/students', [
            ...$this->studentData(),
            'placement_mode' => 'existing',
            'placement_group_id' => (string) $group->getKey(),
        ])->assertSessionHasErrors('placement_mode');

        $this->assertDatabaseCount('group_memberships', 0);
    }

    public function test_a_group_of_another_program_is_refused_without_losing_the_student(): void
    {
        $this->groupCourse();
        $foreign = Group::query()->create([
            'organization_id' => $this->organizationId,
            'code' => 'G-OTHER',
            'name' => ['ar' => 'مجموعة أخرى'],
            'capacity' => 5,
            'timezone' => 'Africa/Cairo',
            'status' => GroupStatus::Planning,
        ]);

        $this->actingAs($this->admin(), 'web')->post('/manage/students', [
            ...$this->studentData(),
            'placement_mode' => 'existing',
            'placement_group_id' => (string) $foreign->getKey(),
        ])->assertSessionHasNoErrors()->assertSessionHas('error')->assertRedirect();

        $this->assertDatabaseCount('group_memberships', 0);
        $this->assertDatabaseCount('student_profiles', 1);
    }

    public function test_group_options_and_course_mode_are_served_for_the_chosen_course(): void
    {
        $this->groupCourse();
        $group = $this->group();

        $this->actingAs($this->admin(), 'web')
            ->getJson('/manage/students/form-options?course_id='.$this->courseId)
            ->assertOk()
            ->assertJsonPath('courseMode', 'group')
            ->assertJsonPath('groups.0.value', (string) $group->getKey())
            ->assertJsonPath('groups.0.draft', true);

        $this->permissions = ['admin.panel.access', 'student.view.any', 'student.create'];
        $this->getJson('/manage/students/form-options?course_id='.$this->courseId)
            ->assertOk()->assertJsonPath('groups', []);
    }

    public function test_a_refused_placement_leaves_no_orphan_group_behind(): void
    {
        $this->groupCourse();
        // برنامج للإناث وحدهنّ بينما الطالب ذكر: التسكين يُرفض بعد إنشاء المجموعة.
        ProgramEligibility::query()->create([
            'program_id' => $this->programId,
            'gender' => TargetGender::Female,
            'manual_approval_required' => false,
            'teacher_gender_rule' => 'any',
            'requires_individual_sessions' => false,
        ]);

        $this->actingAs($this->admin(), 'web')->post('/manage/students', [
            ...$this->studentData(),
            'placement_mode' => 'new',
            'placement_group_code' => 'G-ROLLBACK',
            'placement_group_name' => 'مجموعة مرفوضة',
        ])->assertSessionHasNoErrors()->assertSessionHas('error')->assertRedirect();

        $this->assertDatabaseCount('student_profiles', 1);
        $this->assertDatabaseMissing('groups', ['code' => 'G-ROLLBACK']);
        $this->assertDatabaseCount('group_memberships', 0);
    }

    private function groupCourse(): void
    {
        DB::table('courses')->where('id', $this->courseId)
            ->update(['session_mode' => SessionMode::Group->value]);
    }

    /** مجموعة قيد التخطيط مربوطة ببرنامج التسجيل — مفتوحة للتسكين. */
    private function group(): Group
    {
        /** @var Group $group */
        $group = Group::query()->create([
            'organization_id' => $this->organizationId,
            'code' => 'G-OPEN',
            'name' => ['ar' => 'مجموعة مفتوحة'],
            'capacity' => 6,
            'timezone' => 'Africa/Cairo',
            'status' => GroupStatus::Planning,
        ]);
        $group->programs()->create([
            'group_id' => (string) $group->getKey(),
            'program_id' => $this->programId,
        ]);

        return $group;
    }

    private function admin(): User
    {
        return User::factory()->inOrganization($this->organizationId)->create();
    }

    private function createdStudent(): StudentProfile
    {
        $user = User::query()->where('username', 'console.student')->firstOrFail();

        return StudentProfile::query()->where('user_id', $user->id)->firstOrFail();
    }

    /** معلم مؤهل ومتاح طوال الأسبوع — كما هي إتاحة معلمي الإنتاج اليوم. */
    private function teacher(): string
    {
        $staffProfileId = Fixtures::staffProfileId();
        Fixtures::qualifyTeacher($staffProfileId, $this->courseId);

        for ($weekday = 0; $weekday <= 6; $weekday++) {
            TeacherAvailability::query()->create([
                'staff_profile_id' => $staffProfileId,
                'weekday' => $weekday,
                'start_time' => '00:00:00',
                'end_time' => '23:59:59',
                'timezone' => 'UTC',
                'effective_from' => CarbonImmutable::now('UTC')->subMonths(2)->startOfDay(),
                'approval_status' => TeacherAvailabilityApprovalStatus::Approved,
                'approved_at' => CarbonImmutable::now('UTC')->subMonths(2),
            ]);
        }

        return $staffProfileId;
    }

    /** @return array<string, mixed> */
    private function studentData(): array
    {
        return [
            'account_mode' => 'new', 'full_name' => 'Console Student',
            'email' => 'console.student@example.test', 'username' => 'console.student',
            'password' => 'G8!Student-Console#2026', 'password_confirmation' => 'G8!Student-Console#2026',
            'locale' => 'ar', 'timezone' => 'Africa/Cairo', 'date_of_birth' => '2011-05-15',
            'gender' => 'male', 'country_id' => $this->countryId, 'region_id' => $this->regionId,
            'preferred_program_id' => $this->programId, 'preferred_course_id' => $this->courseId,
        ];
    }
}
