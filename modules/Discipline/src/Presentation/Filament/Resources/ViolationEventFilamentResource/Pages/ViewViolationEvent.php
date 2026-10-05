<?php

declare(strict_types=1);

namespace Modules\Discipline\Presentation\Filament\Resources\ViolationEventFilamentResource\Pages;

use Filament\Resources\Pages\ViewRecord;
use Modules\Discipline\Presentation\Filament\Resources\ViolationEventFilamentResource;

final class ViewViolationEvent extends ViewRecord
{
    protected static string $resource = ViolationEventFilamentResource::class;

    /** @return array<int, mixed> */
    protected function getHeaderActions(): array
    {
        return [ViolationEventFilamentResource::waiveAction()];
    }
}
