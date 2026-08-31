<?php

declare(strict_types=1);

namespace Modules\Students\Presentation\Filament\Resources\RegistrationQuestionResource\Pages;

use Filament\Resources\Pages\EditRecord;
use Modules\Students\Presentation\Filament\Resources\RegistrationQuestionResource;

final class EditRegistrationQuestion extends EditRecord
{
    protected static string $resource = RegistrationQuestionResource::class;
}
