<?php

declare(strict_types=1);

namespace Modules\Sessions\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Sessions\Domain\Models\SessionParticipant;

/**
 * تمثيل مشارك الحصة في الـ API.
 *
 * @mixin SessionParticipant
 */
final class SessionParticipantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'session_id' => $this->session_id,
            'student_profile_id' => $this->student_profile_id,
            'enrollment_id' => $this->enrollment_id,
            'invited_at' => $this->invited_at?->toIso8601String(),
            'first_joined_at' => $this->first_joined_at?->toIso8601String(),
            'last_left_at' => $this->last_left_at?->toIso8601String(),
            'attended_minutes' => $this->attended_minutes,
        ];
    }
}
