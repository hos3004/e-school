<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Academics\Domain\Models\Course;
use Modules\AccessControl\Database\Seeders\AccessControlSeeder;
use Modules\AccessControl\Domain\Models\ModelHasPermission;
use Modules\AccessControl\Domain\Models\Permission;
use Modules\AccessControl\Infrastructure\Authorization\PermissionGateRegistrar;
use Modules\Identity\Domain\Enums\UserStatus;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Database\Seeders\GeographySeeder;
use Modules\Organization\Domain\Contracts\GeographyQueries;
use Modules\Organization\Domain\Models\Organization;
use Modules\Students\Domain\Enums\RegistrationStatus;
use Modules\Students\Domain\Events\RegistrationAccepted;
use Modules\Students\Domain\Models\RegistrationApplication;
use Modules\Students\Domain\Models\StudentProfile;
use Tests\TestCase;

final class ConsoleRegistrationExistingStudentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['console.enabled' => true]);
        $this->seed([GeographySeeder::class, AccessControlSeeder::class]);
        app(PermissionGateRegistrar::class)->register();
        Event::fake([RegistrationAccepted::class]);
    }

    public function test_accepting_another_course_reuses_the_existing_profile_and_preserves_all_old_data_once(): void
    {
        [$org, $student, $user, $actor, $application] = $this->context();
        $old = $this->application($org, $user, ['status' => RegistrationStatus::Assigned, 'student_profile_id' => $student->id]);
        $application->update(['duplicate_of_application_id' => $old->id]);
        $profileBefore = $student->fresh()->getRawOriginal();
        $accountBefore = $user->fresh()->getRawOriginal();
        $oldBefore = $old->fresh()->getRawOriginal();
        $answersBefore = $application->fresh()->evaluation_answers;
        $userCount = User::query()->count();
        $profiles = StudentProfile::query()->count();
        $url = $this->url($application);
        $this->actingAs($actor)->post($url, $this->decision($user))->assertSessionHasErrors('identity_confirmed');
        $this->assertNull($application->fresh()->user_id);
        $decision = [...$this->decision($user), 'identity_confirmed' => true];
        $this->post($url, $decision)->assertSessionHasNoErrors()->assertRedirect('/manage/registration/applications/'.$application->id);
        $accepted = $application->fresh();
        $this->assertSame(RegistrationStatus::WaitingAssignment, $accepted->status);
        $this->assertSame($student->id, $accepted->student_profile_id);
        $this->assertSame($user->id, $accepted->user_id);
        $this->assertSame($profileBefore, $student->fresh()->getRawOriginal());
        $this->assertSame($accountBefore, $user->fresh()->getRawOriginal());
        $this->assertSame($oldBefore, $old->fresh()->getRawOriginal());
        $this->assertSame($answersBefore, $accepted->evaluation_answers);
        $this->assertSame($application->preferred_course_id, $accepted->preferred_course_id);
        $this->assertSame($userCount, User::query()->count());
        $this->assertSame($profiles, StudentProfile::query()->count());
        $this->assertDatabaseCount('enrollments', 0);
        $this->assertDatabaseCount('group_memberships', 0);
        $this->assertDatabaseHas('audit_log', ['action' => 'academic_status.registration_accepted', 'auditable_id' => $application->id, 'actor_id' => $actor->id]);
        $this->post($url, $decision)->assertSessionHasNoErrors();
        Event::assertDispatchedTimes(RegistrationAccepted::class, 1);
        $this->assertSame(1, DB::table('audit_log')->where('action', 'academic_status.registration_accepted')->where('auditable_id', $application->id)->count());
    }

    public function test_existing_account_must_match_contact_and_organization_with_real_permissions(): void
    {
        [$org, $student, $user, $actor, $application] = $this->context();
        $wrong = User::factory()->inOrganization($org)->create();
        $foreignOrg = (string) Organization::factory()->create()->id;
        $foreign = User::factory()->inOrganization($foreignOrg)->create();
        $before = $student->fresh()->getRawOriginal();
        $this->actingAs($actor)->post($this->url($application), $this->decision($wrong))->assertSessionHasErrors('form');
        $this->post($this->url($application), $this->decision($foreign))->assertNotFound();
        $foreignApplication = $this->application($foreignOrg, $foreign);
        $this->post($this->url($foreignApplication), $this->decision($user))->assertNotFound();
        $this->actingAs($this->actor($org, false))->post($this->url($application), $this->decision($user))->assertForbidden();
        $this->assertSame(RegistrationStatus::Submitted, $application->fresh()->status);
        $this->assertNull($application->fresh()->user_id);
        $this->assertSame($before, $student->fresh()->getRawOriginal());
        Event::assertNotDispatched(RegistrationAccepted::class);
    }

    public function test_suspended_frozen_or_archived_existing_student_is_never_reactivated_by_acceptance(): void
    {
        [$org, $student, $user, $actor, $application] = $this->context();
        $this->actingAs($actor);
        foreach ([UserStatus::Suspended, UserStatus::Frozen] as $status) {
            $user->update(['status' => $status]);
            $before = $student->fresh()->getRawOriginal();
            $this->post($this->url($application), $this->decision($user))->assertSessionHasErrors('existing_user_id');
            $this->assertSame($status, $user->fresh()->status);
            $this->assertSame($before, $student->fresh()->getRawOriginal());
            $this->assertNull($application->fresh()->user_id);
        }
        $user->update(['status' => UserStatus::Active]);
        $student->delete();
        $archived = StudentProfile::withTrashed()->findOrFail($student->id)->getRawOriginal();
        $this->post($this->url($application), $this->decision($user))->assertSessionHasErrors('existing_user_id');
        $this->assertSame($archived, StudentProfile::withTrashed()->findOrFail($student->id)->getRawOriginal());
        $this->assertSame(RegistrationStatus::Submitted, $application->fresh()->status);
        $this->assertNull($application->fresh()->student_profile_id);
        $this->assertDatabaseMissing('audit_log', ['action' => 'academic_status.registration_accepted', 'auditable_id' => $application->id]);
        Event::assertNotDispatched(RegistrationAccepted::class);
    }

    /** @return array{string, StudentProfile, User, User, RegistrationApplication} */
    private function context(): array
    {
        $org = (string) Organization::factory()->create()->id;
        $user = User::factory()->inOrganization($org)->create(['name' => 'الاسم الأصلي', 'locale' => 'fr', 'timezone' => 'Europe/Paris']);
        $student = StudentProfile::factory()->create(['organization_id' => $org, 'user_id' => $user->id,
            'date_of_birth' => '2005-06-01', 'notes' => 'ملاحظات الملف الأصلي', 'joined_at' => '2024-01-01']);

        return [$org, $student, $user, $this->actor($org), $this->application($org, $user)];
    }

    private function actor(string $org, bool $canAccept = true): User
    {
        $actor = User::factory()->inOrganization($org)->create();
        foreach ($canAccept ? ['admin.panel.access', 'student.create'] : ['admin.panel.access'] as $name) {
            $permission = Permission::query()->where('name', $name)->sole();
            ModelHasPermission::query()->create(['permission_id' => $permission->id, 'model_type' => $actor->getMorphClass(), 'model_id' => $actor->id]);
        }

        return $actor;
    }

    /** @param array<string, mixed> $extra */
    private function application(string $org, User $user, array $extra = []): RegistrationApplication
    {
        $country = app(GeographyQueries::class)->findCountryByIso2('EG');
        $region = app(GeographyQueries::class)->regionsOf($country->id)[0];
        $course = Course::factory()->create(['organization_id' => $org]);

        return RegistrationApplication::query()->create(['organization_id' => $org, 'full_name' => 'اسم مختلف بالطلب',
            'date_of_birth' => '2010-01-01', 'gender' => 'male', 'country_id' => $country->id, 'region_id' => $region->id,
            'email' => $user->email, 'status' => RegistrationStatus::Submitted, 'submitted_at' => now(),
            'preferred_course_id' => $course->id, 'preferred_program_id' => $course->level->program_id,
            'notes' => 'ملاحظات الطلب الجديد', 'evaluation_answers' => [['question_id' => 'time', 'question' => 'التوقيت', 'answer' => 'المساء']], ...$extra]);
    }

    /** @return array<string, mixed> */
    private function decision(User $user): array
    {
        return ['decision' => 'accept', 'account_mode' => 'existing', 'existing_user_id' => $user->id, 'timezone' => 'Africa/Cairo'];
    }

    private function url(RegistrationApplication $application): string
    {
        return '/manage/registration/applications/'.$application->id.'/decision';
    }
}
