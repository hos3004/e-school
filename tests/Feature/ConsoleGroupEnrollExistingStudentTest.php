<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Modules\Academics\Domain\Models\Course;
use Modules\Academics\Domain\Models\Program;
use Modules\Groups\Domain\Enums\GroupStatus;
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

final class ConsoleGroupEnrollExistingStudentTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private Course $course;

    private Program $program;

    private string $countryId;

    private string $regionId;

    /** @var list<string> */
    private array $denied = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['console.enabled' => true]);
        $this->withoutVite();
        $this->seed(GeographySeeder::class);
        $this->actor = User::query()->findOrFail(Fixtures::userId());
        $this->course = Course::query()->findOrFail(Fixtures::courseId());
        $this->program = $this->course->level->program;
        $geography = app(GeographyQueries::class);
        $this->countryId = $geography->findCountryByIso2('EG')->id;
        $this->regionId = $geography->regionsOf($this->countryId)[0]->id;
        foreach (['admin.panel.access', 'student.view.any', 'enrollment.create', 'group.manage', 'group.view'] as $permission) {
            Gate::define($permission, fn (User $user): bool => $user->id === $this->actor->id && !in_array($permission, $this->denied, true));
        }
        $this->actingAs($this->actor);
    }

    public function test_adds_an_existing_student_with_no_prior_application_straight_into_the_group(): void
    {
        $group = $this->activeGroup(2);
        $profile = $this->existingStudentProfile();

        $this->assertDatabaseCount('registration_applications', 0);

        $this->post('/manage/groups/'.$group->id.'/students', [
            'student_profile_id' => $profile->id,
            'course_id' => $this->course->id,
        ])->assertRedirect('/manage/groups/'.$group->id);

        $this->assertDatabaseHas('group_memberships', [
            'group_id' => $group->id, 'student_profile_id' => $profile->id, 'status' => 'active',
        ]);
        $this->assertDatabaseHas('enrollments', [
            'student_profile_id' => $profile->id, 'program_id' => $this->program->id, 'status' => 'active',
        ]);
        $application = RegistrationApplication::query()->where('student_profile_id', $profile->id)->firstOrFail();
        $this->assertSame(RegistrationStatus::Assigned, $application->status);
    }

    public function test_a_pending_waitlist_entry_for_another_course_does_not_block_group_enrollment(): void
    {
        $group = $this->activeGroup(2);
        $profile = $this->existingStudentProfile();

        $otherCourse = Course::factory()->create(['organization_id' => $this->actor->organization_id, 'level_id' => $this->course->level_id]);
        RegistrationApplication::query()->create([
            'organization_id' => $this->actor->organization_id, 'user_id' => $profile->user_id,
            'status' => RegistrationStatus::Submitted, 'full_name' => 'نفس الطالب لكورس آخر',
            'preferred_program_id' => $this->program->id, 'preferred_course_id' => $otherCourse->id,
            'submitted_at' => now(),
        ]);

        $this->post('/manage/groups/'.$group->id.'/students', [
            'student_profile_id' => $profile->id,
            'course_id' => $this->course->id,
        ])->assertRedirect('/manage/groups/'.$group->id);

        $this->assertDatabaseHas('group_memberships', [
            'group_id' => $group->id, 'student_profile_id' => $profile->id, 'status' => 'active',
        ]);
    }

    public function test_rejects_unknown_student_profile(): void
    {
        $group = $this->activeGroup(2);

        $this->post('/manage/groups/'.$group->id.'/students', [
            'student_profile_id' => (string) Str::ulid(),
            'course_id' => $this->course->id,
        ])->assertRedirect()->assertSessionHasErrors('form');

        $this->assertDatabaseCount('group_memberships', 0);
    }

    public function test_requires_all_permissions(): void
    {
        $group = $this->activeGroup(2);
        $profile = $this->existingStudentProfile();

        foreach (['student.view.any', 'enrollment.create', 'group.manage'] as $permission) {
            $this->denied = [$permission];
            $this->post('/manage/groups/'.$group->id.'/students', [
                'student_profile_id' => $profile->id, 'course_id' => $this->course->id,
            ])->assertForbidden();
        }
        $this->assertDatabaseCount('group_memberships', 0);
    }

    public function test_capacity_full_rejects_enrollment(): void
    {
        $group = $this->activeGroup(1);
        $first = $this->existingStudentProfile();
        $this->post('/manage/groups/'.$group->id.'/students', [
            'student_profile_id' => $first->id, 'course_id' => $this->course->id,
        ])->assertRedirect('/manage/groups/'.$group->id);

        $second = $this->existingStudentProfile();
        $this->post('/manage/groups/'.$group->id.'/students', [
            'student_profile_id' => $second->id, 'course_id' => $this->course->id,
        ])->assertRedirect()->assertSessionHasErrors('form');

        $this->assertDatabaseCount('group_memberships', 1);
        // الطلب الذي أنشأه المحاولة الفاشلة لا يبقى معلّقًا في قائمة الانتظار.
        $this->assertDatabaseMissing('registration_applications', [
            'student_profile_id' => $second->id, 'deleted_at' => null,
        ]);
        $this->assertSoftDeleted('registration_applications', ['student_profile_id' => $second->id]);
    }

    public function test_individual_session_course_is_rejected_even_with_a_crafted_request(): void
    {
        $group = $this->activeGroup(2);
        $profile = $this->existingStudentProfile();
        $individualCourse = Course::factory()->create([
            'organization_id' => $this->actor->organization_id,
            'level_id' => $this->course->level_id,
            'session_mode' => 'individual',
        ]);
        GroupProgram::query()->firstOrCreate(['group_id' => $group->id, 'program_id' => $this->program->id]);

        $this->post('/manage/groups/'.$group->id.'/students', [
            'student_profile_id' => $profile->id, 'course_id' => $individualCourse->id,
        ])->assertRedirect()->assertSessionHasErrors('form');

        $this->assertDatabaseCount('group_memberships', 0);
        $this->assertDatabaseCount('registration_applications', 0);
    }

    private function activeGroup(int $capacity): Group
    {
        $group = Group::query()->create([
            'organization_id' => $this->actor->organization_id, 'code' => 'G'.substr((string) Str::ulid(), -6),
            'name' => ['ar' => 'مجموعة الاختبار'], 'capacity' => $capacity, 'timezone' => 'Africa/Cairo',
            'status' => GroupStatus::Active, 'starts_on' => now()->toDateString(),
        ]);
        GroupProgram::query()->create(['group_id' => $group->id, 'program_id' => $this->program->id]);
        $staffId = Fixtures::staffProfileId();
        Fixtures::qualifyTeacher($staffId, $this->course->id);
        GroupTeacher::query()->create(['group_id' => $group->id, 'staff_profile_id' => $staffId, 'course_id' => $this->course->id, 'role' => 'lead', 'assigned_from' => now()->subDay()->toDateString()]);

        return $group;
    }

    private function existingStudentProfile(): StudentProfile
    {
        return StudentProfile::query()->create([
            'organization_id' => $this->actor->organization_id, 'user_id' => Fixtures::userId(),
            'student_code' => 'E'.substr((string) Str::ulid(), -7), 'date_of_birth' => '2010-01-01',
            'gender' => 'male', 'country_id' => $this->countryId, 'region_id' => $this->regionId, 'joined_at' => now()->toDateString(),
        ]);
    }
}
