<?php

declare(strict_types=1);

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Academics\Domain\Enums\SessionMode;
use Modules\Groups\Domain\Enums\GroupStatus;
use Modules\Groups\Domain\Enums\GroupTeacherRole;
use Modules\Groups\Domain\Enums\MembershipStatus;
use Modules\Groups\Domain\Models\Group;
use Modules\Groups\Domain\Models\GroupMembership;
use Modules\Groups\Domain\Models\GroupTeacher;
use Modules\Identity\Domain\Models\User;
use Modules\Scheduling\Domain\Models\PendingTeachingAssignment;
use Modules\Scheduling\Domain\Models\Schedule;
use Modules\Staff\Domain\Enums\EmploymentType;
use Shared\Testing\Fixtures;
use Tests\TestCase;

/**
 * البرامج والدورات التي يقدّمها المعلم من صفحة ملفه.
 *
 * المحروس: الكورس الفردي يعرض أسماء طلابه لا عددهم، والرابط المعلق يظهر موسومًا
 * بانتظار الموعد، ومجموعة المعلم تعرض طلابها، وحِمل معلم لا يظهر عند غيره،
 * وروابط صفحات الطلاب تُحجب على الخادم بلا صلاحية.
 */
final class ConsoleTeacherPortfolioTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $permissions = [
        'admin.panel.access', 'staff.view.any', 'staff.view', 'student.view.any', 'group.view',
    ];

    private string $organizationId;

    private string $courseId;

    private string $programId;

    protected function setUp(): void
    {
        parent::setUp();
        config(['console.enabled' => true]);
        $this->withoutVite();
        foreach (['admin.panel.access', 'staff.view.any', 'staff.view', 'student.view.any', 'group.view'] as $ability) {
            Gate::define($ability, fn (): bool => in_array($ability, $this->permissions, true));
        }
        $this->organizationId = Fixtures::organizationId();
        $this->courseId = Fixtures::courseId();
        DB::table('courses')->where('id', $this->courseId)->update([
            'session_mode' => SessionMode::Individual->value,
            'default_duration_minutes' => 25,
            'sessions_per_week' => 2,
        ]);
        $this->programId = (string) DB::table('courses')
            ->join('levels', 'courses.level_id', '=', 'levels.id')
            ->where('courses.id', $this->courseId)
            ->value('levels.program_id');
    }

    public function test_individual_course_lists_its_students_by_name(): void
    {
        $teacher = $this->teacherProfileId();
        $scheduled = Fixtures::studentProfileId();
        $awaiting = Fixtures::studentProfileId();
        $this->schedule($teacher, $scheduled);
        PendingTeachingAssignment::query()->create([
            'organization_id' => $this->organizationId,
            'student_profile_id' => $awaiting,
            'staff_profile_id' => $teacher,
            'course_id' => $this->courseId,
            'session_type' => 'individual',
            'duration_minutes' => 25,
            'reason' => 'رابط قبل الموعد',
            'created_by' => Fixtures::userId(),
        ]);

        $this->assertDatabaseCount('groups', 0);
        $portfolio = $this->portfolio($teacher);

        $this->assertCount(1, $portfolio['programs']);
        $program = $portfolio['programs'][0];
        $this->assertSame($this->programId, $program['id']);
        $this->assertSame(1, $program['courses_count']);
        $this->assertSame(2, $program['students_count']);

        $course = $program['courses'][0];
        $this->assertSame($this->courseId, $course['id']);
        $this->assertSame(25, $course['default_duration_minutes']);
        $this->assertSame(2, $course['sessions_per_week']);
        $this->assertCount(2, $course['students']);

        $students = collect($course['students'])->keyBy('id');
        $this->assertSame(
            (string) DB::table('users')
                ->join('student_profiles', 'student_profiles.user_id', '=', 'users.id')
                ->where('student_profiles.id', $scheduled)->value('users.name'),
            $students[$scheduled]['name'],
        );
        $this->assertFalse($students[$scheduled]['awaiting']);
        $this->assertSame(['الإثنين 10:00'], $students[$scheduled]['slots']);
        $this->assertSame(url('/manage/students/'.$scheduled), $students[$scheduled]['url']);
        $this->assertTrue($students[$awaiting]['awaiting']);
        $this->assertSame([], $students[$awaiting]['slots']);
    }

    public function test_group_course_lists_the_group_and_its_members(): void
    {
        $teacher = $this->teacherProfileId();
        $member = Fixtures::studentProfileId();
        $group = Group::query()->create([
            'organization_id' => $this->organizationId,
            'code' => 'G-PORT-1',
            'name' => ['ar' => 'مجموعة الاختبار', 'en' => 'Test group'],
            'capacity' => 10,
            'timezone' => 'UTC',
            'status' => GroupStatus::Active,
            'starts_on' => CarbonImmutable::now('UTC')->subMonth()->toDateString(),
        ]);
        GroupTeacher::query()->create([
            'group_id' => (string) $group->getKey(),
            'staff_profile_id' => $teacher,
            'course_id' => $this->courseId,
            'role' => GroupTeacherRole::Lead,
            'assigned_from' => CarbonImmutable::now('UTC')->subMonth()->toDateString(),
        ]);
        GroupMembership::query()->create([
            'group_id' => (string) $group->getKey(),
            'student_profile_id' => $member,
            'status' => MembershipStatus::Active,
            'joined_at' => CarbonImmutable::now('UTC')->subMonth(),
        ]);

        $course = $this->portfolio($teacher)['programs'][0]['courses'][0];

        $this->assertCount(1, $course['groups']);
        $this->assertSame('مجموعة الاختبار', $course['groups'][0]['name']);
        $this->assertSame(1, $course['groups'][0]['students_count']);
        $this->assertSame(
            url('/manage/groups/'.$group->getKey()),
            $course['groups'][0]['url'],
        );
        $this->assertCount(1, $course['students']);
        $this->assertSame($member, $course['students'][0]['id']);
        $this->assertSame('مجموعة الاختبار', $course['students'][0]['group']);
    }

    /** جدول مجموعة بلا إسناد مسجَّل: المجموعة تظهر كاملة البيانات ومعها مواعيدها. */
    public function test_a_group_schedule_without_an_assignment_still_shows_the_group(): void
    {
        $teacher = $this->teacherProfileId();
        $member = Fixtures::studentProfileId();
        $group = Group::query()->create([
            'organization_id' => $this->organizationId,
            'code' => 'G-PORT-2',
            'name' => ['ar' => 'مجموعة بلا إسناد', 'en' => 'Unassigned group'],
            'capacity' => 10,
            'timezone' => 'UTC',
            'status' => GroupStatus::Active,
            'starts_on' => CarbonImmutable::now('UTC')->subMonth()->toDateString(),
        ]);
        GroupMembership::query()->create([
            'group_id' => (string) $group->getKey(),
            'student_profile_id' => $member,
            'status' => MembershipStatus::Active,
            'joined_at' => CarbonImmutable::now('UTC')->subMonth(),
        ]);
        $this->schedule($teacher, null, (string) $group->getKey());

        $course = $this->portfolio($teacher)['programs'][0]['courses'][0];

        $this->assertCount(1, $course['groups']);
        $this->assertSame('مجموعة بلا إسناد', $course['groups'][0]['name']);
        $this->assertSame('G-PORT-2', $course['groups'][0]['code']);
        $this->assertNotNull($course['groups'][0]['status']);
        $this->assertNull($course['groups'][0]['role']);
        $this->assertSame(['الإثنين 10:00'], $course['groups'][0]['slots']);
        $this->assertCount(1, $course['students']);
        $this->assertSame($member, $course['students'][0]['id']);
        $this->assertSame(['الإثنين 10:00'], $course['students'][0]['slots']);
    }

    public function test_one_teachers_load_never_appears_on_another(): void
    {
        $teacher = $this->teacherProfileId();
        $other = $this->teacherProfileId();
        $this->schedule($teacher, Fixtures::studentProfileId());

        $this->assertSame([], $this->portfolio($other)['programs']);
    }

    public function test_student_identity_is_withheld_without_the_directory_permission(): void
    {
        $teacher = $this->teacherProfileId();
        $student = Fixtures::studentProfileId();
        $this->schedule($teacher, $student);

        $this->permissions = ['admin.panel.access', 'staff.view.any', 'staff.view'];
        $course = $this->portfolio($teacher)['programs'][0]['courses'][0];

        $this->assertSame([], $course['students']);
        $this->assertSame(1, $course['students_count']);
    }

    /** ملف معلم صالح للعرض — تثبيت نوع التوظيف لأن تركيبة الاختبارات تسبق enum الحالي. */
    private function teacherProfileId(): string
    {
        $id = Fixtures::staffProfileId();
        DB::table('staff_profiles')->where('id', $id)
            ->update(['employment_type' => EmploymentType::PartTime->value]);

        return $id;
    }

    /** @return array<string, mixed> */
    private function portfolio(string $staffProfileId): array
    {
        $admin = User::factory()->inOrganization($this->organizationId)->create();
        $response = $this->actingAs($admin, 'web')
            ->get('/manage/teachers/'.$staffProfileId)
            ->assertOk();

        /** @var array<string, mixed> $props */
        $props = $response->viewData('page')['props'];

        /** @var array<string, mixed> $teaching */
        $teaching = $props['teaching'];

        return $teaching;
    }

    private function schedule(
        string $staffProfileId,
        ?string $studentProfileId,
        ?string $groupId = null,
    ): Schedule {
        return Schedule::query()->create([
            'organization_id' => $this->organizationId,
            'group_id' => $groupId,
            'student_profile_id' => $studentProfileId,
            'course_id' => $this->courseId,
            'staff_profile_id' => $staffProfileId,
            'session_type' => $groupId === null ? 'individual' : 'group',
            'rrule' => 'FREQ=WEEKLY;INTERVAL=1;BYDAY=MO',
            'start_time' => '10:00:00',
            'duration_minutes' => 25,
            'timezone' => 'UTC',
            'starts_on' => CarbonImmutable::now('UTC')->subMonth()->toDateString(),
            'ends_on' => null,
            'materialized_until' => CarbonImmutable::now('UTC')->addWeeks(2)->toDateString(),
            'is_active' => true,
            'created_by' => Fixtures::userId(),
        ]);
    }
}
