<?php

declare(strict_types=1);

namespace Modules\Messaging\Tests\Feature\Authorization;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\AccessControl\Database\Seeders\AccessControlSeeder;
use Modules\AccessControl\Domain\Enums\GuardName;
use Modules\AccessControl\Domain\Models\ModelHasPermission;
use Modules\AccessControl\Domain\Models\Permission;
use Modules\AccessControl\Infrastructure\Authorization\PermissionGateRegistrar;
use Modules\Identity\Domain\Models\User;
use Modules\Messaging\Application\Actions\CreateConversationAction;
use Modules\Messaging\Domain\Enums\ConversationType;
use Modules\Messaging\Domain\Models\Conversation;
use Shared\Testing\Fixtures;
use Tests\TestCase;

final class ConversationListAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Fixtures::flush();
        $this->seed(AccessControlSeeder::class);
    }

    public function test_guest_receives_unauthorized_instead_of_method_not_allowed(): void
    {
        $this->getJson('/api/conversations')->assertUnauthorized();
    }

    public function test_authenticated_user_without_messaging_permission_is_forbidden(): void
    {
        $actor = User::factory()->inOrganization(Fixtures::organizationId())->create();

        $this->actingAs($actor)
            ->getJson('/api/conversations')
            ->assertForbidden();
    }

    public function test_participant_list_is_scoped_by_membership_and_tenant(): void
    {
        $organizationId = Fixtures::organizationId();
        $actor = User::factory()->inOrganization($organizationId)->create();
        $peer = User::factory()->inOrganization($organizationId)->create();
        $otherA = User::factory()->inOrganization($organizationId)->create();
        $otherB = User::factory()->inOrganization($organizationId)->create();
        $this->grantPermission($actor, 'message.send');

        $owned = $this->conversation($organizationId, $actor, $peer, 'Owned');
        $this->conversation($organizationId, $otherA, $otherB, 'Same tenant, not a participant');

        $foreignOrganizationId = $this->createOrganization();
        $foreignA = User::factory()->inOrganization($foreignOrganizationId)->create();
        $foreignB = User::factory()->inOrganization($foreignOrganizationId)->create();
        $this->conversation($foreignOrganizationId, $foreignA, $foreignB, 'Foreign tenant');

        $this->actingAs($actor)
            ->getJson('/api/conversations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $owned->id);
    }

    public function test_null_last_message_conversations_paginate_without_short_pages(): void
    {
        $organizationId = Fixtures::organizationId();
        $actor = User::factory()->inOrganization($organizationId)->create();
        $this->grantPermission($actor, 'message.send');

        for ($index = 0; $index < 17; $index++) {
            $peer = User::factory()->inOrganization($organizationId)->create();
            $conversation = $this->conversation($organizationId, $actor, $peer, 'Owned '.$index);
            self::assertNull($conversation->last_message_at);
        }

        $this->actingAs($actor)
            ->getJson('/api/conversations?page=1')
            ->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.total', 17);

        $this->actingAs($actor)
            ->getJson('/api/conversations?page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 17);
    }

    public function test_large_participant_list_uses_bounded_database_pagination(): void
    {
        $organizationId = Fixtures::organizationId();
        $actor = User::factory()->inOrganization($organizationId)->create();
        $peer = User::factory()->inOrganization($organizationId)->create();
        $this->grantPermission($actor, 'message.send');

        for ($index = 0; $index < 250; $index++) {
            $this->conversation($organizationId, $actor, $peer, 'Measured '.$index);
        }

        $queryCount = 0;
        DB::listen(static function () use (&$queryCount): void {
            $queryCount++;
        });

        $memoryBefore = memory_get_usage(true);
        $startedAt = hrtime(true);

        $response = $this->actingAs($actor)->getJson('/api/conversations?page=17');

        $elapsedMilliseconds = (hrtime(true) - $startedAt) / 1_000_000;
        $memoryGrowthBytes = max(memory_get_usage(true) - $memoryBefore, 0);

        $response
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.total', 250);

        self::assertLessThanOrEqual(12, $queryCount);
        self::assertLessThan(1_500.0, $elapsedMilliseconds);
        self::assertLessThan(32 * 1024 * 1024, $memoryGrowthBytes);

        fwrite(STDERR, sprintf(
            "Messaging pagination: 250 conversations, %d queries, %.2f ms, %.2f MiB growth\n",
            $queryCount,
            $elapsedMilliseconds,
            $memoryGrowthBytes / 1024 / 1024,
        ));
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

    private function conversation(
        string $organizationId,
        User $creator,
        User $participant,
        string $subject,
    ): Conversation {
        return app(CreateConversationAction::class)->execute(
            organizationId: $organizationId,
            creatorUserId: (string) $creator->id,
            type: ConversationType::Direct,
            subject: $subject,
            participantUserIds: [(string) $participant->id],
        );
    }

    private function createOrganization(): string
    {
        $id = (string) Str::ulid();

        DB::table('organizations')->insert([
            'id' => $id,
            'name' => json_encode(
                ['ar' => 'Other Organization', 'en' => 'Other Organization'],
                JSON_UNESCAPED_UNICODE,
            ),
            'slug' => 'other-'.strtolower(substr($id, -10)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }
}
