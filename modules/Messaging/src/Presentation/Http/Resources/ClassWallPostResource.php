<?php

declare(strict_types=1);

namespace Modules\Messaging\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Messaging\Domain\Models\ClassWallPost;

/**
 * @property-read ClassWallPost $resource
 */
final class ClassWallPostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->resource->id,
            'organization_id' => (string) $this->resource->organization_id,
            'group_id' => (string) $this->resource->group_id,
            'user_id' => (string) $this->resource->user_id,
            'body' => $this->resource->body,
            'attachments' => $this->resource->attachments ?? [],
            'is_pinned' => (bool) $this->resource->is_pinned,
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
