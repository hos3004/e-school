<?php

declare(strict_types=1);

namespace Modules\Academics\Presentation\Filament\Resources\ProgramFilamentResource\Pages;

use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Academics\Application\Actions\UpdateProgramAction;
use Modules\Academics\Domain\Models\Program;
use Modules\Academics\Presentation\Filament\Resources\ProgramFilamentResource;

final class EditProgram extends EditRecord
{
    protected static string $resource = ProgramFilamentResource::class;

    protected function getHeaderActions(): array
    {
        return [ViewAction::make()];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $program = $this->getRecord();
        abort_unless($program instanceof Program, 404);
        $eligibility = $program->eligibility;
        if ($eligibility !== null) {
            $data['eligibility'] = [
                'countries' => $eligibility->countries ?? [],
                'regions' => $eligibility->regions ?? [],
                'age_from' => $eligibility->age_from,
                'age_to' => $eligibility->age_to,
                'gender' => $eligibility->gender?->value,
                'manual_approval_required' => (bool) $eligibility->manual_approval_required,
                'teacher_gender_rule' => (string) $eligibility->teacher_gender_rule,
                'requires_individual_sessions' => (bool) $eligibility->requires_individual_sessions,
            ];
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        abort_unless($record instanceof Program, 404);

        return app(UpdateProgramAction::class)->execute(
            $record,
            $data,
            (string) auth()->id(),
            (string) $data['reason'],
        );
    }
}
