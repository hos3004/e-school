<?php

declare(strict_types=1);

namespace Modules\Assessments\Presentation\Filament\Resources\AssessmentResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Assessments\Application\Actions\CreateAssessmentAction;
use Modules\Assessments\Presentation\Filament\Resources\AssessmentResource;

final class CreateAssessment extends CreateRecord
{
    protected static string $resource = AssessmentResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        $data['organization_id'] = AssessmentResource::organizationId();

        return app(CreateAssessmentAction::class)->execute(
            data: $data,
            actorId: (string) auth()->id(),
            reason: (string) $data['reason'],
            canManageAll: auth()->user()?->can('assessment.manage') ?? false,
        );
    }

    protected function getCreatedNotificationTitle(): string
    {
        return __('assessments::messages.created');
    }

    protected function getRedirectUrl(): string
    {
        return AssessmentResource::getUrl('view', ['record' => $this->record]);
    }
}
