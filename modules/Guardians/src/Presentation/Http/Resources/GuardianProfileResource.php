<?php

declare(strict_types=1);

namespace Modules\Guardians\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Modules\Guardians\Domain\Models\GuardianProfile
 */
final class GuardianProfileResource extends JsonResource
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
            'national_id_last4' => $this->national_id_last4,
            'occupation' => $this->occupation,
            'preferred_contact_channel' => $this->preferred_contact_channel?->value,
            'preferred_contact_channel_label' => $this->preferred_contact_channel?->label(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
