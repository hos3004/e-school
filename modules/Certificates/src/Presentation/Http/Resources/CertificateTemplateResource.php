<?php

declare(strict_types=1);

namespace Modules\Certificates\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Certificates\Domain\Models\CertificateTemplate;

/**
 * تمثيل قالب الشهادة في الـ API.
 *
 * @mixin CertificateTemplate
 */
final class CertificateTemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'program_id' => $this->program_id,
            'name' => $this->name,
            'layout' => $this->layout,
            'background_image_path' => $this->background_image_path,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
