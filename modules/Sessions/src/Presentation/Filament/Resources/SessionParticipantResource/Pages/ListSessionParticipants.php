<?php

declare(strict_types=1);

namespace Modules\Sessions\Presentation\Filament\Resources\SessionParticipantResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Sessions\Presentation\Filament\Resources\SessionParticipantResource;

final class ListSessionParticipants extends ListRecords
{
    protected static string $resource = SessionParticipantResource::class;
}
