<?php

declare(strict_types=1);

namespace Modules\Students\Presentation\Filament\Resources\StudentProfileResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Students\Presentation\Filament\Resources\StudentProfileResource;

final class ListStudentProfiles extends ListRecords
{
    protected static string $resource = StudentProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('students::admin.onboarding.action')),
        ];
    }
}
