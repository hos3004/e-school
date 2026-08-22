<?php

declare(strict_types=1);

namespace Modules\Certificates\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Certificates\Domain\Models\BadgeAward;

/**
 * تمثيل منح الشارة في الـ API.
 *
 * @mixin BadgeAward
 */
final class BadgeAwardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'badge_id' => $this->badge_id,
            'user_id' => $this->user_id,
            'awarded_by' => $this->awarded_by,
            'reason' => $this->reason,
            'awarded_at' => $this->awarded_at?->toIso8601String(),
        ];
    }
}
