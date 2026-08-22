<?php

declare(strict_types=1);

namespace Modules\Organization\Presentation\Filament\Resources\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Organization\Presentation\Filament\Resources\HolidayFilamentResource;

final class CreateHoliday extends CreateRecord
{
    protected static string $resource = HolidayFilamentResource::class;

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['organization_id'] ??= auth()->user()?->getAttribute('organization_id');

        return $data;
    }
}
