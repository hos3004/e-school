<?php

declare(strict_types=1);

namespace Modules\Guardians\Presentation\Filament\Resources\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Guardians\Presentation\Filament\Resources\GuardianProfileFilamentResource;

final class ManageGuardianProfiles extends ListRecords
{
    protected static string $resource = GuardianProfileFilamentResource::class;
}
