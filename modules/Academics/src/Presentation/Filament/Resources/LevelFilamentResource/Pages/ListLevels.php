<?php

declare(strict_types=1);

namespace Modules\Academics\Presentation\Filament\Resources\LevelFilamentResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Academics\Presentation\Filament\Resources\LevelFilamentResource;

final class ListLevels extends ListRecords
{
    protected static string $resource = LevelFilamentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
