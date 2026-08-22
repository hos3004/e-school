<?php

declare(strict_types=1);

namespace Modules\Messaging\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Messaging\Domain\Models\ClassWallComment;

/**
 * @property-read ClassWallComment $resource
 */
final class ClassWallCommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->resource->id,
            'organization_id' => (string) $this->resource->organization_id,
            'post_id' => (string) $this->resource->post_id,
            'user_id' => (string) $this->resource->user_id,
            'body' => $this->resource->body,
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
