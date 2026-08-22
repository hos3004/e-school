<?php

declare(strict_types=1);

namespace Modules\Certificates\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Certificates\Domain\Models\Certificate;

/**
 * تمثيل الشهادة في الـ API.
 *
 * @mixin Certificate
 */
final class CertificateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'certificate_template_id' => $this->certificate_template_id,
            'student_profile_id' => $this->student_profile_id,
            'program_id' => $this->program_id,
            'enrollment_id' => $this->enrollment_id,
            'serial_number' => $this->serial_number,
            'title' => $this->title,
            'issued_at' => $this->issued_at?->toIso8601String(),
            'issued_by' => $this->issued_by,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'metadata' => $this->metadata,
            'is_expired' => $this->expires_at !== null && $this->expired(),
            'revoked_at' => $this->deleted_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
