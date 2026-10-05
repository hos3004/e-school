<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\AccessControl\Database\Seeders\AccessControlSeeder;
use Modules\AccessControl\Infrastructure\Authorization\PermissionGateRegistrar;
use Modules\Assignments\Domain\Models\Assignment;
use Modules\Attendance\Tests\Concerns\CreatesSessionParticipant;
use Modules\Identity\Domain\Enums\UserStatus;
use Modules\Identity\Domain\Models\User;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Models\Session;
use Modules\Students\Domain\Models\StudentProfile;
use Tests\TestCase;

final class LearningAuthTest extends TestCase
{
    use CreatesSessionParticipant;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Http::fake(['api.pwnedpasswords.com/*' => Http::response('', 200)]);
        config(['console.enabled' => true]);
        $this->seed(AccessControlSeeder::class);
        app(PermissionGateRegistrar::class)->register();
    }

    public function test_new_login_and_recovery_use_arabic_reference_pages_only_while_enabled(): void
    {
        $this->get('/login?portal=teacher')->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->component('Auth/LearningLogin')->where('portal', 'teacher')->where('locale', 'ar')->where('direction', 'rtl')
            ->where('translations', fn ($lines): bool => ($lines['learning.entry.welcome'] ?? null) === 'أهلاً بك في الأكاديمية'));
        $this->get('/forgot-password')->assertOk()->assertInertia(fn (Assert $page): Assert => $page->component('Auth/LearningForgotPassword'));
        $this->get('/reset-password/test-token')->assertOk()->assertInertia(fn (Assert $page): Assert => $page->component('Auth/LearningResetPassword')->where('token', 'test-token'));
        config(['console.enabled' => false]);
        $this->get('/login')->assertOk()->assertInertia(fn (Assert $page): Assert => $page->component('Auth/Login'));
        $this->get('/forgot-password')->assertOk()->assertInertia(fn (Assert $page): Assert => $page->component('Auth/ForgotPassword'));
        $this->actingAs($this->account('student'))->get('/learn/entry')->assertNotFound();
    }

    public function test_student_login_uses_actual_student_profile_even_when_teacher_is_selected(): void
    {
        $user = $this->account('student');
        $this->post('/login', ['login' => $user->username, 'password' => 'password', 'portal' => 'teacher'])
            ->assertRedirect('/learn/student');
        $this->assertAuthenticatedAs($user);
        $this->get('/')->assertRedirect('/learn/student');
        $this->get('/learn/entry')->assertOk()->assertInertia(fn (Assert $page): Assert => $page->component('Auth/LearningLogin')
            ->where('portals.student', route('learning.student.dashboard'))->missing('portals.teacher')->missing('portals.admin'));
        $this->get('/learn/teacher')->assertForbidden();
        $this->get('/manage')->assertForbidden();
    }

    public function test_teacher_and_admin_login_reach_their_authorized_new_workspaces(): void
    {
        $teacher = $this->account('teacher');
        $this->post('/login', ['login' => $teacher->username, 'password' => 'password', 'portal' => 'student'])
            ->assertRedirect('/learn/teacher');
        $this->get('/learn/teacher')->assertOk()->assertInertia(fn (Assert $page): Assert => $page->component('Learning/Dashboard')
            ->where('kind', 'teacher')->where('earnings', null)->has('availability', 0));
        $this->post('/logout');
        $admin = $this->account('platform_admin');
        $this->post('/login', ['login' => $admin->username, 'password' => 'password'])
            ->assertRedirect('/manage');
        $this->get('/learn/student')->assertForbidden();
    }

    public function test_intended_new_and_old_routes_are_preserved_and_permissions_still_apply(): void
    {
        $user = $this->account('student');
        $this->get('/learn/student/profile')->assertRedirect('/login');
        $this->post('/login', ['login' => $user->username, 'password' => 'password'])->assertRedirect('/learn/student/profile');
        $this->post('/logout');
        $this->get('/student/profile')->assertRedirect('/login');
        $this->post('/login', ['login' => $user->username, 'password' => 'password'])->assertRedirect('/student/profile');
        $this->post('/logout');
        $this->get('/learn/teacher')->assertRedirect('/login');
        $this->post('/login', ['login' => $user->username, 'password' => 'password'])->assertRedirect('/learn/teacher');
        $this->get('/learn/teacher')->assertForbidden();
    }

    public function test_bad_credentials_and_suspended_accounts_never_enter_a_portal(): void
    {
        $user = $this->account('teacher');
        $this->from('/login')->post('/login', ['login' => $user->username, 'password' => 'incorrect', 'portal' => 'admin'])
            ->assertRedirect('/login')->assertSessionHasErrors('login');
        $this->assertGuest();
        $user->update(['status' => UserStatus::Suspended]);
        $this->post('/login', ['login' => $user->username, 'password' => 'password'])->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_password_recovery_keeps_its_real_secure_flow_with_the_new_appearance(): void
    {
        Notification::fake();
        $user = $this->account('student');
        $unknown = $this->from('/forgot-password')->post('/forgot-password', ['email' => 'unknown@example.invalid'])
            ->assertSessionHasNoErrors()->assertSessionHas('status')->baseResponse->getSession()->get('status');
        Notification::assertNothingSent();
        $known = $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status')->baseResponse->getSession()->get('status');
        $this->assertSame($unknown, $known);
        $token = app('auth.password.broker')->createToken($user);
        $this->post('/reset-password', ['token' => $token, 'email' => $user->email,
            'password' => 'New-Secret-2026', 'password_confirmation' => 'New-Secret-2026'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('New-Secret-2026', (string) $user->fresh()->getAuthPassword()));
        $this->post('/login', ['login' => $user->username, 'password' => 'New-Secret-2026'])->assertRedirect('/learn/student');
    }

    public function test_disabling_console_preserves_legacy_auth_response_and_home(): void
    {
        $user = $this->account('student');
        config(['console.enabled' => false]);
        $this->post('/login', ['login' => $user->username, 'password' => 'password'])->assertRedirect('/');
        $this->get('/')->assertRedirect('/student');
        $this->get('/student')->assertOk();
    }

    public function test_assignment_submission_stays_in_learning_and_rejects_a_foreign_student(): void
    {
        $participantId = $this->createSessionParticipant();
        $participant = DB::table('session_participants')->where('id', $participantId)->first();
        $session = DB::table('sessions')->where('id', $participant->session_id)->first();
        $profile = StudentProfile::query()->findOrFail($participant->student_profile_id);
        $student = User::query()->findOrFail($profile->user_id);
        $org = $this->organizationId;
        $roleId = DB::table('roles')->whereNull('organization_id')->where('guard_name', 'web')->where('name', 'student')->value('id');
        DB::table('model_has_roles')->insert(['role_id' => $roleId, 'model_type' => User::class, 'model_id' => $student->id]);
        $assignment = Assignment::factory()->create([
            'organization_id' => $org, 'course_id' => $session->course_id, 'group_id' => null, 'staff_profile_id' => $session->staff_profile_id,
            'title' => ['ar' => 'تدريب القراءة'], 'due_at' => now()->addDay(),
        ]);
        $this->actingAs($student)->get('/learn/student')->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->has('allAssignments', 1)->where('allAssignments.0.id', $assignment->id));
        $url = '/learn/student/assignments/'.$assignment->id.'/submit';
        $this->from('/learn/student')->post($url, ['content' => 'إجابتي الأولى'])->assertRedirect('/learn/student')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('assignment_submissions', ['assignment_id' => $assignment->id, 'student_profile_id' => $profile->id, 'content' => 'إجابتي الأولى']);
        $this->from('/learn/student')->post($url, ['content' => 'إجابتي بعد المراجعة'])->assertRedirect('/learn/student')->assertSessionHasErrors();
        $this->get('/learn/student')->assertOk()->assertInertia(fn (Assert $page): Assert => $page->where('allAssignments.0.canSubmit', false));
        $this->assertSame(1, DB::table('assignment_submissions')->where('assignment_id', $assignment->id)->where('student_profile_id', $profile->id)->count());
        $foreign = $this->account('student');
        $this->flushSession();
        $this->actingAs($foreign)->post($url, ['content' => 'محتوى من حساب آخر'])->assertForbidden();
        $this->assertDatabaseMissing('assignment_submissions', ['content' => 'محتوى من حساب آخر']);
    }

    public function test_teacher_week_contains_past_and_upcoming_own_lessons_and_skips_cancelled_next_lesson(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-10T12:00:00Z'));
        $participant = $this->createSessionParticipant();
        $originalId = DB::table('session_participants')->where('id', $participant)->value('session_id');
        $original = Session::query()->findOrFail($originalId);
        $teacherId = DB::table('staff_profiles')->where('id', $original->staff_profile_id)->value('user_id');
        $teacher = User::query()->findOrFail($teacherId);
        $roleId = DB::table('roles')->whereNull('organization_id')->where('guard_name', 'web')->where('name', 'teacher')->value('id');
        DB::table('model_has_roles')->insert(['role_id' => $roleId, 'model_type' => User::class, 'model_id' => $teacher->id]);
        $past = $original->replicate(['time_range']);
        $past->forceFill(['id' => (string) Str::ulid(), 'scheduled_start' => '2026-09-08T10:00:00Z', 'scheduled_end' => '2026-09-08T11:00:00Z'])->save();
        $future = $original->replicate(['time_range']);
        $future->forceFill(['id' => (string) Str::ulid(), 'scheduled_start' => '2026-09-11T10:00:00Z', 'scheduled_end' => '2026-09-11T11:00:00Z',
            'status' => SessionStatus::Scheduled])->save();
        $cancelled = $original->replicate(['time_range']);
        $cancelled->forceFill(['id' => (string) Str::ulid(), 'scheduled_start' => '2026-09-10T14:00:00Z', 'scheduled_end' => '2026-09-10T15:00:00Z',
            'status' => SessionStatus::CancelledBySchool])->save();
        $nextWeek = $original->replicate(['time_range']);
        $nextWeek->forceFill(['id' => (string) Str::ulid(), 'scheduled_start' => '2026-09-17T10:00:00Z', 'scheduled_end' => '2026-09-17T11:00:00Z'])->save();
        $this->actingAs($teacher)->get('/learn/teacher')->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->has('sessions', 4)->where('nextSession.id', $future->id)->where('sessions.0.id', $past->id));
        $other = $this->account('teacher');
        $this->flushSession();
        $this->actingAs($other)->get('/learn/teacher')->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->has('sessions', 0)->where('nextSession', null));
    }

    private function account(string $role): User
    {
        $org = (string) Str::ulid();
        DB::table('organizations')->insert(['id' => $org, 'name' => json_encode(['ar' => 'أكاديمية الاختبار']),
            'slug' => strtolower($org), 'created_at' => now(), 'updated_at' => now()]);
        $user = User::factory()->inOrganization($org)->create(['timezone' => 'Africa/Cairo', 'locale' => 'fr']);
        if ($role === 'student') {
            StudentProfile::factory()->create(['organization_id' => $org, 'user_id' => $user->id]);
        }
        if ($role === 'teacher') {
            DB::table('staff_profiles')->insert(['id' => (string) Str::ulid(), 'organization_id' => $org,
                'user_id' => $user->id, 'staff_code' => 'QA-'.Str::random(8), 'employment_type' => 'part_time',
                'created_at' => now(), 'updated_at' => now()]);
        }
        $roleId = DB::table('roles')->whereNull('organization_id')->where('guard_name', 'web')->where('name', $role)->value('id');
        DB::table('model_has_roles')->insert(['role_id' => $roleId, 'model_type' => User::class, 'model_id' => $user->id]);

        return $user;
    }
}
