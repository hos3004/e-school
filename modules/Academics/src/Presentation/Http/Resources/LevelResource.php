<?php

declare(strict_types=1);

namespace Modules\Academics\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Academics\Domain\Models\Level;

/**
 * تمثيل مستوى في الـ API — قراءة فقط.
 *
 * @property-read Level $resource
 */
final class LevelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->resource->getKey(),
            'program_id' => $this->resource->program_id,
            'code' => $this->resource->code,
            'name' => $this->resource->name,
            'sort_order' => $this->resource->sort_order,
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
