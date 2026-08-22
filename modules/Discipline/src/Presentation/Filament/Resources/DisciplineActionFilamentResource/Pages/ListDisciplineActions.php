<?php

declare(strict_types=1);

namespace Modules\Discipline\Presentation\Filament\Resources\DisciplineActionFilamentResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Discipline\Presentation\Filament\Resources\DisciplineActionFilamentResource;

final class ListDisciplineActions extends ListRecords
{
    protected static string $resource = DisciplineActionFilamentResource::class;
}
