<?php

declare(strict_types=1);

namespace Modules\Messaging\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\AccessControl\Database\Seeders\AccessControlSeeder;
use Modules\AccessControl\Domain\Enums\GuardName;
use Modules\AccessControl\Domain\Models\ModelHasPermission;
use Modules\AccessControl\Domain\Models\Permission;
use Modules\AccessControl\Infrastructure\Authorization\PermissionGateRegistrar;
use Modules\Identity\Domain\Models\User;
use Modules\Messaging\Domain\Models\Conversation;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Shared\Testing\Fixtures;
use Tests\TestCase;

final class SupervisionConversationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Fixtures::flush();
        $this->seed(AccessControlSeeder::class);
    }

    public function test_teacher_reaches_platform_admin_and_the_admin_is_notified(): void
    {
        $organizationId = Fixtures::organizationId();
        $teacher = User::factory()->inOrganization($organizationId)->create();
        $admin = User::factory()->inOrganization($organizationId)->create();
        $this->grantPermission($teacher, 'message.send');
        $this->grantPermission($admin, 'message.send');
        $this->grantPermission($admin, 'message.moderate');
        $this->assignRole($admin, 'platform_admin');

        $response = $this->actingAs($teacher)
            ->postJson('/api/messaging/supervision-conversations', [
                'body' => 'محتاج دعم في متابعة أحد الطلاب',
            ])
            ->assertCreated();

        $conversationId = (string) $response->json('data.id');

        self::assertTrue(Conversation::query()
            ->whereKey($conversationId)
            ->where('related_type', 'supervision')
            ->where('related_id', (string) $teacher->id)
            ->exists());

        self::assertSame(1, NotificationOutbox::query()
            ->where('user_id', (string) $admin->id)
            ->where('event_name', 'message.sent')
            ->count());
    }

    public function test_a_second_message_reuses_the_same_supervision_conversation(): void
    {
        $organizationId = Fixtures::organizationId();
        $teacher = User::factory()->inOrganization($organizationId)->create();
        $admin = User::factory()->inOrganization($organizationId)->create();
        $this->grantPermission($teacher, 'message.send');
        $this->grantPermission($admin, 'message.send');
        $this->assignRole($admin, 'platform_admin');

        $first = $this->actingAs($teacher)
            ->postJson('/api/messaging/supervision-conversations', ['body' => 'أول رسالة'])
            ->assertCreated();

        $second = $this->actingAs($teacher)
            ->postJson('/api/messaging/supervision-conversations', ['body' => 'رسالة تانية'])
            ->assertCreated();

        self::assertSame($first->json('data.id'), $second->json('data.id'));
        self::assertSame(1, Conversation::query()->where('related_type', 'supervision')->count());
    }

    public function test_rejects_when_no_account_currently_holds_the_supervision_role(): void
    {
        $organizationId = Fixtures::organizationId();
        $teacher = User::factory()->inOrganization($organizationId)->create();
        $this->grantPermission($teacher, 'message.send');

        $this->actingAs($teacher)
            ->postJson('/api/messaging/supervision-conversations', ['body' => 'محتاج مساعدة'])
            ->assertUnprocessable();

        self::assertSame(0, Conversation::query()->count());
    }

    private function assignRole(User $user, string $roleName): void
    {
        $roleId = DB::table('roles')
            ->whereNull('organization_id')
            ->where('guard_name', GuardName::Web->value)
            ->where('name', $roleName)
            ->value('id');

        self::assertIsString($roleId, "Role '{$roleName}' was not seeded by AccessControlSeeder.");

        DB::table('model_has_roles')->insert([
            'role_id' => $roleId,
            'model_type' => $user->getMorphClass(),
            'model_id' => (string) $user->getAuthIdentifier(),
        ]);
    }

    private function grantPermission(User $user, string $permissionName): void
    {
        $permission = Permission::query()->firstOrCreate(
            ['name' => $permissionName],
            ['guard_name' => GuardName::Web->value, 'module' => 'Messaging'],
        );

        ModelHasPermission::query()->create([
            'permission_id' => (string) $permission->getKey(),
            'model_type' => $user->getMorphClass(),
            'model_id' => (string) $user->getAuthIdentifier(),
        ]);

        app(PermissionGateRegistrar::class)->register();
    }
}
