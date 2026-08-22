<?php

declare(strict_types=1);

namespace Modules\Discipline\Presentation\Filament\Resources\DisciplineActionFilamentResource\Pages;

use Filament\Resources\Pages\ViewRecord;
use Modules\Discipline\Presentation\Filament\Resources\DisciplineActionFilamentResource;

final class ViewDisciplineAction extends ViewRecord
{
    protected static string $resource = DisciplineActionFilamentResource::class;
}
