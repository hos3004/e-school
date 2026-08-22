<?php

declare(strict_types=1);

namespace Modules\Notifications\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Notifications\Domain\Models\NotificationPreference;

/**
 * تمثيل تفضيل إشعارات في الـ API.
 *
 * @mixin NotificationPreference
 */
final class NotificationPreferenceResource extends JsonResource
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
            'enabled' => $this->enabled,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
