<?php

declare(strict_types=1);

namespace Modules\Sessions\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Sessions\Domain\Enums\SessionStatus;

/**
 * تمثيل قيد سجل حالات الحصة في الـ API.
 *
 * @mixin \Modules\Sessions\Domain\Models\SessionStatusHistory
 */
final class SessionStatusHistoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'session_id' => $this->session_id,
            'from_status' => $this->from_status !== null
                ? SessionStatus::from($this->from_status)->label()
                : null,
            'to_status' => SessionStatus::from($this->to_status)->label(),
            'reason' => $this->reason,
            'changed_by' => $this->changed_by,
            'changed_at' => $this->changed_at?->toIso8601String(),
        ];
    }
}
