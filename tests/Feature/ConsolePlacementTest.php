<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Academics\Domain\Models\Course;
use Modules\Academics\Domain\Models\Level;
use Modules\Academics\Domain\Models\Program;
use Modules\Academics\Domain\Models\ProgramEligibility;
use Modules\AccessControl\Database\Seeders\AccessControlSeeder;
use Modules\Groups\Domain\Enums\GroupStatus;
use Modules\Groups\Domain\Models\Group;
use Modules\Groups\Domain\Models\GroupProgram;
use Modules\Groups\Domain\Models\GroupTeacher;
use Modules\Identity\Domain\Enums\UserStatus;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Database\Seeders\GeographySeeder;
use Modules\Organization\Domain\Contracts\GeographyQueries;
use Modules\Organization\Domain\Models\Organization;
use Modules\Scheduling\Domain\Models\Schedule;
use Modules\Scheduling\Domain\Models\ScheduleWeeklySlot;
use Modules\Students\Domain\Enums\RegistrationStatus;
use Modules\Students\Domain\Events\RegistrationAccepted;
use Modules\Students\Domain\Models\RegistrationApplication;
use Modules\Students\Domain\Models\StudentProfile;
use Shared\Testing\Fixtures;
use Tests\TestCase;

final class ConsolePlacementTest extends TestCase
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
        foreach (['admin.panel.access', 'student.view', 'student.view.any', 'student.create', 'enrollment.create', 'group.manage'] as $permission) {
            Gate::define($permission, fn (User $user): bool => $user->id === $this->actor->id && !in_array($permission, $this->denied, true));
        }
        $this->actingAs($this->actor);
    }

    public function test_places_a_real_student_and_keeps_group_and_distinct_schedule_times_after_save(): void
    {
        $group = $this->activeGroup(2);
        $application = $this->application();
        $schedule = $this->schedule($group);
        $url = '/manage/placement?course='.$this->course->id.'&application='.$application->id;
        $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->component('Console/Placement')
            ->where('applications.data.0.id', $application->id)
            ->where('groups.0.schedules.0.weekly_slots.0.start_time', '17:30')
            ->where('groups.0.schedules.0.weekly_slots.1.start_time', '18:15')
            ->where('groups.0.schedules.0.timezone', 'Africa/Cairo'));
        $this->postJson('/manage/placement/preflight', $this->payload([$application], $group))->assertOk()
            ->assertJsonPath('eligible_count', 1)->assertJsonPath('remaining_seats', 2);
        $this->assertDatabaseCount('group_memberships', 0);
        $this->postJson('/manage/placement', $this->payload([$application], $group))->assertOk()
            ->assertJsonPath('placed_count', 1)->assertJsonPath('group_id', $group->id)
            ->assertJsonPath('applications.0.memberships.0.group_id', $group->id);
        $this->assertDatabaseHas('enrollments', ['student_profile_id' => $application->student_profile_id, 'program_id' => $this->program->id, 'status' => 'active']);
        $this->assertDatabaseHas('group_memberships', ['student_profile_id' => $application->student_profile_id, 'group_id' => $group->id, 'status' => 'active']);
        $this->assertDatabaseHas('audit_log', ['action' => 'enrollment.bulk_placed', 'auditable_id' => $group->id]);
        $this->assertSame(RegistrationStatus::Assigned, $application->fresh()->status);
        $this->postJson('/manage/placement', $this->payload([$application], $group))->assertUnprocessable()->assertJsonValidationErrors('form');
        $this->assertDatabaseCount('enrollments', 1);
        $this->assertDatabaseCount('group_memberships', 1);
        $this->get('/manage/placement?course='.$this->course->id.'&status=assigned')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('applications.data', 1)->where('applications.data.0.memberships.0.group_id', $group->id)
            ->where('groups.0.schedules.0.id', $schedule->id)->where('groups.0.remaining_seats', 1));
    }

    public function test_capacity_and_eligibility_failures_roll_back_the_whole_batch(): void
    {
        $group = $this->activeGroup(1);
        $first = $this->application();
        $second = $this->application();
        $payload = $this->payload([$first, $second], $group);
        $this->postJson('/manage/placement/preflight', $payload)->assertOk()->assertJsonPath('eligible_count', 2)
            ->assertJson(fn ($json) => $json->whereType('capacity_warning', 'string')->etc());
        $this->postJson('/manage/placement', $payload)->assertUnprocessable()->assertJsonValidationErrors('form');
        $this->assertDatabaseCount('enrollments', 0);
        $this->assertDatabaseCount('group_memberships', 0);
        ProgramEligibility::query()->updateOrCreate(['program_id' => $this->program->id], ['gender' => 'female']);
        $this->postJson('/manage/placement', $this->payload([$first], $group))->assertUnprocessable()->assertJsonValidationErrors('form');
        $this->assertDatabaseCount('enrollments', 0);
        $this->assertDatabaseCount('group_memberships', 0);
        $this->assertSame(RegistrationStatus::WaitingAssignment, $first->fresh()->status);
    }

    public function test_new_group_uses_official_draft_action_and_pending_membership(): void
    {
        $application = $this->application();
        $this->postJson('/manage/placement', [
            'application_ids' => [$application->id], 'course_id' => $this->course->id,
            'group_id' => null, 'new_group_name' => 'مجموعة القبول الجديدة',
        ])->assertOk()->assertJsonPath('group_is_draft', true)
            ->assertJsonPath('applications.0.memberships.0.status', 'pending');
        $group = Group::query()->firstOrFail();
        $this->assertSame(GroupStatus::Planning, $group->status);
        $this->assertSame(['ar' => 'مجموعة القبول الجديدة'], $group->name);
        $this->assertDatabaseHas('group_programs', ['group_id' => $group->id, 'program_id' => $this->program->id]);
        $this->assertDatabaseHas('group_memberships', ['group_id' => $group->id, 'status' => 'pending']);
        $this->assertDatabaseMissing('group_memberships', ['group_id' => $group->id, 'status' => 'active']);
    }

    public function test_foreign_ids_and_non_cleared_students_cannot_reach_placement(): void
    {
        $group = $this->activeGroup(4);
        $application = $this->application();
        $other = Organization::factory()->create();
        $foreignProgram = Program::factory()->create(['organization_id' => $other->id]);
        $level = Level::factory()->create(['program_id' => $foreignProgram->id]);
        $foreignCourse = Course::factory()->create(['organization_id' => $other->id, 'level_id' => $level->id]);
        $foreignGroup = Group::query()->create(['organization_id' => $other->id, 'code' => 'OTHER', 'name' => ['ar' => 'خاصة'], 'status' => GroupStatus::Planning, 'timezone' => 'UTC']);
        $foreignApplication = RegistrationApplication::query()->create([
            'organization_id' => $other->id, 'status' => RegistrationStatus::Submitted,
            'full_name' => 'طالب مؤسسة أخرى', 'gender' => 'male', 'country_id' => $this->countryId,
            'region_id' => $this->regionId, 'date_of_birth' => '2010-01-01',
        ]);
        $payload = $this->payload([$application], $group);
        $this->postJson('/manage/placement', [...$payload, 'application_ids' => [$application->id, $foreignApplication->id]])->assertNotFound();
        $this->postJson('/manage/placement/preflight', [...$payload, 'application_ids' => [$foreignApplication->id]])->assertNotFound();
        $this->get('/manage/placement?application='.$foreignApplication->id)->assertNotFound();
        $this->postJson('/manage/placement', [...$payload, 'course_id' => $foreignCourse->id])->assertNotFound();
        $this->postJson('/manage/placement', [...$payload, 'group_id' => $foreignGroup->id])->assertNotFound();
        $this->postJson('/manage/placement', [...$payload, 'organization_id' => $other->id])->assertUnprocessable()->assertJsonValidationErrors('organization_id');
        $application->update(['status' => RegistrationStatus::Submitted, 'student_profile_id' => null]);
        $this->postJson('/manage/placement', $payload)->assertForbidden();
        $this->assertDatabaseCount('enrollments', 0);
        $this->assertDatabaseCount('group_memberships', 0);
    }

    public function test_requires_all_permissions_for_lists_preflight_and_writes(): void
    {
        $group = $this->activeGroup(4);
        $application = $this->application();
        foreach (['student.view.any', 'enrollment.create', 'group.manage'] as $permission) {
            $this->denied = [$permission];
            $this->get('/manage/placement')->assertForbidden();
            $this->postJson('/manage/placement/preflight', $this->payload([$application], $group))->assertForbidden();
            $this->postJson('/manage/placement', $this->payload([$application], $group))->assertForbidden();
        }
        $this->denied = ['student.view'];
        $this->postJson('/manage/placement', $this->payload([$application], $group))->assertForbidden();
        $this->assertDatabaseCount('group_memberships', 0);
    }

    public function test_multiple_selected_requests_share_one_seat_and_only_selected_requests_change(): void
    {
        $group = $this->activeGroup(1);
        $old = $this->application();
        $oldBefore = $old->fresh()->getRawOriginal();
        $new = $old->replicate();
        $new->full_name = 'طلب الكورس الجديد';
        $new->save();
        $this->postJson('/manage/placement', $this->payload([$new], $group))->assertOk();
        $this->assertSame($oldBefore, $old->fresh()->getRawOriginal());
        $this->assertSame(RegistrationStatus::Assigned, $new->fresh()->status);
        $third = $old->replicate();
        $third->full_name = 'طلب آخر مختار';
        $third->save();
        $this->postJson('/manage/placement/preflight', $this->payload([$old, $third], $group))->assertOk()
            ->assertJsonPath('eligible_count', 1)->assertJsonPath('capacity_warning', null);
        $this->postJson('/manage/placement', $this->payload([$old, $third], $group))->assertOk()->assertJsonPath('placed_count', 0);
        $this->assertSame(RegistrationStatus::Assigned, $old->fresh()->status);
        $this->assertSame(RegistrationStatus::Assigned, $third->fresh()->status);
        $this->assertDatabaseCount('enrollments', 1);
        $this->assertDatabaseCount('group_memberships', 1);
    }

    public function test_duplicate_requests_for_one_student_do_not_exhaust_capacity_and_failure_rolls_back_all_requests(): void
    {
        $group = $this->activeGroup(2);
        $first = $this->application();
        $duplicate = $first->replicate();
        $duplicate->save();
        $bad = $this->application();
        StudentProfile::query()->whereKey($bad->student_profile_id)->update(['gender' => 'female']);
        ProgramEligibility::query()->updateOrCreate(['program_id' => $this->program->id], ['gender' => 'male']);
        $this->postJson('/manage/placement/preflight', $this->payload([$first, $duplicate, $bad], $group))->assertOk()
            ->assertJsonPath('eligible_count', 2)->assertJsonPath('capacity_warning', null);
        $this->postJson('/manage/placement', $this->payload([$first, $duplicate, $bad], $group))->assertUnprocessable()->assertJsonValidationErrors('form');
        $this->assertDatabaseCount('enrollments', 0);
        $this->assertDatabaseCount('group_memberships', 0);
        foreach ([$first, $duplicate, $bad] as $application) {
            $this->assertSame(RegistrationStatus::WaitingAssignment, $application->fresh()->status);
        }
        $group->update(['capacity' => 1]);
        $this->postJson('/manage/placement', $this->payload([$first, $duplicate], $group))->assertOk()->assertJsonPath('placed_count', 1);
        $this->assertSame(RegistrationStatus::Assigned, $first->fresh()->status);
        $this->assertSame(RegistrationStatus::Assigned, $duplicate->fresh()->status);
        $this->assertDatabaseCount('enrollments', 1);
        $this->assertDatabaseCount('group_memberships', 1);
    }

    public function test_student_archived_or_account_suspended_after_acceptance_cannot_be_placed(): void
    {
        $group = $this->activeGroup(2);
        $application = $this->application();
        $user = User::query()->findOrFail($application->user_id);
        foreach ([UserStatus::Suspended, UserStatus::Frozen] as $status) {
            $user->update(['status' => $status]);
            $this->postJson('/manage/placement', $this->payload([$application], $group))->assertUnprocessable()->assertJsonValidationErrors('form');
            $this->assertSame($status, $user->fresh()->status);
        }
        $user->update(['status' => UserStatus::Active]);
        StudentProfile::query()->findOrFail($application->student_profile_id)->delete();
        $this->postJson('/manage/placement', $this->payload([$application], $group))->assertUnprocessable()->assertJsonValidationErrors('form');
        $this->assertSoftDeleted('student_profiles', ['id' => $application->student_profile_id]);
        $this->assertSame(RegistrationStatus::WaitingAssignment, $application->fresh()->status);
        $this->assertDatabaseCount('enrollments', 0);
        $this->assertDatabaseCount('group_memberships', 0);
    }

    public function test_accepting_an_existing_student_for_another_course_reaches_placement_without_duplicate_enrollment(): void
    {
        $this->seed(AccessControlSeeder::class);
        Event::fake([RegistrationAccepted::class]);
        $group = $this->activeGroup(1);
        $old = $this->application();
        $this->postJson('/manage/placement', $this->payload([$old], $group))->assertOk();
        $oldBefore = $old->fresh()->getRawOriginal();
        $studentBefore = StudentProfile::query()->findOrFail($old->student_profile_id)->getRawOriginal();
        $course = Course::factory()->create(['organization_id' => $this->actor->organization_id, 'level_id' => $this->course->level_id]);
        $staff = GroupTeacher::query()->where('group_id', $group->id)->firstOrFail()->staff_profile_id;
        Fixtures::qualifyTeacher($staff, $course->id);
        GroupTeacher::query()->create(['group_id' => $group->id, 'staff_profile_id' => $staff, 'course_id' => $course->id, 'role' => 'lead', 'assigned_from' => now()->subDay()->toDateString()]);
        $user = User::query()->findOrFail($old->user_id);
        $new = $old->fresh()->replicate();
        $new->fill(['status' => RegistrationStatus::Submitted, 'user_id' => null, 'student_profile_id' => null,
            'email' => $user->email, 'preferred_course_id' => $course->id, 'duplicate_of_application_id' => $old->id]);
        $new->save();
        $this->post('/manage/registration/applications/'.$new->id.'/decision', [
            'decision' => 'accept', 'account_mode' => 'existing', 'existing_user_id' => $user->id,
            'identity_confirmed' => true, 'timezone' => 'Africa/Cairo',
        ])->assertSessionHasNoErrors();
        $this->assertSame($old->student_profile_id, $new->fresh()->student_profile_id);
        $this->assertSame(RegistrationStatus::WaitingAssignment, $new->fresh()->status);
        $this->get('/manage/placement?application='.$new->id)->assertOk()->assertInertia(fn (Assert $page) => $page->where('filters.course', $course->id));
        $this->postJson('/manage/placement', [
            ...$this->payload([$new], $group), 'course_id' => $course->id,
        ])->assertOk();
        $this->assertSame(RegistrationStatus::Assigned, $new->fresh()->status);
        $this->assertSame($oldBefore, $old->fresh()->getRawOriginal());
        $this->assertSame($studentBefore, StudentProfile::query()->findOrFail($old->student_profile_id)->getRawOriginal());
        $this->assertDatabaseCount('enrollments', 1);
        $this->assertDatabaseCount('group_memberships', 1);
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

    private function application(): RegistrationApplication
    {
        $profile = StudentProfile::query()->create([
            'organization_id' => $this->actor->organization_id, 'user_id' => Fixtures::userId(),
            'student_code' => 'E'.substr((string) Str::ulid(), -7), 'date_of_birth' => '2010-01-01',
            'gender' => 'male', 'country_id' => $this->countryId, 'region_id' => $this->regionId, 'joined_at' => now()->toDateString(),
        ]);

        return RegistrationApplication::query()->create([
            'organization_id' => $this->actor->organization_id, 'user_id' => $profile->user_id,
            'student_profile_id' => $profile->id, 'status' => RegistrationStatus::WaitingAssignment,
            'full_name' => 'طالب '.substr((string) Str::ulid(), -5), 'date_of_birth' => '2010-01-01',
            'gender' => 'male', 'country_id' => $this->countryId, 'region_id' => $this->regionId,
            'preferred_program_id' => $this->program->id, 'preferred_course_id' => $this->course->id,
        ]);
    }

    private function schedule(Group $group): Schedule
    {
        $schedule = Schedule::query()->create([
            'organization_id' => $this->actor->organization_id, 'group_id' => $group->id,
            'course_id' => $this->course->id, 'staff_profile_id' => Fixtures::staffProfileId(), 'session_type' => 'group',
            'rrule' => 'FREQ=WEEKLY;INTERVAL=1;BYDAY=MO,WE', 'start_time' => '17:30',
            'duration_minutes' => 35, 'timezone' => 'Africa/Cairo', 'starts_on' => now()->toDateString(),
            'materialized_until' => now()->toDateString(), 'is_active' => true, 'created_by' => $this->actor->id,
        ]);
        foreach ([1 => '17:30', 3 => '18:15'] as $weekday => $time) {
            ScheduleWeeklySlot::query()->create(['organization_id' => $this->actor->organization_id, 'schedule_id' => $schedule->id, 'weekday' => $weekday, 'start_time' => $time]);
        }

        return $schedule;
    }

    /** @param list<RegistrationApplication> $applications
     * @return array<string, mixed>
     */
    private function payload(array $applications, Group $group): array
    {
        return ['application_ids' => array_map(static fn ($item): string => $item->id, $applications), 'course_id' => $this->course->id, 'group_id' => $group->id, 'new_group_name' => null];
    }
}
