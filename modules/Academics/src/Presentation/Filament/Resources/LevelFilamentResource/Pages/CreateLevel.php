<?php

declare(strict_types=1);

namespace Modules\Academics\Presentation\Filament\Resources\LevelFilamentResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Academics\Presentation\Filament\Resources\LevelFilamentResource;

final class CreateLevel extends CreateRecord
{
    protected static string $resource = LevelFilamentResource::class;
}
