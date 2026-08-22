<?php

declare(strict_types=1);

namespace Modules\Assignments\Presentation\Filament\Resources\AssignmentFilamentResource\Pages;

use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Modules\Assignments\Presentation\Filament\Resources\AssignmentFilamentResource;

final class ViewAssignment extends ViewRecord
{
    protected static string $resource = AssignmentFilamentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->label(__('assignments::filament.actions.edit')),
        ];
    }
}
