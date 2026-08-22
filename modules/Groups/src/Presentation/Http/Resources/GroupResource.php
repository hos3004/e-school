<?php

declare(strict_types=1);

namespace Modules\Groups\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Groups\Domain\Models\Group;

/**
 * تمثيل المجموعة في الـ API — قراءة فقط.
 *
 * @property-read Group $resource
 */
final class GroupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->resource->getKey(),
            'organization_id' => $this->resource->organization_id,
            'code' => $this->resource->code,
            'name' => $this->resource->name,
            'capacity' => $this->resource->capacity,
            'timezone' => $this->resource->timezone,
            'status' => $this->resource->status->value,
            'status_label' => $this->resource->status->label(),
            'starts_on' => $this->resource->starts_on?->toDateString(),
            'ends_on' => $this->resource->ends_on?->toDateString(),
            'active_members_count' => $this->when(
                isset($this->resource->memberships_count),
                fn (): ?int => $this->resource->memberships_count ?? null,
            ),
            'archived_at' => $this->resource->deleted_at?->toIso8601String(),
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
