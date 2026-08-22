<?php

declare(strict_types=1);

namespace Modules\Sessions\Presentation\Filament\Resources\SessionResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Sessions\Presentation\Filament\Resources\SessionResource;

final class ListSessions extends ListRecords
{
    protected static string $resource = SessionResource::class;
}
