<?php

declare(strict_types=1);

namespace Modules\Discipline\Presentation\Filament\Resources\ReactivationRequestFilamentResource\Pages;

use Filament\Resources\Pages\ViewRecord;
use Modules\Discipline\Presentation\Filament\Resources\ReactivationRequestFilamentResource;

final class ViewReactivationRequest extends ViewRecord
{
    protected static string $resource = ReactivationRequestFilamentResource::class;
}
