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
use Modules\Messaging\Domain\Models\Conversation;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Shared\Testing\Fixtures;
use Tests\TestCase;

/**
 * MessageSent كان يُبث بلا مستمع؛ لا رسالة سابقة أنتجت إشعارًا حقيقيًا.
 * هذه الحزمة تثبت السلك الكامل: الحدث → الإشعار → القالب المُرسَل، وأن
 * الحقول التي تضيفها الواجهة (participants/unread_count/sender_name)
 * تصل فعلًا في استجابة الـAPI لا في الكود فقط.
 */
final class MessageNotificationAndEnrichmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Fixtures::flush();
        $this->seed(AccessControlSeeder::class);
    }

    public function test_direct_message_notifies_the_recipient_only(): void
    {
        $organizationId = Fixtures::organizationId();
        $sender = User::factory()->inOrganization($organizationId)->create(['name' => 'Sender One']);
        $recipient = User::factory()->inOrganization($organizationId)->create();
        $this->grantPermission($sender, 'message.send');
        $this->grantPermission($recipient, 'message.send');

        $start = $this->actingAs($sender)->postJson('/api/messaging/direct-conversations', [
            'recipient_user_id' => (string) $recipient->id,
            'subject' => 'حول الواجب',
            'body' => 'هل يمكنك مراجعة الحصة الأخيرة؟',
        ])->assertCreated();

        $conversationId = (string) $start->json('data.id');

        $outboxForRecipient = NotificationOutbox::query()
            ->where('user_id', (string) $recipient->id)
            ->where('event_name', 'message.sent')
            ->get();

        self::assertCount(1, $outboxForRecipient);
        self::assertSame('message_received', $outboxForRecipient->first()->category);
        self::assertSame('in_app', $outboxForRecipient->first()->channel);

        self::assertSame(0, NotificationOutbox::query()
            ->where('user_id', (string) $sender->id)
            ->where('event_name', 'message.sent')
            ->count());

        $body = json_decode((string) json_encode($outboxForRecipient->first()->body), true);
        $renderedText = collect((array) $body)->implode(' ');
        self::assertStringContainsString('Sender One', $renderedText);
        self::assertStringContainsString('هل يمكنك مراجعة الحصة الأخيرة؟', $renderedText);

        unset($conversationId);
    }

    public function test_group_message_notifies_every_other_participant(): void
    {
        $organizationId = Fixtures::organizationId();
        $owner = User::factory()->inOrganization($organizationId)->create();
        $memberA = User::factory()->inOrganization($organizationId)->create();
        $memberB = User::factory()->inOrganization($organizationId)->create();
        $this->grantPermission($owner, 'message.send');
        $this->grantPermission($memberA, 'message.send');
        $this->grantPermission($memberB, 'message.send');

        $created = $this->actingAs($owner)->postJson('/api/conversations', [
            'type' => 'group',
            'subject' => 'مجموعة الصف',
            'participant_user_ids' => [(string) $memberA->id, (string) $memberB->id],
        ])->assertCreated();

        $conversationId = (string) $created->json('data.id');

        $this->actingAs($owner)->postJson("/api/conversations/{$conversationId}/messages", [
            'body' => 'تذكير بموعد الحصة غدًا',
        ])->assertCreated();

        self::assertSame(1, NotificationOutbox::query()
            ->where('user_id', (string) $memberA->id)
            ->where('event_name', 'message.sent')
            ->count());
        self::assertSame(1, NotificationOutbox::query()
            ->where('user_id', (string) $memberB->id)
            ->where('event_name', 'message.sent')
            ->count());
        self::assertSame(0, NotificationOutbox::query()
            ->where('user_id', (string) $owner->id)
            ->where('event_name', 'message.sent')
            ->count());
    }

    public function test_conversation_list_and_messages_are_enriched_for_mobile(): void
    {
        $organizationId = Fixtures::organizationId();
        $actor = User::factory()->inOrganization($organizationId)->create(['name' => 'Actor Name']);
        $peer = User::factory()->inOrganization($organizationId)->create(['name' => 'Peer Name']);
        $this->grantPermission($actor, 'message.send');
        $this->grantPermission($peer, 'message.send');

        $created = $this->actingAs($actor)->postJson('/api/messaging/direct-conversations', [
            'recipient_user_id' => (string) $peer->id,
            'subject' => 'محادثة تجريبية',
            'body' => 'أول رسالة',
        ])->assertCreated();

        $conversationId = (string) $created->json('data.id');

        // الراسل قرأ محادثته فور الإرسال، فلا رسائل غير مقروءة له.
        $listForActor = $this->actingAs($actor)->getJson('/api/conversations')->assertOk();
        $actorItem = collect((array) $listForActor->json('data'))->firstWhere('id', $conversationId);
        self::assertNotNull($actorItem);
        self::assertSame(0, $actorItem['unread_count']);
        self::assertSame('أول رسالة', $actorItem['last_message']);
        self::assertCount(2, $actorItem['participants']);
        self::assertContains('Peer Name', array_column($actorItem['participants'], 'name'));

        // المستلم لم يقرأ بعد، فتظهر له رسالة واحدة غير مقروءة.
        $listForPeer = $this->actingAs($peer)->getJson('/api/conversations')->assertOk();
        $peerItem = collect((array) $listForPeer->json('data'))->firstWhere('id', $conversationId);
        self::assertSame(1, $peerItem['unread_count']);

        $messages = $this->actingAs($peer)
            ->getJson("/api/conversations/{$conversationId}/messages")
            ->assertOk();
        self::assertSame('Actor Name', $messages->json('data.0.sender_name'));

        self::assertTrue(Conversation::query()->whereKey($conversationId)->exists());
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
