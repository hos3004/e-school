<?php

declare(strict_types=1);

namespace Modules\Scheduling\Presentation\Filament\Resources\ScheduleResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Scheduling\Presentation\Filament\Resources\ScheduleResource;

final class ListSchedules extends ListRecords
{
    protected static string $resource = ScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
