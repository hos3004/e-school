<?php

declare(strict_types=1);

namespace Modules\Assignments\Presentation\Filament\Resources\AssignmentFilamentResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Assignments\Application\Actions\CreateAssignmentAction;
use Modules\Assignments\Presentation\Filament\Resources\AssignmentFilamentResource;

final class CreateAssignment extends CreateRecord
{
    protected static string $resource = AssignmentFilamentResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        $data['organization_id'] = (string) auth()->user()?->getAttribute('organization_id');

        return app(CreateAssignmentAction::class)->execute(
            $data,
            (string) auth()->id(),
            (string) $data['reason'],
        );
    }

    protected function getCreatedNotificationTitle(): string
    {
        return __('assignments::messages.created');
    }

    protected function getRedirectUrl(): string
    {
        return AssignmentFilamentResource::getUrl('view', ['record' => $this->record]);
    }
}
