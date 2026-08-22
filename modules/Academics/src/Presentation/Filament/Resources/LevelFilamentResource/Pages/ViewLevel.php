<?php

declare(strict_types=1);

namespace Modules\Academics\Presentation\Filament\Resources\LevelFilamentResource\Pages;

use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Modules\Academics\Presentation\Filament\Resources\LevelFilamentResource;

final class ViewLevel extends ViewRecord
{
    protected static string $resource = LevelFilamentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
