<?php

declare(strict_types=1);

namespace Modules\Guardians\Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Modules\AccessControl\Database\Seeders\AccessControlSeeder;
use Modules\Guardians\Application\Actions\CreateGuardianOnboardingAction;
use Modules\Guardians\Domain\Enums\ContactChannel;
use Modules\Guardians\Domain\Enums\GuardianAccountMode;
use Modules\Guardians\Domain\Enums\GuardianRelationship;
use Modules\Guardians\Domain\Events\GuardianLinkedToStudent;
use Modules\Guardians\Domain\Events\GuardianProfileCreated;
use Modules\Guardians\Domain\Models\GuardianLink;
use Modules\Guardians\Domain\Models\GuardianProfile;
use Modules\Guardians\Presentation\Filament\Resources\GuardianLinkFilamentResource;
use Modules\Guardians\Presentation\Filament\Resources\GuardianProfileFilamentResource;
use Modules\Identity\Domain\Contracts\UserQueryService;
use Modules\Identity\Domain\Events\UserRegistered;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Domain\Models\Organization;
use Modules\Students\Domain\Models\StudentProfile;
use Shared\Support\BusinessRuleViolation;
use Tests\TestCase;

final class GuardianOnboardingActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccessControlSeeder::class);
        Event::fake([
            UserRegistered::class,
            GuardianProfileCreated::class,
            GuardianLinkedToStudent::class,
        ]);
    }

    public function test_new_guardian_account_profile_initial_student_link_role_and_audit_are_created_atomically(): void
    {
        [$organization, $actor, $student] = $this->context();
        $reason = 'إثبات صلة القرابة ومراجعة بيانات التواصل قبل تفعيل حساب ولي الأمر';

        $profile = app(CreateGuardianOnboardingAction::class)->execute(
            $this->data(GuardianAccountMode::NewAccount, $reason, (string) $student->id),
            (string) $organization->id,
            (string) $actor->id,
        );

        $account = User::query()->findOrFail($profile->user_id);
        $link = GuardianLink::query()->where('guardian_profile_id', $profile->id)->firstOrFail();
        $guardianRoleId = DB::table('roles')->whereNull('organization_id')->where('name', 'guardian')->value('id');
        $modelType = app(UserQueryService::class)->modelType();

        self::assertSame('New Guardian', $account->name);
        self::assertSame('new.guardian', $account->username);
        self::assertSame((string) $student->id, $link->student_profile_id);
        self::assertSame(GuardianRelationship::Mother, $link->relationship);
        self::assertTrue($link->is_primary);
        self::assertTrue(DB::table('model_has_roles')->where([
            'role_id' => $guardianRoleId,
            'model_type' => $modelType,
            'model_id' => (string) $account->id,
        ])->exists());
        self::assertTrue(DB::table('audit_log')->where([
            'action' => 'guardians.student_linked',
            'auditable_id' => (string) $link->id,
            'reason' => $reason,
        ])->exists());
        self::assertTrue(DB::table('audit_log')->where([
            'action' => 'guardians.guardian_onboarded',
            'auditable_id' => (string) $profile->id,
            'reason' => $reason,
        ])->exists());
    }

    public function test_existing_account_can_be_linked_without_creating_a_second_user(): void
    {
        [$organization, $actor] = $this->context();
        $existing = User::factory()->inOrganization((string) $organization->id)->create([
            'name' => 'Existing Guardian',
            'email' => 'existing.guardian@example.test',
        ]);
        $usersBefore = User::query()->count();

        $profile = app(CreateGuardianOnboardingAction::class)->execute(
            $this->data(
                GuardianAccountMode::ExistingAccount,
                'ربط حساب ولي الأمر الموجود بعد مطابقة جهة الاتصال',
                existingUserId: (string) $existing->id,
            ),
            (string) $organization->id,
            (string) $actor->id,
        );

        self::assertSame((string) $existing->id, $profile->user_id);
        self::assertSame($usersBefore, User::query()->count());
        self::assertFalse(GuardianLink::query()->where('guardian_profile_id', $profile->id)->exists());
    }

    public function test_missing_guardian_role_rolls_back_account_profile_link_and_audit(): void
    {
        [$organization, $actor, $student] = $this->context();
        $reason = 'اختبار التراجع عند غياب دور ولي الأمر';
        $profilesBefore = GuardianProfile::query()->count();
        $linksBefore = GuardianLink::query()->count();
        DB::table('roles')->whereNull('organization_id')->where('name', 'guardian')->delete();

        try {
            app(CreateGuardianOnboardingAction::class)->execute(
                $this->data(GuardianAccountMode::NewAccount, $reason, (string) $student->id),
                (string) $organization->id,
                (string) $actor->id,
            );

            self::fail('The onboarding action must roll back when the guardian role is missing.');
        } catch (BusinessRuleViolation $violation) {
            self::assertSame('accesscontrol.role.not_found', $violation->rule);
        }

        self::assertFalse(User::query()->where('username', 'new.guardian')->exists());
        self::assertSame($profilesBefore, GuardianProfile::query()->count());
        self::assertSame($linksBefore, GuardianLink::query()->count());
        self::assertFalse(DB::table('audit_log')->where('reason', $reason)->exists());
    }

    public function test_student_from_another_organization_cannot_be_linked(): void
    {
        [$organization, $actor] = $this->context();
        $foreignOrganization = Organization::factory()->create();
        $foreignUser = User::factory()->inOrganization((string) $foreignOrganization->id)->create();
        $foreignStudent = StudentProfile::factory()->create([
            'organization_id' => (string) $foreignOrganization->id,
            'user_id' => (string) $foreignUser->id,
        ]);
        $profilesBefore = GuardianProfile::query()->count();

        try {
            app(CreateGuardianOnboardingAction::class)->execute(
                $this->data(
                    GuardianAccountMode::NewAccount,
                    'محاولة ربط طالب من مؤسسة أخرى',
                    (string) $foreignStudent->id,
                ),
                (string) $organization->id,
                (string) $actor->id,
            );

            self::fail('Cross-organization students must never be linkable.');
        } catch (BusinessRuleViolation $violation) {
            self::assertSame('guardians.student_not_in_organization', $violation->rule);
        }

        self::assertSame($profilesBefore, GuardianProfile::query()->count());
    }

    public function test_create_and_hub_pages_are_registered_and_render_for_an_authorized_operator(): void
    {
        [$organization, $actor, $student] = $this->context();
        Gate::before(static fn (): bool => true);
        Filament::setCurrentPanel('admin');
        $guardianAccount = User::factory()->inOrganization((string) $organization->id)->create();
        $guardian = GuardianProfile::factory()->create([
            'organization_id' => (string) $organization->id,
            'user_id' => (string) $guardianAccount->id,
        ]);
        GuardianLink::factory()->create([
            'guardian_profile_id' => (string) $guardian->id,
            'student_profile_id' => (string) $student->id,
        ]);

        $this->actingAs($actor)
            ->get(GuardianProfileFilamentResource::getUrl('create', panel: 'admin'))
            ->assertOk()
            ->assertSeeText(__('guardians::admin.onboarding.new_account'))
            ->assertSeeText(__('guardians::admin.onboarding.steps.student'));

        $this->get(GuardianProfileFilamentResource::getUrl('view', ['record' => $guardian], panel: 'admin'))
            ->assertOk()
            ->assertSeeText(__('guardians::admin.hub.students'))
            ->assertSeeText((string) $student->student_code);

        $this->get(GuardianLinkFilamentResource::getUrl('index', panel: 'admin'))
            ->assertOk()
            ->assertSeeText((string) $student->student_code)
            ->assertSeeText(__('guardians::admin.actions.verify'));
    }

    /** @return array{Organization, User, StudentProfile} */
    private function context(): array
    {
        $organization = Organization::factory()->create();
        $actor = User::factory()->inOrganization((string) $organization->id)->create();
        $studentAccount = User::factory()->inOrganization((string) $organization->id)->create([
            'name' => 'Linked Student',
        ]);
        $student = StudentProfile::factory()->create([
            'organization_id' => (string) $organization->id,
            'user_id' => (string) $studentAccount->id,
            'student_code' => 'ST-GUARDIAN-001',
        ]);

        return [$organization, $actor, $student];
    }

    /** @return array<string, mixed> */
    private function data(
        GuardianAccountMode $mode,
        string $reason,
        ?string $studentProfileId = null,
        ?string $existingUserId = null,
    ): array {
        return [
            'account_mode' => $mode->value,
            'existing_user_id' => $existingUserId,
            'full_name' => $mode === GuardianAccountMode::NewAccount ? 'New Guardian' : null,
            'email' => $mode === GuardianAccountMode::NewAccount ? 'new.guardian@example.test' : null,
            'phone' => null,
            'username' => $mode === GuardianAccountMode::NewAccount ? 'new.guardian' : null,
            'password' => $mode === GuardianAccountMode::NewAccount ? 'G7!eSchool-Guardian-2026#Qp9' : null,
            'password_confirmation' => $mode === GuardianAccountMode::NewAccount ? 'G7!eSchool-Guardian-2026#Qp9' : null,
            'locale' => 'ar',
            'timezone' => 'UTC',
            'national_id_last4' => '4821',
            'occupation' => 'engineer',
            'preferred_contact_channel' => ContactChannel::WhatsApp->value,
            'student_profile_id' => $studentProfileId,
            'relationship' => $studentProfileId === null ? null : GuardianRelationship::Mother->value,
            'is_primary' => true,
            'can_act_for' => true,
            'visible_sections' => ['attendance', 'schedule', 'grades'],
            'onboarding_reason' => $reason,
        ];
    }
}
