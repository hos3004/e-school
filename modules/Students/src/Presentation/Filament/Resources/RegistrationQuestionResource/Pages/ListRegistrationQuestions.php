<?php

declare(strict_types=1);

namespace Modules\Students\Presentation\Filament\Resources\RegistrationQuestionResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Students\Presentation\Filament\Resources\RegistrationQuestionResource;

final class ListRegistrationQuestions extends ListRecords
{
    protected static string $resource = RegistrationQuestionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
