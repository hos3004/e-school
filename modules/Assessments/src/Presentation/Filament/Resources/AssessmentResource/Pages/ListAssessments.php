<?php

declare(strict_types=1);

namespace Modules\Assessments\Presentation\Filament\Resources\AssessmentResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Assessments\Presentation\Filament\Resources\AssessmentResource;

final class ListAssessments extends ListRecords
{
    protected static string $resource = AssessmentResource::class;

    /** @return array<CreateAction> */
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
