<?php

declare(strict_types=1);

namespace Modules\Organization\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Organization\Domain\Models\OrganizationSetting;

/**
 * @mixin OrganizationSetting
 */
final class OrganizationSettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'key' => $this->key,
            'value' => $this->value,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
