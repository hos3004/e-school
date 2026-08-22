<?php

declare(strict_types=1);

namespace Modules\Assignments\Presentation\Filament\Resources\AssignmentFilamentResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Assignments\Presentation\Filament\Resources\AssignmentFilamentResource;

final class EditAssignment extends EditRecord
{
    protected static string $resource = AssignmentFilamentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label(__('assignments::filament.actions.delete')),
        ];
    }
}
