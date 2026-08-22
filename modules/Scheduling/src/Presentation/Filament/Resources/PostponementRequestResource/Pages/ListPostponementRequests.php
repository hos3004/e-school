<?php

declare(strict_types=1);

namespace Modules\Scheduling\Presentation\Filament\Resources\PostponementRequestResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Scheduling\Presentation\Filament\Resources\PostponementRequestResource;

final class ListPostponementRequests extends ListRecords
{
    protected static string $resource = PostponementRequestResource::class;

    /**
     * @return array<int, mixed>
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
