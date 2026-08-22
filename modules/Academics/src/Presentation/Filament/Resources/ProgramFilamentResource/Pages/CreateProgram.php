<?php

declare(strict_types=1);

namespace Modules\Academics\Presentation\Filament\Resources\ProgramFilamentResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Academics\Presentation\Filament\Resources\ProgramFilamentResource;

final class CreateProgram extends CreateRecord
{
    protected static string $resource = ProgramFilamentResource::class;
}
