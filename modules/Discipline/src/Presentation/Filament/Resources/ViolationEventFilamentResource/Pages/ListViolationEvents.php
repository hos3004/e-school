<?php

declare(strict_types=1);

namespace Modules\Discipline\Presentation\Filament\Resources\ViolationEventFilamentResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Discipline\Presentation\Filament\Resources\ViolationEventFilamentResource;

final class ListViolationEvents extends ListRecords
{
    protected static string $resource = ViolationEventFilamentResource::class;
}
