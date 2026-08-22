<?php

declare(strict_types=1);

namespace Modules\Assignments\Presentation\Filament\Resources\AssignmentFilamentResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Assignments\Presentation\Filament\Resources\AssignmentFilamentResource;

final class CreateAssignment extends CreateRecord
{
    protected static string $resource = AssignmentFilamentResource::class;
}
