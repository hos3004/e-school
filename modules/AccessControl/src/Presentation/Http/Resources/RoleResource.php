<?php

declare(strict_types=1);

namespace Modules\AccessControl\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\AccessControl\Domain\Models\Role;

/**
 * @mixin Role
 */
final class RoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->getKey(),
            'organization_id' => $this->organization_id,
            'name' => $this->name,
            'guard_name' => $this->guard_name?->value,
            'is_system' => $this->is_system,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
