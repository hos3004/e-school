<?php

declare(strict_types=1);

namespace Modules\Identity\Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Modules\AccessControl\Database\Seeders\AccessControlSeeder;
use Modules\AccessControl\Domain\Models\ModelHasPermission;
use Modules\AccessControl\Domain\Models\Permission;
use Modules\AccessControl\Infrastructure\Authorization\PermissionGateRegistrar;
use Modules\Identity\Domain\Models\User;
use Modules\Identity\Presentation\Filament\Resources\Users\Pages\ViewUser;
use Modules\Identity\Presentation\Filament\Resources\Users\UserResource;
use Modules\Organization\Domain\Models\Organization;
use Tests\TestCase;

/**
 * مفاتيح الصلاحيات الاختيارية في شاشة المستخدم — تُقاس كما يستخدمها المسؤول.
 *
 * الصلاحيات الثلاث خارج حزمة دور المشرف عمدًا، فإن لم يكن ثمة زرٌّ يمنحها
 * تصبح الميزة معطّلة عمليًا مهما كان منطق الخادم سليمًا.
 */
final class OptionalAccessPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_toggling_optional_access_grants_revokes_and_audits_with_a_reason(): void
    {
        [$organizationId, $actor] = $this->actor();
        $target = $this->supervisorAccount($organizationId);
        $targetId = (string) $target->getAuthIdentifier();

        Livewire::test(ViewUser::class, ['record' => $targetId])
            ->callAction('optional_access', [
                'grant_payroll_view' => true,
                'grant_contact_pii_view' => true,
                'grant_recording_view' => false,
                'reason' => 'مشرف متابعة الجودة يراجع المستحقات ويتواصل مع أولياء الأمور',
            ])
            ->assertHasNoActionErrors();

        self::assertTrue($this->hasDirect($target, 'payroll.view'));
        self::assertTrue($this->hasDirect($target, 'contact.pii.view'));
        self::assertFalse($this->hasDirect($target, 'recording.view'));

        $granted = DB::table('audit_log')
            ->where('action', 'accesscontrol.permission_granted_directly')
            ->where('auditable_id', $targetId)
            ->get();

        self::assertCount(2, $granted);
        foreach ($granted as $row) {
            self::assertSame(
                'مشرف متابعة الجودة يراجع المستحقات ويتواصل مع أولياء الأمور',
                $row->reason,
            );
            self::assertSame((string) $actor->getAuthIdentifier(), (string) $row->actor_id);
        }

        // إطفاء المفتاح يسحب الصلاحية ويترك أثرًا مستقلًا في التدقيق.
        Livewire::test(ViewUser::class, ['record' => $targetId])
            ->callAction('optional_access', [
                'grant_payroll_view' => false,
                'grant_contact_pii_view' => true,
                'grant_recording_view' => false,
                'reason' => 'انتهى تكليف مراجعة المستحقات',
            ])
            ->assertHasNoActionErrors();

        self::assertFalse($this->hasDirect($target, 'payroll.view'));
        self::assertTrue($this->hasDirect($target, 'contact.pii.view'));

        self::assertSame(1, DB::table('audit_log')
            ->where('action', 'accesscontrol.permission_revoked_directly')
            ->where('auditable_id', $targetId)
            ->count());
    }

    public function test_an_unchanged_submission_records_nothing(): void
    {
        [$organizationId] = $this->actor();
        $target = $this->supervisorAccount($organizationId);

        Livewire::test(ViewUser::class, ['record' => (string) $target->getAuthIdentifier()])
            ->callAction('optional_access', [
                'grant_payroll_view' => false,
                'grant_contact_pii_view' => false,
                'grant_recording_view' => false,
                'reason' => 'مراجعة دورية بلا تغيير',
            ])
            ->assertHasNoActionErrors();

        self::assertSame(0, DB::table('audit_log')
            ->whereIn('action', [
                'accesscontrol.permission_granted_directly',
                'accesscontrol.permission_revoked_directly',
            ])->count());
    }

    public function test_the_switches_are_hidden_from_an_actor_without_the_direct_grant_permission(): void
    {
        [$organizationId] = $this->actor(withGrantDirect: false);
        $target = $this->supervisorAccount($organizationId);

        Livewire::test(ViewUser::class, ['record' => (string) $target->getAuthIdentifier()])
            ->assertActionHidden('optional_access');
    }

    public function test_an_actor_cannot_change_their_own_optional_access(): void
    {
        [, $actor] = $this->actor();

        Livewire::test(ViewUser::class, ['record' => (string) $actor->getAuthIdentifier()])
            ->assertActionHidden('optional_access');
    }

    private function hasDirect(User $user, string $permissionName): bool
    {
        $permissionId = Permission::query()->where('name', $permissionName)->value('id');

        return ModelHasPermission::query()
            ->where('permission_id', $permissionId)
            ->where('model_type', $user->getMorphClass())
            ->where('model_id', (string) $user->getAuthIdentifier())
            ->exists();
    }

    private function supervisorAccount(string $organizationId): User
    {
        return User::factory()->inOrganization($organizationId)->create();
    }

    /** @return array{0: string, 1: User} */
    private function actor(bool $withGrantDirect = true): array
    {
        $this->seed(AccessControlSeeder::class);

        $organization = Organization::factory()->create();
        $organizationId = (string) $organization->getKey();
        $actor = User::factory()->inOrganization($organizationId)->create();

        $abilities = ['admin.panel.access', 'identity.users.view_any', 'identity.users.view'];
        if ($withGrantDirect) {
            $abilities[] = 'accesscontrol.permissions.grant_direct';
        }

        foreach ($abilities as $name) {
            $permission = Permission::query()->firstOrCreate(['name' => $name], ['guard_name' => 'web']);
            ModelHasPermission::query()->firstOrCreate([
                'permission_id' => (string) $permission->getKey(),
                'model_type' => $actor->getMorphClass(),
                'model_id' => (string) $actor->getAuthIdentifier(),
            ]);
        }

        app(PermissionGateRegistrar::class)->register();

        $this->actingAs($actor);
        session()->put('organization_id', $organizationId);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->get(UserResource::getUrl('index', panel: 'admin'));

        return [$organizationId, $actor];
    }
}
