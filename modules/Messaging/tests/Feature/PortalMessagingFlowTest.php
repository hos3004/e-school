<?php

declare(strict_types=1);

namespace Modules\Messaging\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Modules\AccessControl\Database\Seeders\AccessControlSeeder;
use Modules\AccessControl\Domain\Enums\GuardName;
use Modules\AccessControl\Domain\Models\ModelHasPermission;
use Modules\AccessControl\Domain\Models\Permission;
use Modules\AccessControl\Infrastructure\Authorization\PermissionGateRegistrar;
use Modules\Identity\Domain\Models\User;
use Modules\Messaging\Domain\Models\Conversation;
use Modules\Messaging\Domain\Models\Message;
use Shared\Testing\Fixtures;
use Tests\TestCase;

final class PortalMessagingFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Fixtures::flush();
        $this->seed(AccessControlSeeder::class);
    }

    public function test_authorized_user_can_open_messaging_pages(): void
    {
        $actor = User::factory()->inOrganization(Fixtures::organizationId())->create();
        $this->grantPermission($actor, 'message.send');

        $this->actingAs($actor)->get('/messages')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Shared/Messaging/Inbox'));

        $this->actingAs($actor)->get('/messages/create')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Shared/Messaging/Create'));
    }

    public function test_recipient_search_is_tenant_scoped_and_excludes_actor(): void
    {
        $organizationId = Fixtures::organizationId();
        $actor = User::factory()->inOrganization($organizationId)->create(['name' => 'Needle Actor']);
        $peer = User::factory()->inOrganization($organizationId)->create(['name' => 'Needle Peer']);
        $foreign = User::factory()->inOrganization($this->createOrganization())->create(['name' => 'Needle Foreign']);
        $this->grantPermission($actor, 'message.send');

        $this->actingAs($actor)
            ->getJson('/api/messaging/recipients?q=Needle')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $peer->id)
            ->assertJsonMissing(['id' => (string) $actor->id])
            ->assertJsonMissing(['id' => (string) $foreign->id]);
    }

    public function test_direct_conversation_and_first_message_are_created_together(): void
    {
        $organizationId = Fixtures::organizationId();
        $actor = User::factory()->inOrganization($organizationId)->create();
        $peer = User::factory()->inOrganization($organizationId)->create();
        $this->grantPermission($actor, 'message.send');

        $response = $this->actingAs($actor)->postJson('/api/messaging/direct-conversations', [
            'recipient_user_id' => (string) $peer->id,
            'subject' => 'Progress question',
            'body' => 'Could you review the latest lesson?',
        ]);

        $response->assertCreated();
        $conversationId = (string) $response->json('data.id');

        self::assertTrue(Conversation::query()->whereKey($conversationId)->exists());
        self::assertTrue(Message::query()
            ->where('conversation_id', $conversationId)
            ->where('user_id', (string) $actor->id)
            ->where('body', 'Could you review the latest lesson?')
            ->exists());
    }

    public function test_foreign_tenant_recipient_is_rejected_without_partial_conversation(): void
    {
        $actor = User::factory()->inOrganization(Fixtures::organizationId())->create();
        $foreign = User::factory()->inOrganization($this->createOrganization())->create();
        $this->grantPermission($actor, 'message.send');

        $this->actingAs($actor)->postJson('/api/messaging/direct-conversations', [
            'recipient_user_id' => (string) $foreign->id,
            'subject' => 'Forbidden',
            'body' => 'This must not be delivered.',
        ])->assertUnprocessable();

        self::assertSame(0, Conversation::query()->count());
        self::assertSame(0, Message::query()->count());
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

    private function createOrganization(): string
    {
        $id = (string) Str::ulid();

        DB::table('organizations')->insert([
            'id' => $id,
            'name' => json_encode(['ar' => 'مؤسسة أخرى', 'en' => 'Other Organization'], JSON_UNESCAPED_UNICODE),
            'slug' => 'other-'.strtolower(substr($id, -10)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }
}
