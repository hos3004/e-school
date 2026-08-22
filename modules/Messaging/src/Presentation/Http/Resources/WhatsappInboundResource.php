<?php

declare(strict_types=1);

namespace Modules\Messaging\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Messaging\Domain\Models\WhatsappInbound;

/**
 * @property-read WhatsappInbound $resource
 */
final class WhatsappInboundResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->resource->id,
            'organization_id' => (string) $this->resource->organization_id,
            'from_phone' => $this->resource->from_phone,
            'message_id' => $this->resource->message_id,
            'body' => $this->resource->body,
            'media' => $this->resource->media ?? [],
            'received_at' => $this->resource->received_at?->toIso8601String(),
            'matched_user_id' => $this->resource->matched_user_id !== null
                ? (string) $this->resource->matched_user_id
                : null,
            'handled_by' => $this->resource->handled_by !== null
                ? (string) $this->resource->handled_by
                : null,
            'handled_at' => $this->resource->handled_at?->toIso8601String(),
        ];
    }
}
