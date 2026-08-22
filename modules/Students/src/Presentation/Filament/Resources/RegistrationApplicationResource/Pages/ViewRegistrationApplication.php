<?php

declare(strict_types=1);

namespace Modules\Students\Presentation\Filament\Resources\RegistrationApplicationResource\Pages;

use Filament\Resources\Pages\ViewRecord;
use Modules\Students\Presentation\Filament\Resources\RegistrationApplicationResource;

final class ViewRegistrationApplication extends ViewRecord
{
    protected static string $resource = RegistrationApplicationResource::class;
}
