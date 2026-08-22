<?php

declare(strict_types=1);

namespace Modules\Sessions\Presentation\Filament\Resources\SessionResource\Pages;

use Filament\Resources\Pages\ViewRecord;
use Modules\Sessions\Presentation\Filament\Resources\SessionResource;

final class ViewSession extends ViewRecord
{
    protected static string $resource = SessionResource::class;
}
