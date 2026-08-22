<?php

declare(strict_types=1);

namespace Modules\Certificates\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Certificates\Domain\Models\Badge;

/**
 * تمثيل الشارة في الـ API.
 *
 * @mixin Badge
 */
final class BadgeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'icon_path' => $this->icon_path,
            'tier' => [
                'value' => $this->tier->value,
                'label' => $this->tier->label(),
                'color' => $this->tier->color(),
            ],
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
