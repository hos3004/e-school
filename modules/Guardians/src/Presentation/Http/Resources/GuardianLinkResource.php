<?php

declare(strict_types=1);

namespace Modules\Guardians\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Guardians\Domain\Models\GuardianLink;

/**
 * @mixin GuardianLink
 */
final class GuardianLinkResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'guardian_profile_id' => $this->guardian_profile_id,
            'student_profile_id' => $this->student_profile_id,
            'relationship' => $this->relationship?->value,
            'relationship_label' => $this->relationship?->label(),
            'is_primary' => $this->is_primary,
            'can_act_for' => $this->can_act_for,
            'visible_sections' => $this->visible_sections ?? [],
            'verified_at' => $this->verified_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
