<?php

declare(strict_types=1);

namespace Modules\Academics\Presentation\Filament\Resources\ProgramFilamentResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Academics\Presentation\Filament\Resources\ProgramFilamentResource;

final class ListPrograms extends ListRecords
{
    protected static string $resource = ProgramFilamentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
