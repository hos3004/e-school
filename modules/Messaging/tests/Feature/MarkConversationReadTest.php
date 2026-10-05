<?php

declare(strict_types=1);

namespace Modules\Messaging\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\AccessControl\Database\Seeders\AccessControlSeeder;
use Modules\AccessControl\Domain\Enums\GuardName;
use Modules\AccessControl\Domain\Models\ModelHasPermission;
use Modules\AccessControl\Domain\Models\Permission;
use Modules\AccessControl\Infrastructure\Authorization\PermissionGateRegistrar;
use Modules\Identity\Domain\Models\User;
use Modules\Messaging\Domain\Models\ConversationParticipant;
use Shared\Testing\Fixtures;
use Tests\TestCase;

final class MarkConversationReadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Fixtures::flush();
        $this->seed(AccessControlSeeder::class);
    }

    public function test_marking_a_conversation_read_clears_the_unread_count_only_for_the_actor(): void
    {
        $organizationId = Fixtures::organizationId();
        $actor = User::factory()->inOrganization($organizationId)->create();
        $peer = User::factory()->inOrganization($organizationId)->create();
        $this->grantPermission($actor, 'message.send');
        $this->grantPermission($peer, 'message.send');

        $created = $this->actingAs($peer)->postJson('/api/messaging/direct-conversations', [
            'recipient_user_id' => (string) $actor->id,
            'subject' => 'تجربة',
            'body' => 'رسالة أولى',
        ])->assertCreated();

        $conversationId = (string) $created->json('data.id');

        $before = collect((array) $this->actingAs($actor)->getJson('/api/conversations')->json('data'))
            ->firstWhere('id', $conversationId);
        self::assertSame(1, $before['unread_count']);

        $this->actingAs($actor)
            ->postJson("/api/conversations/{$conversationId}/read")
            ->assertNoContent();

        $after = collect((array) $this->actingAs($actor)->getJson('/api/conversations')->json('data'))
            ->firstWhere('id', $conversationId);
        self::assertSame(0, $after['unread_count']);

        // القراءة شخصية — تعليم الممثل ما يمسّش last_read_at بتاع المرسل نفسه.
        self::assertNull(ConversationParticipant::query()
            ->where('conversation_id', $conversationId)
            ->where('user_id', (string) $peer->id)
            ->value('last_read_at'));
    }

    public function test_a_non_participant_cannot_mark_a_conversation_read(): void
    {
        $organizationId = Fixtures::organizationId();
        $owner = User::factory()->inOrganization($organizationId)->create();
        $peer = User::factory()->inOrganization($organizationId)->create();
        $outsider = User::factory()->inOrganization($organizationId)->create();
        $this->grantPermission($owner, 'message.send');
        $this->grantPermission($peer, 'message.send');
        $this->grantPermission($outsider, 'message.send');

        $created = $this->actingAs($owner)->postJson('/api/messaging/direct-conversations', [
            'recipient_user_id' => (string) $peer->id,
            'subject' => 'تجربة',
            'body' => 'رسالة أولى',
        ])->assertCreated();

        $conversationId = (string) $created->json('data.id');

        $this->actingAs($outsider)
            ->postJson("/api/conversations/{$conversationId}/read")
            ->assertForbidden();
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
