<?php

declare(strict_types=1);

namespace Modules\Guardians\Presentation\Filament\Resources\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Guardians\Presentation\Filament\Resources\GuardianProfileFilamentResource;

final class ManageGuardianProfiles extends ListRecords
{
    protected static string $resource = GuardianProfileFilamentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('guardians::admin.onboarding.create_action')),
        ];
    }
}
