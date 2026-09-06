<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Academics\Domain\Enums\SessionMode;
use Modules\Academics\Domain\Models\Course;
use Modules\Academics\Domain\Models\Level;
use Modules\Academics\Domain\Models\Program;
use Modules\AccessControl\Database\Seeders\AccessControlSeeder;
use Modules\AccessControl\Domain\Enums\GuardName;
use Modules\AccessControl\Domain\Models\ModelHasRole;
use Modules\AccessControl\Domain\Models\Permission;
use Modules\AccessControl\Domain\Models\Role;
use Modules\AccessControl\Domain\Models\RoleHasPermission;
use Modules\AccessControl\Infrastructure\Authorization\PermissionGateRegistrar;
use Modules\Identity\Domain\Events\UserRegistered;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Database\Seeders\GeographySeeder;
use Modules\Organization\Domain\Contracts\GeographyQueries;
use Modules\Organization\Domain\Models\Organization;
use Modules\Staff\Domain\Events\StaffProfileCreated;
use Modules\Staff\Domain\Events\TeacherContractCreated;
use Modules\Staff\Domain\Events\TeacherRateCreated;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Staff\Domain\Models\TeacherContract;
use Modules\Staff\Domain\Models\TeacherRate;
use Modules\Students\Domain\Enums\RegistrationStatus;
use Modules\Students\Domain\Events\RegistrationAccepted;
use Modules\Students\Domain\Events\RegistrationSubmitted;
use Modules\Students\Domain\Models\RegistrationApplication;
use Modules\Students\Domain\Models\StudentProfile;
use Tests\TestCase;

final class ConsolePeopleTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $permissions = [
        'admin.panel.access', 'student.view.any', 'student.create', 'student.update',
        'staff.view.any', 'staff.contract.view', 'staff.contract.update', 'identity.users.update',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        config(['console.enabled' => true]);
        $this->withoutVite();
        Http::preventStrayRequests();
        Http::fake(['api.pwnedpasswords.com/*' => Http::response('', 200)]);
        $this->seed([GeographySeeder::class, AccessControlSeeder::class]);
        foreach ($this->permissions as $ability) {
            Gate::define($ability, fn (): bool => in_array($ability, $this->permissions, true));
        }
        Event::fake([
            UserRegistered::class, RegistrationSubmitted::class, RegistrationAccepted::class,
            StaffProfileCreated::class, TeacherContractCreated::class, TeacherRateCreated::class,
        ]);
    }

    public function test_directory_and_profile_never_expose_another_organization(): void
    {
        [$organization, $actor] = $this->context();
        $ownUser = User::factory()->inOrganization((string) $organization->id)->create(['name' => 'Own student']);
        $student = StudentProfile::factory()->create(['organization_id' => $organization->id, 'user_id' => $ownUser->id]);
        $foreignOrganization = Organization::factory()->create();
        $foreignUser = User::factory()->inOrganization((string) $foreignOrganization->id)->create(['name' => 'Foreign student']);
        $foreignStudent = StudentProfile::factory()->create(['organization_id' => $foreignOrganization->id, 'user_id' => $foreignUser->id]);
        $this->actingAs($actor)->get('/manage/students')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Console/People/Index')->has('people.data', 1)->where('people.data.0.id', (string) $student->id));
        $this->get('/manage/students/'.$foreignStudent->id)->assertNotFound();
        $this->get('/manage/students/'.$foreignStudent->id.'/edit')->assertNotFound();
    }

    public function test_read_access_does_not_allow_creation_or_account_suggestions(): void
    {
        [, $actor] = $this->context();
        $this->permissions = ['admin.panel.access', 'student.view.any'];
        $this->actingAs($actor)->get('/manage/students')->assertOk();
        $this->get('/manage/students/create')->assertForbidden();
        $this->get('/manage/students/username-suggestions?name=Test')->assertForbidden();
        $this->post('/manage/students', [])->assertForbidden();
    }

    public function test_student_creation_accepts_application_without_a_typed_reason_and_ignores_client_organization(): void
    {
        [$organization, $actor, $program, $course, $country, $region] = $this->context();
        $foreign = Organization::factory()->create();
        $data = $this->studentData((string) $program->id, (string) $course->id, $country, $region);
        $response = $this->actingAs($actor)->post('/manage/students', [...$data, 'organization_id' => $foreign->id]);
        $response->assertSessionHasNoErrors()->assertRedirect();
        $user = User::query()->where('username', $data['username'])->firstOrFail();
        $student = StudentProfile::query()->where('user_id', $user->id)->firstOrFail();
        $application = RegistrationApplication::query()->where('student_profile_id', $student->id)->firstOrFail();
        self::assertSame((string) $organization->id, (string) $user->organization_id);
        self::assertSame(RegistrationStatus::WaitingAssignment, $application->status);
        self::assertSame(__('console_people.audit.student_created'), $application->decision_reason);
        $response->assertRedirect(route('console.students.show', ['profile' => $student->id]));
    }

    public function test_taken_username_does_not_create_partial_student_records(): void
    {
        [$organization, $actor, $program, $course, $country, $region] = $this->context();
        User::factory()->inOrganization((string) $organization->id)->create(['username' => 'console.student']);
        $studentsBefore = StudentProfile::query()->count();
        $usersBefore = User::query()->count();
        $this->actingAs($actor)->post('/manage/students', $this->studentData((string) $program->id, (string) $course->id, $country, $region))
            ->assertSessionHasErrors('username');
        self::assertSame($usersBefore, User::query()->count());
        self::assertSame($studentsBefore, StudentProfile::query()->count());
        self::assertSame(0, RegistrationApplication::query()->count());
    }

    public function test_username_suggestions_are_server_checked_and_return_no_account_data(): void
    {
        [$organization, $actor] = $this->context();
        $this->actingAs($actor);
        $first = $this->getJson('/manage/students/username-suggestions?name=Ali%20Hassan')->assertOk()->json('suggestions.0');
        User::factory()->inOrganization((string) $organization->id)->create(['username' => $first]);
        $next = $this->getJson('/manage/students/username-suggestions?name=Ali%20Hassan')->assertOk();
        self::assertNotContains($first, $next->json('suggestions'));
        self::assertSame(['suggestions'], array_keys($next->json()));
    }

    public function test_student_edit_preserves_credentials_and_study_and_records_actual_changes(): void
    {
        [$organization, $actor, , , $country, $region] = $this->context();
        $user = User::factory()->inOrganization((string) $organization->id)->create(['username' => 'unchanged.name', 'email' => 'unchanged@example.test']);
        $student = StudentProfile::factory()->create(['organization_id' => $organization->id, 'user_id' => $user->id, 'country_id' => $country, 'region_id' => $region]);
        $password = $user->password;
        $this->actingAs($actor)->put('/manage/students/'.$student->id, [
            'full_name' => 'Updated Name', 'phone' => $user->phone, 'timezone' => 'Europe/London',
            'country_id' => $country, 'region_id' => $region, 'city' => 'Cairo',
            'username' => 'attack.change', 'email' => 'attack@example.test', 'password' => 'different',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $user->refresh();
        self::assertSame('Updated Name', $user->name);
        self::assertSame('unchanged.name', $user->username);
        self::assertSame('unchanged@example.test', $user->email);
        self::assertSame($password, $user->password);
        self::assertSame('Europe/London', $user->timezone);
        self::assertSame('Cairo', $student->fresh()->city);
        self::assertTrue(DB::table('audit_log')->where('auditable_id', $student->id)->where('reason', __('console_people.audit.profile_updated'))->exists());
    }

    public function test_clearing_an_existing_phone_is_not_reported_as_a_successful_profile_update(): void
    {
        [$organization, $actor, , , $country, $region] = $this->context();
        $user = User::factory()->inOrganization((string) $organization->id)->create(['name' => 'Original Name', 'phone' => '+201001234567']);
        $student = StudentProfile::factory()->create(['organization_id' => $organization->id, 'user_id' => $user->id, 'country_id' => $country, 'region_id' => $region]);
        $this->actingAs($actor)->put('/manage/students/'.$student->id, [
            'full_name' => 'Rejected Name', 'phone' => null, 'timezone' => 'Europe/London',
            'country_id' => $country, 'region_id' => $region, 'city' => 'Uncommitted City',
        ])->assertSessionHasErrors('phone');
        self::assertSame('Original Name', $user->fresh()->name);
        self::assertSame('+201001234567', $user->fresh()->phone);
        self::assertNotSame('Uncommitted City', $student->fresh()->city);
    }

    public function test_real_profile_editor_role_cannot_change_account_identity_but_can_edit_profiles(): void
    {
        [$organization, $actor, , , $country, $region] = $this->context();
        $role = Role::query()->create([
            'organization_id' => $organization->id, 'name' => 'console-profile-editor',
            'guard_name' => GuardName::Web, 'is_system' => false,
        ]);
        foreach (['admin.panel.access', 'student.view.any', 'student.update', 'staff.view.any', 'staff.contract.update'] as $name) {
            $permission = Permission::query()->where('name', $name)->firstOrFail();
            RoleHasPermission::query()->create(['role_id' => $role->id, 'permission_id' => $permission->id]);
        }
        ModelHasRole::query()->create([
            'role_id' => $role->id, 'model_type' => $actor->getMorphClass(), 'model_id' => $actor->id,
        ]);
        app(PermissionGateRegistrar::class)->register();
        self::assertTrue($actor->can('student.update'));
        self::assertFalse($actor->can('identity.users.update'));
        $this->actingAs($actor);
        foreach (['students', 'teachers'] as $kind) {
            $user = User::factory()->inOrganization((string) $organization->id)->create([
                'name' => 'Protected Account', 'phone' => '+201001234567', 'timezone' => 'Africa/Cairo',
            ]);
            $record = $kind === 'students'
                ? StudentProfile::factory()->create(['organization_id' => $organization->id, 'user_id' => $user->id, 'country_id' => $country, 'region_id' => $region])
                : StaffProfile::query()->create(['organization_id' => $organization->id, 'user_id' => $user->id, 'staff_code' => 'T'.$user->id, 'employment_type' => 'contractor', 'country_id' => $country, 'region_id' => $region, 'phone' => '+201001234567']);
            $url = '/manage/'.$kind.'/'.$record->id;
            $this->get($url.'/edit')->assertOk()->assertInertia(fn (Assert $page) => $page->where('canUpdateAccount', false));
            $profileChanges = ['country_id' => $country, 'region_id' => $region, 'city' => 'Academic update', 'staff_code' => $record->getAttribute('staff_code'), 'employment_type' => 'contractor', 'bio' => 'Academic update'];
            foreach (['full_name' => 'Unauthorized name', 'phone' => '+201009999999', 'timezone' => 'Asia/Tokyo'] as $field => $value) {
                $this->put($url, [...$profileChanges, $field => $value])->assertForbidden();
                self::assertSame('Protected Account', $user->fresh()->name);
                self::assertSame('+201001234567', $user->fresh()->phone);
                self::assertSame('Africa/Cairo', $user->fresh()->timezone);
                self::assertNotSame('Academic update', $record->fresh()->getAttribute($kind === 'students' ? 'city' : 'bio'));
            }
            $this->put($url, $profileChanges)->assertSessionHasNoErrors()->assertRedirect();
            self::assertSame('Academic update', $kind === 'students' ? $record->fresh()->city : $record->fresh()->bio['ar']);
            self::assertSame('+201001234567', $user->fresh()->phone);
            if ($record instanceof StaffProfile) {
                self::assertSame('+201001234567', $record->fresh()->phone);
            }
        }
    }

    public function test_teacher_creation_saves_contract_rate_and_profile_atomically(): void
    {
        [$organization, $actor, , $course, $country, $region] = $this->context();
        $this->actingAs($actor)->post('/manage/teachers', [
            'account_mode' => 'new', 'full_name' => 'Console Teacher', 'email' => 'console.teacher@example.test',
            'username' => 'console.teacher', 'password' => 'G8!Teacher-Console#2026',
            'password_confirmation' => 'G8!Teacher-Console#2026', 'locale' => 'ar', 'timezone' => 'Africa/Cairo',
            'gender' => 'female', 'country_id' => $country, 'region_id' => $region,
            'staff_code' => 'T991', 'employment_type' => 'contractor', 'hired_at' => '2026-09-01',
            'contract_basis' => 'per_session', 'contract_effective_from' => '2026-09-01',
            'currency' => 'EGP', 'default_rate_major' => '150.50', 'course_ids' => [$course->id],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $user = User::query()->where('username', 'console.teacher')->firstOrFail();
        $teacher = StaffProfile::query()->where('user_id', $user->id)->firstOrFail();
        self::assertSame((string) $organization->id, (string) $teacher->organization_id);
        $contract = TeacherContract::query()->where('staff_profile_id', $teacher->id)->firstOrFail();
        self::assertSame(15050, TeacherRate::query()->where('teacher_contract_id', $contract->id)->firstOrFail()->amount);
        $this->get('/manage/teachers/'.$teacher->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Console/People/Show')->has('hub.contracts', 1)->has('hub.rates', 1)->missing('person.password'));
        $this->permissions = ['admin.panel.access', 'staff.view.any'];
        $this->get('/manage/teachers/'.$teacher->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->missing('hub.contracts')->missing('hub.rates'));
    }

    public function test_student_form_uses_school_timezone_and_profiles_keep_directory_context(): void
    {
        [$organization, $actor, , , $country, $region] = $this->context();
        $organization->update(['default_timezone' => 'Africa/Cairo']);
        $actor->update(['timezone' => 'Europe/London']);
        $this->actingAs($actor)->get('/manage/students/create')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Console/People/Form')->where('person.timezone', 'Africa/Cairo'));
        $user = User::factory()->inOrganization((string) $organization->id)->create();
        $profile = StudentProfile::factory()->create(['organization_id' => $organization->id, 'user_id' => $user->id, 'country_id' => $country, 'region_id' => $region]);
        $this->get('/manage/students/'.$profile->id.'?search=Name&archived=0&page=2')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('backUrl', route('console.students.index', ['search' => 'Name', 'archived' => '0', 'page' => '2'])));
    }

    /** @return array{Organization, User, Program, Course, string, string} */
    private function context(): array
    {
        $organization = Organization::factory()->create();
        $actor = User::factory()->inOrganization((string) $organization->id)->create();
        $geography = app(GeographyQueries::class);
        $country = $geography->findCountryByIso2('EG');
        $region = $geography->regionsOf((string) $country?->id)[0];
        $program = Program::factory()->create(['organization_id' => $organization->id, 'is_active' => true]);
        $level = Level::factory()->create(['program_id' => $program->id]);
        $course = Course::factory()->create(['organization_id' => $organization->id, 'level_id' => $level->id, 'is_active' => true, 'session_mode' => SessionMode::Group]);

        return [$organization, $actor, $program, $course, (string) $country?->id, $region->id];
    }

    /** @return array<string, mixed> */
    private function studentData(string $program, string $course, string $country, string $region): array
    {
        return [
            'account_mode' => 'new', 'full_name' => 'Console Student', 'email' => 'console.student@example.test',
            'username' => 'console.student', 'password' => 'G8!Student-Console#2026',
            'password_confirmation' => 'G8!Student-Console#2026', 'locale' => 'ar', 'timezone' => 'Africa/Cairo',
            'date_of_birth' => '2011-05-15', 'gender' => 'male', 'country_id' => $country, 'region_id' => $region,
            'preferred_program_id' => $program, 'preferred_course_id' => $course,
        ];
    }
}
