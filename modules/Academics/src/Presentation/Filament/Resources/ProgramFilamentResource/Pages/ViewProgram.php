<?php

declare(strict_types=1);

namespace Modules\Academics\Presentation\Filament\Resources\ProgramFilamentResource\Pages;

use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Modules\Academics\Presentation\Filament\Resources\ProgramFilamentResource;

final class ViewProgram extends ViewRecord
{
    protected static string $resource = ProgramFilamentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
