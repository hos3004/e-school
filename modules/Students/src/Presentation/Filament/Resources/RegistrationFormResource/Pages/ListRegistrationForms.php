<?php

declare(strict_types=1);

namespace Modules\Students\Presentation\Filament\Resources\RegistrationFormResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Students\Presentation\Filament\Resources\RegistrationFormResource;

final class ListRegistrationForms extends ListRecords
{
    protected static string $resource = RegistrationFormResource::class;

    /** @return list<CreateAction> */
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
