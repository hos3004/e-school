<?php

declare(strict_types=1);

namespace Modules\Integrations\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Integrations\Domain\Models\IntegrationProvider;

/**
 * تمثيل مزوّد التكاملات في الـ API.
 *
 * @mixin IntegrationProvider
 */
final class IntegrationProviderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'category' => $this->category,
            'driver' => $this->driver,
            'is_active' => $this->is_active,
            'default_settings' => $this->default_settings,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
