<?php

declare(strict_types=1);

namespace Modules\Academics\Presentation\Filament\Resources\ProgramFilamentResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Academics\Presentation\Filament\Resources\ProgramFilamentResource;

final class EditProgram extends EditRecord
{
    protected static string $resource = ProgramFilamentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
