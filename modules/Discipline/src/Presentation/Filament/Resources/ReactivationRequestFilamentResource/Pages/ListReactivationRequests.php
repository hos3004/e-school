<?php

declare(strict_types=1);

namespace Modules\Discipline\Presentation\Filament\Resources\ReactivationRequestFilamentResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Discipline\Presentation\Filament\Resources\ReactivationRequestFilamentResource;

final class ListReactivationRequests extends ListRecords
{
    protected static string $resource = ReactivationRequestFilamentResource::class;
}
