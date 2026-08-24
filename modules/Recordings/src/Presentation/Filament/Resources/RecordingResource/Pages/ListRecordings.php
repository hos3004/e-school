<?php

declare(strict_types=1);

namespace Modules\Recordings\Presentation\Filament\Resources\RecordingResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Recordings\Presentation\Filament\Resources\RecordingResource;

final class ListRecordings extends ListRecords
{
    protected static string $resource = RecordingResource::class;
}
