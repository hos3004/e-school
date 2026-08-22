<?php

declare(strict_types=1);

namespace Modules\Groups\Presentation\Filament\Resources\GroupResource\Pages;

use Filament\Resources\Pages\ViewRecord;
use Modules\Groups\Presentation\Filament\Resources\GroupResource;

final class ViewGroup extends ViewRecord
{
    protected static string $resource = GroupResource::class;
}
