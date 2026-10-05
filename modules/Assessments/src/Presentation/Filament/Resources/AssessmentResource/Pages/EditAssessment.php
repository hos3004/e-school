<?php

declare(strict_types=1);

namespace Modules\Assessments\Presentation\Filament\Resources\AssessmentResource\Pages;

use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Assessments\Application\Actions\UpdateAssessmentAction;
use Modules\Assessments\Domain\Models\Assessment;
use Modules\Assessments\Presentation\Filament\Resources\AssessmentResource;

final class EditAssessment extends EditRecord
{
    protected static string $resource = AssessmentResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        abort_unless($record instanceof Assessment, 404);

        return app(UpdateAssessmentAction::class)->execute(
            assessment: $record,
            data: $data,
            actorId: (string) auth()->id(),
            reason: (string) $data['reason'],
        );
    }

    protected function getSavedNotificationTitle(): string
    {
        return __('assessments::messages.updated');
    }
}
