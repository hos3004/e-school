<?php

declare(strict_types=1);

namespace Modules\Messaging\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Messaging\Domain\Models\Conversation;

/**
 * @property-read Conversation $resource
 */
final class ConversationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var list<array{id: string, name: string, role: string}>|null $participants */
        $participants = $this->resource->getAttribute('participants_summary');

        /** @var string|null $lastMessage */
        $lastMessage = $this->resource->getAttribute('last_message_preview');

        /** @var int|null $unreadCount */
        $unreadCount = $this->resource->getAttribute('unread_count_for_viewer');

        return [
            'id' => (string) $this->resource->id,
            'organization_id' => (string) $this->resource->organization_id,
            'subject' => $this->resource->subject,
            'type' => $this->resource->type,
            'is_moderated' => (bool) $this->resource->is_moderated,
            'related_type' => $this->resource->related_type,
            'related_id' => $this->resource->related_id !== null
                ? (string) $this->resource->related_id
                : null,
            'created_by' => (string) $this->resource->created_by,
            'last_message_at' => $this->resource->last_message_at?->toIso8601String(),
            // اسم بديل لنفس last_message_at لتوافق واجهة الويب القديمة التي تتوقعه.
            'updated_at' => $this->resource->last_message_at?->toIso8601String(),
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'last_message' => $lastMessage,
            'unread_count' => $unreadCount ?? 0,
            'participants' => $participants ?? [],
            'participants_count' => $participants !== null ? count($participants) : null,
        ];
    }
}
