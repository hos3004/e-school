<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Academics\Domain\Models\Course;
use Modules\Academics\Domain\Models\Level;
use Modules\Academics\Domain\Models\Program;
use Modules\AccessControl\Database\Seeders\AccessControlSeeder;
use Modules\AccessControl\Domain\Contracts\RoleAssignmentGateway;
use Modules\AccessControl\Infrastructure\Authorization\PermissionGateRegistrar;
use Modules\Groups\Domain\Models\Group;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Domain\Models\Organization;
use Shared\Testing\Fixtures;
use Tests\TestCase;

final class ConsoleFirstCourseGroupContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_group_redirect_keeps_its_course_for_teacher_assignment_and_rejects_unrelated_context(): void
    {
        config(['console.enabled' => true]);
        $this->withoutVite();
        $this->seed(AccessControlSeeder::class);
        app(PermissionGateRegistrar::class)->register();
        $actor = User::query()->findOrFail(Fixtures::userId());
        app(RoleAssignmentGateway::class)->assignIfMissing('platform_admin', User::class, $actor->id, $actor->organization_id);
        $this->actingAs($actor);
        $base = Course::query()->findOrFail(Fixtures::courseId());
        $response = $this->post('/manage/courses/items', [
            'code' => 'FIRST-GROUP-CONTEXT', 'name' => ['ar' => 'كورس بمجموعته الأولى'],
            'level_id' => $base->level_id, 'session_mode' => 'group', 'is_active' => true,
            'first_group_name' => 'المجموعة الأولى للكورس', 'first_group_capacity' => 8,
            'first_group_starts_on' => now()->toDateString(),
        ])->assertSessionHasNoErrors()->assertRedirect();
        $course = Course::query()->where('code', 'FIRST-GROUP-CONTEXT')->sole();
        $group = Group::query()->where('name->ar', 'المجموعة الأولى للكورس')->sole();
        $destination = $response->headers->get('Location');
        parse_str((string) parse_url($destination, PHP_URL_QUERY), $parameters);
        $this->assertSame(['group' => $group->id, 'course' => $course->id, 'action' => 'assign'], $parameters);
        $this->get($destination)->assertOk()->assertInertia(fn (Assert $page): Assert => $page->component('Console/Courses')
            ->where('initialTab', 'groups')->where('initialGroupAction', 'assign')->where('initialGroupId', $group->id)->where('initialCourseId', $course->id));
        $this->assertDatabaseHas('group_programs', ['group_id' => $group->id, 'program_id' => $base->level->program_id]);

        $teacher = Fixtures::staffProfileId();
        DB::table('staff_profiles')->where('id', $teacher)->update(['employment_type' => 'part_time']);
        DB::table('teacher_courses')->insert(['id' => (string) Str::ulid(), 'staff_profile_id' => $teacher,
            'course_id' => $course->id, 'qualified_at' => now(), 'qualified_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->post('/manage/groups/'.$group->id.'/teachers', ['course_id' => $parameters['course'], 'staff_profile_id' => $teacher,
            'role' => 'lead', 'assigned_from' => now()->toDateString()])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('group_teachers', ['group_id' => $group->id, 'course_id' => $course->id, 'staff_profile_id' => $teacher]);

        $otherProgram = Program::factory()->create(['organization_id' => $actor->organization_id]);
        $otherLevel = Level::factory()->create(['program_id' => $otherProgram->id]);
        $otherCourse = Course::factory()->create(['organization_id' => $actor->organization_id, 'level_id' => $otherLevel->id]);
        $foreignOrg = Organization::factory()->create();
        $foreignProgram = Program::factory()->create(['organization_id' => $foreignOrg->id]);
        $foreignLevel = Level::factory()->create(['program_id' => $foreignProgram->id]);
        $foreignCourse = Course::factory()->create(['organization_id' => $foreignOrg->id, 'level_id' => $foreignLevel->id]);
        foreach ([$otherCourse->id, $foreignCourse->id] as $invalid) {
            $this->get('/manage/groups?'.http_build_query(['group' => $group->id, 'course' => $invalid, 'action' => 'assign']))
                ->assertOk()->assertInertia(fn (Assert $page): Assert => $page->where('initialGroupId', $group->id)->where('initialCourseId', null));
        }
        $foreignGroup = Group::factory()->create(['organization_id' => $foreignOrg->id]);
        $this->get('/manage/groups?'.http_build_query(['group' => $foreignGroup->id, 'course' => $course->id, 'action' => 'assign']))
            ->assertOk()->assertInertia(fn (Assert $page): Assert => $page->where('initialGroupId', null)->where('initialCourseId', null));
    }
}
