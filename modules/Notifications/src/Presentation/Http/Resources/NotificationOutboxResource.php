<?php

declare(strict_types=1);

namespace Modules\Notifications\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Notifications\Domain\Models\NotificationOutbox;

/**
 * تمثيل رسالة صندوق الإرسال في الـ API.
 *
 * @mixin NotificationOutbox
 */
final class NotificationOutboxResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'user_id' => $this->user_id,
            'category' => $this->category,
            'channel' => $this->channel,
            'locale' => $this->locale,
            'event_name' => $this->event_name,
            'event_id' => $this->event_id,
            'correlation_id' => $this->correlation_id,
            'subject' => $this->subject,
            'body' => $this->body,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
                'color' => $this->status->color(),
            ],
            'attempts' => $this->attempts,
            'last_error' => $this->last_error,
            'last_error_retryable' => $this->last_error_retryable,
            'scheduled_for' => $this->scheduled_for?->toIso8601String(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
