<?php

declare(strict_types=1);

namespace Modules\Organization\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Organization\Domain\Models\Organization;

/**
 * @mixin Organization
 */
final class OrganizationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'logo_path' => $this->logo_path,
            'default_timezone' => $this->default_timezone,
            'default_currency' => $this->default_currency,
            'default_locale' => $this->default_locale,
            'supported_locales' => $this->supported_locales,
            'week_starts_on' => $this->week_starts_on,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
