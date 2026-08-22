<?php

declare(strict_types=1);

namespace Modules\Students\Presentation\Filament\Resources\StudentProfileResource\Pages;

use Filament\Resources\Pages\EditRecord;
use Modules\Students\Presentation\Filament\Resources\StudentProfileResource;

final class EditStudentProfile extends EditRecord
{
    protected static string $resource = StudentProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
