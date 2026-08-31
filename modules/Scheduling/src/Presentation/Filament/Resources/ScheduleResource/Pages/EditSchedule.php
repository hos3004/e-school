<?php

declare(strict_types=1);

namespace Modules\Scheduling\Presentation\Filament\Resources\ScheduleResource\Pages;

use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Scheduling\Application\Actions\UpdateScheduleAction;
use Modules\Scheduling\Domain\Models\Schedule;
use Modules\Scheduling\Domain\ValueObjects\WeeklyRecurrence;
use Modules\Scheduling\Presentation\Filament\Resources\ScheduleResource;

final class EditSchedule extends EditRecord
{
    protected static string $resource = ScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [ViewAction::make()];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $rule = WeeklyRecurrence::fromRRule((string) $data['rrule']);
        $data['target_type'] = empty($data['group_id']) ? 'student' : 'group';
        $data['weekdays'] = $rule->weekdays;
        $data['interval_weeks'] = $rule->intervalWeeks;

        return $data;
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        abort_unless($record instanceof Schedule, 404);

        return app(UpdateScheduleAction::class)->execute(
            $record,
            $data,
            (string) auth()->id(),
            (string) $data['reason'],
        );
    }
}
