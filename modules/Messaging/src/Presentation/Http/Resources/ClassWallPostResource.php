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
        /** @var string|null $authorName */
        $authorName = $this->resource->getAttribute('author_name');

        /** @var int|null $commentsCount */
        $commentsCount = $this->resource->getAttribute('comments_count');

        $attachments = $this->resource->attachments ?? [];
        $postId = (string) $this->resource->id;

        return [
            'id' => $postId,
            'organization_id' => (string) $this->resource->organization_id,
            'group_id' => (string) $this->resource->group_id,
            'user_id' => (string) $this->resource->user_id,
            'author_name' => $authorName,
            'body' => $this->resource->body,
            'attachments' => array_values(array_map(
                static fn (int $index, array $attachment): array => [
                    'type' => $attachment['type'] ?? 'image',
                    'url' => "/api/wall/posts/{$postId}/attachments/{$index}",
                ],
                array_keys($attachments),
                $attachments,
            )),
            'is_pinned' => (bool) $this->resource->is_pinned,
            'comments_count' => $commentsCount ?? 0,
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
