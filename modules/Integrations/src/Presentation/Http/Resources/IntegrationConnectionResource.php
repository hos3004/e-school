<?php

declare(strict_types=1);

namespace Modules\Integrations\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Integrations\Domain\Models\IntegrationConnection;

/**
 * تمثيل الاتصال في الـ API — بيانات الاعتماد لا تُرجع أبدًا.
 *
 * @mixin IntegrationConnection
 */
final class IntegrationConnectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'provider_id' => $this->provider_id,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
                'color' => $this->status->color(),
            ],
            'has_credentials' => $this->credentials !== null,
            'settings' => $this->settings,
            'activated_at' => $this->activated_at?->toIso8601String(),
            'disabled_at' => $this->disabled_at?->toIso8601String(),
            'last_error_at' => $this->last_error_at?->toIso8601String(),
            'last_error_message' => $this->last_error_message,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
