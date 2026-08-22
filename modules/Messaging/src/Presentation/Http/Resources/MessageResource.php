<?php

declare(strict_types=1);

namespace Modules\Messaging\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Messaging\Domain\Models\Message;

/**
 * @property-read Message $resource
 */
final class MessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->resource->id,
            'organization_id' => (string) $this->resource->organization_id,
            'conversation_id' => (string) $this->resource->conversation_id,
            'user_id' => (string) $this->resource->user_id,
            'body' => $this->resource->body,
            'attachments' => $this->resource->attachments ?? [],
            'is_flagged' => (bool) $this->resource->is_flagged,
            'flagged_reason' => $this->resource->flagged_reason,
            'edited_at' => $this->resource->edited_at?->toIso8601String(),
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
