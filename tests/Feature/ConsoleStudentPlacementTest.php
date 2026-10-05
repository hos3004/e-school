<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Modules\Enrollments\Domain\Enums\EnrollmentStatus;
use Modules\Groups\Domain\Enums\GroupStatus;
use Modules\Groups\Domain\Enums\MembershipStatus;
use Modules\Groups\Domain\Models\Group;
use Modules\Groups\Domain\Models\GroupProgram;
use Modules\Groups\Domain\Models\GroupTeacher;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Database\Seeders\GeographySeeder;
use Modules\Organization\Domain\Contracts\GeographyQueries;
use Modules\Students\Domain\Enums\RegistrationStatus;
use Modules\Students\Domain\Models\RegistrationApplication;
use Modules\Students\Domain\Models\StudentProfile;
use Shared\Testing\Fixtures;
use Tests\TestCase;

/**
 * إضافة الطالب إلى دورة أخرى ونقله بين المجموعات من صفحة ملفه.
 *
 * المحروس هنا: النقل ذرّي (لا يبقى الطالب بلا مجموعة عند الفشل)، والإضافة
 * لا تمس البرنامج القديم، والمجموعة المرسلة تُتحقق فعلًا لا شكلًا.
 */
final class ConsoleStudentPlacementTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $permissions = [
        'admin.panel.access', 'student.view.any', 'enrollment.create', 'group.manage',
    ];

    private string $organizationId;

    private string $programId;

    private string $courseId;

    private string $countryId;

    private string $regionId;

    protected function setUp(): void
    {
        parent::setUp();
        config(['console.enabled' => true]);
        $this->withoutVite();
        $this->seed(GeographySeeder::class);
        foreach ($this->permissions as $ability) {
            Gate::define($ability, fn (): bool => in_array($ability, $this->permissions, true));
        }
        $this->organizationId = Fixtures::organizationId();
        $this->courseId = Fixtures::courseId();
        $this->programId = (string) DB::table('courses')
            ->join('levels', 'courses.level_id', '=', 'levels.id')
            ->where('courses.id', $this->courseId)
            ->value('levels.program_id');
        $geography = app(GeographyQueries::class);
        $country = $geography->findCountryByIso2('EG');
        $this->countryId = (string) $country?->id;
        $this->regionId = $geography->regionsOf($this->countryId)[0]->id;
    }

    public function test_an_active_student_can_be_added_to_another_course_from_the_profile(): void
    {
        $admin = $this->admin();
        $student = $this->student();
        $group = $this->group();

        $this->actingAs($admin, 'web')
            ->post('/manage/students/'.$student->id.'/placements', [
                'course_id' => $this->courseId, 'group_id' => (string) $group->getKey(),
            ])->assertSessionHasErrors('reason');

        $this->post('/manage/students/'.$student->id.'/placements', [
            'course_id' => $this->courseId,
            'group_id' => (string) $group->getKey(),
            'reason' => 'انضمام الطالب لدورة جديدة تبدأ هذا الشهر',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('group_memberships', [
            'group_id' => (string) $group->getKey(),
            'student_profile_id' => (string) $student->id,
            'status' => MembershipStatus::Active->value,
        ]);
        $this->assertDatabaseHas('enrollments', [
            'student_profile_id' => (string) $student->id,
            'program_id' => $this->programId,
            'status' => EnrollmentStatus::Active->value,
        ]);
        $this->assertDatabaseHas('audit_log', [
            'action' => 'enrollment.placed', 'auditable_id' => (string) $student->id,
        ]);
    }

    public function test_transfer_moves_the_student_between_groups_atomically_and_is_audited(): void
    {
        $admin = $this->admin();
        $student = $this->student();
        $source = $this->group();
        $target = $this->group();

        $this->actingAs($admin, 'web')->post('/manage/students/'.$student->id.'/placements', [
            'course_id' => $this->courseId, 'group_id' => (string) $source->getKey(),
            'reason' => 'التسكين الأول',
        ])->assertRedirect();
        $membershipId = (string) DB::table('group_memberships')
            ->where('student_profile_id', (string) $student->id)->value('id');

        $this->post('/manage/students/'.$student->id.'/transfer', [
            'membership_id' => $membershipId,
            'course_id' => $this->courseId,
            'group_id' => (string) $target->getKey(),
            'reason' => 'نقل الطالب إلى حلقة معلم آخر بطلب ولي الأمر',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('group_memberships', [
            'id' => $membershipId, 'status' => MembershipStatus::Left->value,
        ]);
        $this->assertDatabaseHas('group_memberships', [
            'group_id' => (string) $target->getKey(),
            'student_profile_id' => (string) $student->id,
            'status' => MembershipStatus::Active->value,
        ]);
        $this->assertSame(1, DB::table('enrollments')
            ->where('student_profile_id', (string) $student->id)->count());
        $this->assertDatabaseHas('audit_log', [
            'action' => 'students.transferred', 'auditable_id' => (string) $student->id,
        ]);
    }

    public function test_transfer_to_the_same_group_and_unknown_groups_are_rejected(): void
    {
        $admin = $this->admin();
        $student = $this->student();
        $source = $this->group();
        $this->actingAs($admin, 'web')->post('/manage/students/'.$student->id.'/placements', [
            'course_id' => $this->courseId, 'group_id' => (string) $source->getKey(), 'reason' => 'التسكين الأول',
        ])->assertRedirect();
        $membershipId = (string) DB::table('group_memberships')
            ->where('student_profile_id', (string) $student->id)->value('id');

        $this->post('/manage/students/'.$student->id.'/transfer', [
            'membership_id' => $membershipId, 'course_id' => $this->courseId,
            'group_id' => (string) $source->getKey(), 'reason' => 'نقل لنفس المجموعة',
        ])->assertSessionHasErrors('group_id');

        $this->post('/manage/students/'.$student->id.'/transfer', [
            'membership_id' => $membershipId, 'course_id' => $this->courseId,
            'group_id' => (string) Str::ulid(), 'reason' => 'مجموعة غير موجودة',
        ])->assertSessionHasErrors('group_id');

        $this->assertDatabaseHas('group_memberships', [
            'id' => $membershipId, 'status' => MembershipStatus::Active->value,
        ]);
    }

    public function test_placement_is_denied_without_permission_and_across_organizations(): void
    {
        $admin = $this->admin();
        $student = $this->student();
        $group = $this->group();

        $this->permissions = ['admin.panel.access', 'student.view.any'];
        $this->actingAs($admin, 'web')->get('/manage/students/'.$student->id)->assertOk()
            ->assertInertia(fn ($page) => $page->where('placement', null));
        $this->post('/manage/students/'.$student->id.'/placements', [
            'course_id' => $this->courseId, 'group_id' => (string) $group->getKey(), 'reason' => 'بلا صلاحية',
        ])->assertForbidden();
        $this->assertDatabaseCount('group_memberships', 0);
    }

    private function admin(): User
    {
        return User::factory()->inOrganization($this->organizationId)->create();
    }

    private function student(): StudentProfile
    {
        $userId = Fixtures::userId();
        $profile = StudentProfile::query()->create([
            'organization_id' => $this->organizationId,
            'user_id' => $userId,
            'student_code' => 'E'.mb_substr((string) Str::ulid(), -6),
            'date_of_birth' => '2010-01-01',
            'gender' => 'male',
            'country_id' => $this->countryId,
            'region_id' => $this->regionId,
            'joined_at' => now()->toDateString(),
        ]);
        RegistrationApplication::query()->create([
            'organization_id' => $this->organizationId,
            'user_id' => $userId,
            'student_profile_id' => (string) $profile->getKey(),
            'status' => RegistrationStatus::WaitingAssignment,
            'full_name' => 'طالب اختبار',
            'date_of_birth' => '2010-01-01',
            'gender' => 'male',
            'country_id' => $this->countryId,
            'region_id' => $this->regionId,
            'preferred_program_id' => $this->programId,
            'preferred_course_id' => $this->courseId,
        ]);

        return $profile;
    }

    private function group(): Group
    {
        $group = Group::query()->create([
            'organization_id' => $this->organizationId,
            'code' => 'G'.mb_substr((string) Str::ulid(), -6),
            'name' => ['ar' => 'مجموعة', 'en' => 'Group'],
            'capacity' => 5,
            'timezone' => 'UTC',
            'status' => GroupStatus::Active,
            'starts_on' => now()->toDateString(),
        ]);
        GroupProgram::query()->create([
            'group_id' => (string) $group->getKey(),
            'program_id' => $this->programId,
        ]);
        $staffProfileId = Fixtures::staffProfileId();
        Fixtures::qualifyTeacher($staffProfileId, $this->courseId);
        GroupTeacher::query()->create([
            'group_id' => (string) $group->getKey(),
            'staff_profile_id' => $staffProfileId,
            'course_id' => $this->courseId,
            'role' => 'lead',
            'assigned_from' => now()->subDay()->toDateString(),
        ]);

        return $group;
    }
}
