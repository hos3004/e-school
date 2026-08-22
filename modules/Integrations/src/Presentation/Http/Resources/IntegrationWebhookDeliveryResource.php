<?php

declare(strict_types=1);

namespace Modules\Integrations\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Integrations\Domain\Models\IntegrationWebhookDelivery;

/**
 * تمثيل إيصال Webhook في الـ API.
 *
 * @mixin IntegrationWebhookDelivery
 */
final class IntegrationWebhookDeliveryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'connection_id' => $this->connection_id,
            'direction' => [
                'value' => $this->direction->value,
                'label' => $this->direction->label(),
            ],
            'event_type' => $this->event_type,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
                'color' => $this->status->color(),
            ],
            'attempts' => $this->attempts,
            'response_code' => $this->response_code,
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'next_retry_at' => $this->next_retry_at?->toIso8601String(),
            'failed_at' => $this->failed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
