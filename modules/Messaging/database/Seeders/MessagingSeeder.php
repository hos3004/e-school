<?php

declare(strict_types=1);

namespace Modules\Messaging\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\Messaging\Application\Actions\RecordWhatsappInboundAction;
use Modules\Messaging\Domain\Enums\ConversationType;
use Modules\Messaging\Domain\Enums\ParticipantRole;
use Modules\Messaging\Domain\Models\ClassWallComment;
use Modules\Messaging\Domain\Models\ClassWallPost;
use Modules\Messaging\Domain\Models\Conversation;
use Modules\Messaging\Domain\Models\ConversationParticipant;
use Modules\Messaging\Domain\Models\Message;
use Shared\Testing\Fixtures;

/**
 * بيانات تجريبية لموديول Messaging.
 */
final class MessagingSeeder extends Seeder
{
    public function run(): void
    {
        $organizationId = Fixtures::organizationId();
        $staffA = Fixtures::userId();
        $staffB = Fixtures::userId();
        $parent = Fixtures::userId();

        // ── محادثة مباشرة بين موظف وولي أمر ────────────────────────────
        $direct = new Conversation([
            'organization_id' => $organizationId,
            'subject' => __('messaging::seeding.direct_subject'),
            'type' => ConversationType::Direct->value,
            'is_moderated' => false,
            'related_type' => null,
            'related_id' => null,
            'created_by' => $parent,
            'last_message_at' => CarbonImmutable::now('UTC'),
            'created_at' => CarbonImmutable::now('UTC')->subHours(2),
        ]);
        $direct->save();

        foreach ([
            [$parent, ParticipantRole::Owner],
            [$staffA, ParticipantRole::Member],
        ] as [$userId, $role]) {
            ConversationParticipant::query()->create([
                'organization_id' => $organizationId,
                'conversation_id' => (string) $direct->id,
                'user_id' => $userId,
                'role' => $role->value,
                'joined_at' => CarbonImmutable::now('UTC'),
                'last_read_at' => null,
                'muted_until' => null,
            ]);
        }

        foreach ([
            [$parent, __('messaging::seeding.direct_message_1'), 120],
            [$staffA, __('messaging::seeding.direct_message_2'), 90],
            [$parent, __('messaging::seeding.direct_message_3'), 30],
        ] as [$userId, $body, $minutesAgo]) {
            Message::query()->create([
                'organization_id' => $organizationId,
                'conversation_id' => (string) $direct->id,
                'user_id' => $userId,
                'body' => $body,
                'attachments' => [],
                'is_flagged' => false,
                'flagged_reason' => null,
                'moderated_by' => null,
                'moderated_at' => null,
                'edited_at' => null,
                'created_at' => CarbonImmutable::now('UTC')->subMinutes($minutesAgo),
            ]);
        }

        // ── مجموعة صفية بإشراف ─────────────────────────────────────────
        $group = new Conversation([
            'organization_id' => $organizationId,
            'subject' => __('messaging::seeding.group_subject'),
            'type' => ConversationType::Group->value,
            'is_moderated' => true,
            'related_type' => null,
            'related_id' => null,
            'created_by' => $staffA,
            'last_message_at' => CarbonImmutable::now('UTC'),
            'created_at' => CarbonImmutable::now('UTC')->subDays(3),
        ]);
        $group->save();

        foreach ([
            [$staffA, ParticipantRole::Owner],
            [$staffB, ParticipantRole::Moderator],
            [$parent, ParticipantRole::Member],
        ] as [$userId, $role]) {
            ConversationParticipant::query()->create([
                'organization_id' => $organizationId,
                'conversation_id' => (string) $group->id,
                'user_id' => $userId,
                'role' => $role->value,
                'joined_at' => CarbonImmutable::now('UTC'),
                'last_read_at' => null,
                'muted_until' => null,
            ]);
        }

        // ── حائط الصف ─────────────────────────────────────────────────
        $groupId = (string) Str::ulid();

        $post = new ClassWallPost([
            'organization_id' => $organizationId,
            'group_id' => $groupId,
            'user_id' => $staffA,
            'body' => __('messaging::seeding.wall_post_body'),
            'attachments' => [],
            'is_pinned' => true,
            'created_at' => CarbonImmutable::now('UTC')->subDay(),
        ]);
        $post->save();

        ClassWallComment::query()->create([
            'organization_id' => $organizationId,
            'post_id' => (string) $post->id,
            'user_id' => $parent,
            'body' => __('messaging::seeding.wall_comment_body'),
            'created_at' => CarbonImmutable::now('UTC')->subHours(5),
        ]);

        // ── رسالة واتساب واردة بانتظار المعالجة ────────────────────────
        app(RecordWhatsappInboundAction::class)->execute(
            organizationId: $organizationId,
            fromPhone: '+201000000001',
            messageId: 'seed-'.((string) Str::ulid()),
            body: __('messaging::seeding.whatsapp_body'),
            matchedUserId: $parent,
        );
    }
}
