<?php

declare(strict_types=1);

namespace Modules\Assessments\Presentation\Filament\Resources\AssessmentResource\Pages;

use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Modules\Assessments\Presentation\Filament\Resources\AssessmentResource;

final class ViewAssessment extends ViewRecord
{
    protected static string $resource = AssessmentResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
