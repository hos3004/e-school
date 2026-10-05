<?php

declare(strict_types=1);

namespace Modules\Assignments\Presentation\Filament\Resources\AssignmentFilamentResource\Pages;

use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Assignments\Application\Actions\UpdateAssignmentAction;
use Modules\Assignments\Domain\Models\Assignment;
use Modules\Assignments\Presentation\Filament\Resources\AssignmentFilamentResource;

final class EditAssignment extends EditRecord
{
    protected static string $resource = AssignmentFilamentResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        abort_unless($record instanceof Assignment, 404);

        return app(UpdateAssignmentAction::class)->execute(
            $record,
            $data,
            (string) auth()->id(),
            (string) $data['reason'],
        );
    }

    protected function getSavedNotificationTitle(): string
    {
        return __('assignments::messages.updated');
    }
}
