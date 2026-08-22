<?php

declare(strict_types=1);

namespace Modules\Notifications\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Notifications\Domain\Models\NotificationDeliveryAttempt;

/**
 * تمثيل محاولة تسليم في الـ API.
 *
 * @mixin NotificationDeliveryAttempt
 */
final class NotificationDeliveryAttemptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'outbox_id' => $this->outbox_id,
            'attempt_number' => $this->attempt_number,
            'attempted_at' => $this->attempted_at?->toIso8601String(),
            'provider_response' => $this->provider_response,
            'external_message_id' => $this->external_message_id,
            'succeeded' => $this->succeeded,
            'retryable' => $this->retryable,
            'error' => $this->error,
        ];
    }
}
