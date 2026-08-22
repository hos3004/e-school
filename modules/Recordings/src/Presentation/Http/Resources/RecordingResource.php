<?php

declare(strict_types=1);

namespace Modules\Recordings\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * تمثيل التسجيل في الـ API.
 *
 * @mixin \Modules\Recordings\Domain\Models\Recording
 */
final class RecordingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'session_id' => $this->session_id,
            'classroom_id' => $this->classroom_id,
            'provider' => $this->provider,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
                'color' => $this->status->color(),
            ],
            'duration_seconds' => $this->duration_seconds,
            'size_bytes' => $this->size_bytes,
            'thumbnail_path' => $this->thumbnail_path,
            'available_from' => $this->available_from?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
