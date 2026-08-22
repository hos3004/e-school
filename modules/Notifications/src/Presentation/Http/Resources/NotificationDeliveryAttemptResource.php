<?php

declare(strict_types=1);

namespace Modules\Notifications\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * تمثيل محاولة تسليم في الـ API.
 *
 * @mixin \Modules\Notifications\Domain\Models\NotificationDeliveryAttempt
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
            'succeeded' => $this->succeeded,
            'error' => $this->error,
        ];
    }
}
