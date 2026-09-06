<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Academics\Domain\Models\Course;
use Modules\AccessControl\Database\Seeders\AccessControlSeeder;
use Modules\AccessControl\Infrastructure\Authorization\PermissionGateRegistrar;
use Modules\Attendance\Tests\Concerns\CreatesSessionParticipant;
use Modules\Groups\Domain\Models\Group;
use Modules\Groups\Domain\Models\GroupMembership;
use Modules\Groups\Domain\Models\GroupTeacher;
use Modules\Identity\Domain\Models\User;
use Modules\Scheduling\Domain\Contracts\IndividualTeachingAssignments;
use Modules\Sessions\Domain\Models\Session;
use Modules\Students\Domain\Models\StudentProfile;
use Tests\TestCase;

final class LearningPortalsTest extends TestCase
{
    use CreatesSessionParticipant;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['console.enabled' => true]);
    }

    public function test_gate_and_authentication_protect_new_portals_without_changing_old_routes(): void
    {
        $this->get('/learn/student')->assertRedirect(route('login'));
        $this->actingAs($this->user($this->organization()));
        config(['console.enabled' => false]);
        $this->get('/learn/student')->assertNotFound();
        $this->assertSame('student', Route::getRoutes()->getByName('portal.student.dashboard')->uri());
        $this->assertSame('teacher', Route::getRoutes()->getByName('portal.teacher.dashboard')->uri());
    }

    public function test_authenticated_account_without_a_student_profile_cannot_impersonate_one(): void
    {
        Gate::define('session.view', static fn (): bool => true);
        $user = $this->user($this->organization());
        $this->actingAs($user)->get('/learn/student?audience=self')->assertForbidden();
        $this->get('/learn/student/profile?student='.Str::ulid())->assertForbidden();
    }

    public function test_student_profile_and_ordinary_update_are_scoped_to_the_authenticated_account(): void
    {
        $org = $this->organization();
        [$student, $user] = $this->student($org);
        $user->update(['locale' => 'fr']);
        $this->actingAs($user)->get('/learn/student/profile?audience=admin&student='.Str::ulid())
            ->assertOk()->assertInertia(fn (Assert $page): Assert => $page->component('Learning/Profile')
            ->where('profile.id', $student->id)->where('own', true)->where('account.email', $user->email)
            ->where('locale', 'ar')->where('direction', 'rtl')->where('supportedLocales', ['ar']));
        $this->from('/learn/student/profile')->patch('/learn/student/profile', [
            'name' => 'Updated learner', 'phone' => null, 'timezone' => 'Africa/Cairo',
            'id' => (string) Str::ulid(), 'status' => 'suspended', 'roles' => ['super-admin'],
        ])->assertRedirect('/learn/student/profile')->assertSessionHasNoErrors();
        $this->assertSame('Updated learner', $user->fresh()->name);
        $this->assertSame('Africa/Cairo', $user->fresh()->timezone);
        $this->assertSame('fr', $user->fresh()->locale);
        $this->assertSame($user->status, $user->fresh()->status);
        $this->patch('/learn/student/profile', ['name' => 'Updated learner', 'locale' => 'en', 'timezone' => 'Africa/Cairo'])
            ->assertSessionHasNoErrors();
        $this->assertSame('fr', $user->fresh()->locale);
        $this->patch('/learn/student/profile', ['name' => 'Invalid zone', 'locale' => 'ar', 'timezone' => 'Africa/Unknown'])
            ->assertSessionHasErrors('timezone');
        $this->assertSame('Africa/Cairo', $user->fresh()->timezone);
    }

    public function test_teacher_student_projection_excludes_private_fields_and_rejects_unassigned_ids(): void
    {
        Gate::define('student.view', static fn (): bool => true);
        [$org, $teacher, $staffId, $student, $learner, $group, $assignment] = $this->assignedGroup();
        $response = $this->actingAs($teacher)->get('/learn/teacher/students/'.$student->id.'?audience=admin');
        $response->assertOk()->assertInertia(fn (Assert $page): Assert => $page->component('Learning/Profile')
            ->where('own', false)->where('account', null)->where('profile.name', $learner->name)
            ->missing('profile.email')->missing('profile.phone')->missing('profile.dateOfBirth')->missing('profile.notes')
            ->has('profile.tracks', 1)->has('profile.sessions', 0));
        [$other] = $this->student($org);
        $this->get('/learn/teacher/students/'.$other->id)->assertNotFound();
        [$foreign] = $this->student($this->organization());
        $this->get('/learn/teacher/students/'.$foreign->id)->assertNotFound();
        $assignment->update(['assigned_to' => now()->subDay()->toDateString()]);
        $this->get('/learn/teacher/students/'.$student->id)->assertNotFound();
    }

    public function test_future_teacher_assignment_and_left_membership_do_not_grant_profile_access(): void
    {
        Gate::define('student.view', static fn (): bool => true);
        [$org, $teacher, $staffId, $student, $learner, $group, $assignment] = $this->assignedGroup();
        $assignment->update(['assigned_from' => now()->addDay()->toDateString()]);
        $this->actingAs($teacher)->get('/learn/teacher/students/'.$student->id)->assertNotFound();
        $assignment->update(['assigned_from' => now()->subDay()->toDateString()]);
        DB::table('group_memberships')->where('group_id', $group->id)->update(['status' => 'left', 'left_at' => now()]);
        $this->get('/learn/teacher/students/'.$student->id)->assertNotFound();
    }

    public function test_active_individual_schedule_grants_profile_without_a_group_and_is_scoped(): void
    {
        Gate::define('student.view', static fn (): bool => true);
        $org = $this->organization();
        $teacher = $this->user($org);
        $staffId = $this->staff($org, $teacher);
        [$student] = $this->student($org);
        $course = Course::factory()->create(['organization_id' => $org]);
        $id = (string) Str::ulid();
        DB::table('schedules')->insert([
            'id' => $id, 'organization_id' => $org, 'student_profile_id' => $student->id,
            'course_id' => $course->id, 'staff_profile_id' => $staffId, 'session_type' => 'regular',
            'rrule' => 'FREQ=WEEKLY;BYDAY=MO', 'start_time' => '16:00', 'duration_minutes' => 30,
            'timezone' => 'Africa/Cairo', 'starts_on' => now()->subDay()->toDateString(),
            'materialized_until' => now()->toDateString(), 'is_active' => true, 'created_by' => $teacher->id,
        ]);
        $this->actingAs($teacher)->get('/learn/teacher/students/'.$student->id)->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page->where('profile.tracks.0.kind', 'individual')->where('own', false));
        $this->assertCount(0, app(IndividualTeachingAssignments::class)->activeForTeacher($this->organization(), $staffId));
        DB::table('schedules')->where('id', $id)->update(['ends_on' => now()->subDay()->toDateString()]);
        $this->get('/learn/teacher/students/'.$student->id)->assertNotFound();
        DB::table('schedules')->where('id', $id)->update(['ends_on' => null, 'starts_on' => now()->addDay()->toDateString()]);
        $this->get('/learn/teacher/students/'.$student->id)->assertNotFound();
        DB::table('schedules')->where('id', $id)->update(['starts_on' => now()->subDay()->toDateString(), 'is_active' => false]);
        $this->get('/learn/teacher/students/'.$student->id)->assertNotFound();
    }

    public function test_teacher_session_reuses_the_authorized_controller_contract(): void
    {
        Gate::define('attendance.record', static fn (): bool => true);
        $org = $this->organization();
        $teacher = $this->user($org);
        $staffId = $this->staff($org, $teacher);
        $course = Course::factory()->create(['organization_id' => $org]);
        $session = Session::factory()->create(['organization_id' => $org, 'staff_profile_id' => $staffId, 'course_id' => $course->id]);
        $this->actingAs($teacher)->get('/learn/teacher/sessions/'.$session->id)->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page->component('Learning/Session')->where('session.id', $session->id)
                ->where('kind', 'teacher')->where('timezone', 'Asia/Riyadh')->where('session.joinUrl', null)->has('attendance', 0));
        $this->get('/learn/teacher/sessions/'.Str::ulid())->assertNotFound();
    }

    public function test_session_writes_return_to_learning_and_expose_confirmation_state(): void
    {
        $this->seed(AccessControlSeeder::class);
        app(PermissionGateRegistrar::class)->register();
        $participantId = $this->createSessionParticipant();
        $participant = DB::table('session_participants')->where('id', $participantId)->first();
        $session = DB::table('sessions')->where('id', $participant->session_id)->first();
        $teacherId = DB::table('staff_profiles')->where('id', $session->staff_profile_id)->value('user_id');
        $teacher = User::query()->findOrFail($teacherId);
        $this->attachRole($teacher, 'teacher');
        $classroomId = (string) Str::ulid();
        DB::table('classrooms')->insert([
            'id' => $classroomId, 'session_id' => $session->id, 'provider' => 'bigbluebutton',
            'external_id' => 'LEARNING-'.$session->id, 'moderator_secret' => 'test', 'attendee_secret' => 'test',
            'created_remote_at' => now(), 'status' => 'running', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('classroom_events')->insert([
            'id' => (string) Str::ulid(), 'classroom_id' => $classroomId, 'idempotency_key' => hash('sha256', $classroomId),
            'event_type' => 'participant_joined', 'external_user_id' => $teacherId, 'user_id' => $teacherId,
            'occurred_at' => $session->scheduled_start, 'created_at' => now(),
        ]);
        $url = '/learn/teacher/sessions/'.$session->id;
        $this->actingAs($teacher)->get($url)->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->where('attendanceUpdateUrl', url($url.'/attendance'))->where('reportSubmitUrl', url($url.'/report'))
            ->where('attendance.0.confirmedAt', null));
        $this->from($url)->post($url.'/attendance', ['statuses' => [$participant->student_profile_id => 'present']])
            ->assertRedirect($url)->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertDatabaseHas('attendances', ['session_participant_id' => $participantId, 'status' => 'present', 'confirmed_by' => $teacherId]);
        $this->get($url)->assertOk()->assertInertia(fn (Assert $page): Assert => $page->where('attendance.0.status', 'present')
            ->where('attendance.0.confirmedAt', fn ($value): bool => is_string($value) && $value !== ''));
        $this->from($url)->post($url.'/report', ['summary' => 'تقرير حصة', 'notes' => '', 'students' => [[
            'student_profile_id' => $participant->student_profile_id, 'participation' => 4, 'performance' => 4, 'commitment' => 4,
        ]]])->assertRedirect($url)->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertDatabaseHas('session_reports', ['session_id' => $session->id, 'topics_covered' => 'تقرير حصة']);
        $this->get($url)->assertOk()->assertInertia(fn (Assert $page): Assert => $page->component('Learning/Session')->where('initialReport.summary', 'تقرير حصة'));
    }

    public function test_profile_return_validates_the_origin_session_and_password_save_stays_in_learning(): void
    {
        Http::fake(['https://api.pwnedpasswords.com/*' => Http::response('', 200)]);
        $this->seed(AccessControlSeeder::class);
        app(PermissionGateRegistrar::class)->register();
        [$org, $teacher, $staffId, $student] = $this->assignedGroup();
        $this->attachRole($teacher, 'teacher');
        $teacher->update(['password' => Hash::make('Original-Password-123!')]);
        $course = Course::factory()->create(['organization_id' => $org]);
        $session = Session::factory()->create(['organization_id' => $org, 'staff_profile_id' => $staffId, 'course_id' => $course->id]);
        $url = '/learn/teacher/sessions/'.$session->id;
        $this->actingAs($teacher)->get('/learn/teacher/students/'.$student->id.'?from='.$session->id)->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page->where('returnUrl', url($url)));
        $this->get('/learn/teacher/students/'.$student->id.'?from='.Str::ulid())->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page->where('returnUrl', route('learning.teacher.dashboard').'#studies'));
        $passwordUrl = '/learn/teacher/profile/password';
        $this->from('/learn/teacher/profile')->put($passwordUrl, [
            'current_password' => 'Original-Password-123!', 'password' => 'Replacement-Password-123!', 'password_confirmation' => 'Mismatch',
        ])->assertRedirect('/learn/teacher/profile')->assertSessionHasErrors('password');
        $this->assertTrue(Hash::check('Original-Password-123!', $teacher->fresh()->password));
        $this->from('/learn/teacher/profile')->put($passwordUrl, [
            'current_password' => 'Original-Password-123!', 'password' => 'Replacement-Password-123!', 'password_confirmation' => 'Replacement-Password-123!',
        ])->assertRedirect('/learn/teacher/profile')->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertTrue(Hash::check('Replacement-Password-123!', $teacher->fresh()->password));
        $this->get('/learn/teacher/profile')->assertOk();
    }

    /** End-to-end authorization with the actual seeded roles; no test Gate overrides. */
    public function test_seeded_roles_open_only_their_authorized_workspace_and_profiles(): void
    {
        $this->seed(AccessControlSeeder::class);
        app(PermissionGateRegistrar::class)->register();

        $this->get('/admin')->assertRedirect();
        $this->get('/student')->assertRedirect(route('login'));
        $this->get('/teacher')->assertRedirect(route('login'));

        [$org, $teacher, $staffId, $student, $learner] = $this->assignedGroup();
        $admin = $this->user($org);
        $this->attachRole($admin, 'platform_admin');
        $this->attachRole($learner, 'student');
        $this->attachRole($teacher, 'teacher');

        $this->actingAs($admin)->get('/manage')->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page->component('Console/Workspace'));
        $this->get('/learn/student')->assertForbidden();

        $this->actingAs($learner)->get('/learn/student')->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page->component('Learning/Dashboard')->where('kind', 'student'));
        $this->get('/learn/student/profile?audience=admin')->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page->where('profile.id', $student->id)->where('own', true));
        $this->get('/learn/teacher/profile')->assertForbidden();
        $this->get('/learn/teacher/students/'.$student->id)->assertForbidden();
        $this->get('/manage')->assertForbidden();
        $this->get('/admin')->assertForbidden();
        $this->get('/student')->assertOk();

        $this->actingAs($teacher)->get('/learn/teacher')->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page->component('Learning/Dashboard')->where('kind', 'teacher'));
        $this->get('/learn/teacher/profile')->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page->where('profile.id', $staffId)->where('own', true));
        $this->get('/learn/teacher/students/'.$student->id.'?audience=admin')->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page->where('profile.id', $student->id)
                ->where('own', false)->where('account', null)->missing('profile.email'));
        $this->get('/learn/student/profile')->assertForbidden();
        $this->get('/manage')->assertForbidden();
        $this->get('/admin')->assertForbidden();
        $this->get('/teacher')->assertOk();

        $unprivileged = $this->user($org);
        $this->actingAs($unprivileged)->get('/admin')->assertForbidden();
        $this->get('/student')->assertForbidden();
        $this->get('/teacher')->assertForbidden();
    }

    private function attachRole(User $user, string $name): void
    {
        $roleId = DB::table('roles')->whereNull('organization_id')->where('guard_name', 'web')->where('name', $name)->value('id');
        $this->assertIsString($roleId);
        DB::table('model_has_roles')->insert(['role_id' => $roleId, 'model_type' => User::class, 'model_id' => $user->id]);
    }

    private function organization(): string
    {
        $id = (string) Str::ulid();
        DB::table('organizations')->insert(['id' => $id, 'name' => json_encode(['ar' => 'Learning School']), 'slug' => strtolower($id), 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    private function user(string $org): User
    {
        return User::factory()->inOrganization($org)->create(['timezone' => 'Asia/Riyadh', 'locale' => 'ar']);
    }

    /** @return array{StudentProfile, User} */
    private function student(string $org): array
    {
        $user = $this->user($org);

        return [StudentProfile::factory()->create(['organization_id' => $org, 'user_id' => $user->id, 'notes' => 'private administrative notes']), $user];
    }

    private function staff(string $org, User $user): string
    {
        $id = (string) Str::ulid();
        DB::table('staff_profiles')->insert(['id' => $id, 'organization_id' => $org, 'user_id' => $user->id, 'staff_code' => $id, 'employment_type' => 'part_time', 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    /** @return array{string, User, string, StudentProfile, User, Group, GroupTeacher} */
    private function assignedGroup(): array
    {
        $org = $this->organization();
        $teacher = $this->user($org);
        $staffId = $this->staff($org, $teacher);
        [$student, $learner] = $this->student($org);
        $group = Group::factory()->active()->create(['organization_id' => $org]);
        $assignment = GroupTeacher::factory()->create(['group_id' => $group->id, 'staff_profile_id' => $staffId, 'assigned_from' => now()->subDay()->toDateString()]);
        GroupMembership::factory()->create(['group_id' => $group->id, 'student_profile_id' => $student->id]);

        return [$org, $teacher, $staffId, $student, $learner, $group, $assignment];
    }
}
