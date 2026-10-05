<?php

declare(strict_types=1);

namespace Modules\Assignments\Presentation\Filament\Resources\AssignmentFilamentResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Assignments\Presentation\Filament\Resources\AssignmentFilamentResource;

final class ListAssignments extends ListRecords
{
    protected static string $resource = AssignmentFilamentResource::class;

    /** @return array<CreateAction> */
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
