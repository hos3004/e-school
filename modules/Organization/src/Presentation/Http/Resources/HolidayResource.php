<?php

declare(strict_types=1);

namespace Modules\Organization\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Organization\Domain\Models\Holiday;

/**
 * @mixin Holiday
 */
final class HolidayResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'academic_calendar_id' => $this->academic_calendar_id,
            'name' => $this->name,
            'starts_on' => $this->starts_on?->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'source' => $this->source?->value,
            'blocks_scheduling' => $this->blocks_scheduling,
        ];
    }
}
