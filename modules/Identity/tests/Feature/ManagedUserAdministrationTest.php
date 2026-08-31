<?php

declare(strict_types=1);

namespace Modules\Identity\Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\AccessControl\Database\Seeders\AccessControlSeeder;
use Modules\Identity\Application\Actions\CreateManagedUserAction;
use Modules\Identity\Domain\Contracts\UserQueryService;
use Modules\Identity\Domain\Models\User;
use Modules\Identity\Domain\Models\UserDevice;
use Modules\Identity\Presentation\Filament\Resources\Users\UserResource;
use Modules\Organization\Domain\Models\Organization;
use Tests\TestCase;

final class ManagedUserAdministrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccessControlSeeder::class);
    }

    public function test_managed_account_is_created_with_role_and_audit_in_one_flow(): void
    {
        [$organization, $actor] = $this->context();
        $reason = 'إنشاء حساب مشرف أكاديمي بعد اعتماد الصلاحيات والمسؤوليات';

        $user = app(CreateManagedUserAction::class)->execute(
            $this->data($reason),
            (string) $organization->id,
            (string) $actor->id,
        );

        $roleId = DB::table('roles')->whereNull('organization_id')->where('name', 'academic_supervisor')->value('id');

        self::assertSame('Academic Operator', $user->name);
        self::assertTrue(DB::table('model_has_roles')->where([
            'role_id' => $roleId,
            'model_type' => app(UserQueryService::class)->modelType(),
            'model_id' => (string) $user->id,
        ])->exists());
        self::assertTrue(DB::table('audit_log')->where([
            'action' => 'identity.managed_user_created',
            'auditable_id' => (string) $user->id,
            'reason' => $reason,
        ])->exists());
    }

    public function test_people_roles_with_domain_profiles_are_rejected_from_generic_account_creation(): void
    {
        [$organization, $actor] = $this->context();
        $data = $this->data('محاولة إنشاء معلم من شاشة الحساب العامة');
        $data['role_name'] = 'teacher';

        try {
            app(CreateManagedUserAction::class)->execute(
                $data,
                (string) $organization->id,
                (string) $actor->id,
            );

            self::fail('Teacher accounts must use the teacher onboarding flow.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('role_name', $exception->errors());
        }

        self::assertFalse(User::query()->where('username', 'academic.operator')->exists());
    }

    public function test_create_and_account_hub_pages_render_roles_devices_and_status_operations(): void
    {
        [$organization, $actor] = $this->context();
        Gate::before(static fn (): bool => true);
        Filament::setCurrentPanel('admin');
        $target = app(CreateManagedUserAction::class)->execute(
            $this->data('إنشاء حساب للعرض التشغيلي'),
            (string) $organization->id,
            (string) $actor->id,
        );
        UserDevice::factory()->create([
            'user_id' => (string) $target->id,
            'device_name' => 'Office Browser',
            'platform' => 'web',
        ]);

        $this->actingAs($actor)
            ->get(UserResource::getUrl('create', panel: 'admin'))
            ->assertOk()
            ->assertSeeText(__('identity::admin.role'))
            ->assertSeeText(__('identity::admin.creation_reason_help'));

        $this->get(UserResource::getUrl('view', ['record' => $target], panel: 'admin'))
            ->assertOk()
            ->assertSeeText(__('identity::admin.roles_tab'))
            ->assertSeeText(__('identity::admin.roles.academic_supervisor'))
            ->assertSeeText('Office Browser')
            ->assertSeeText(__('identity::admin.change_status'));
    }

    /** @return array{Organization, User} */
    private function context(): array
    {
        $organization = Organization::factory()->create();
        $actor = User::factory()->inOrganization((string) $organization->id)->create();

        return [$organization, $actor];
    }

    /** @return array<string, mixed> */
    private function data(string $reason): array
    {
        return [
            'name' => 'Academic Operator',
            'email' => 'academic.operator@example.test',
            'username' => 'academic.operator',
            'phone' => null,
            'password' => 'A7!eSchool-Operator-2026#Qp9',
            'password_confirmation' => 'A7!eSchool-Operator-2026#Qp9',
            'locale' => 'ar',
            'timezone' => 'UTC',
            'role_name' => 'academic_supervisor',
            'reason' => $reason,
        ];
    }
}
