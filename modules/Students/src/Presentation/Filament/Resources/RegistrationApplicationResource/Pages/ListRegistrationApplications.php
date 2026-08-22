<?php

declare(strict_types=1);

namespace Modules\Students\Presentation\Filament\Resources\RegistrationApplicationResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Students\Presentation\Filament\Resources\RegistrationApplicationResource;

final class ListRegistrationApplications extends ListRecords
{
    protected static string $resource = RegistrationApplicationResource::class;
}
