<?php

declare(strict_types=1);

namespace Modules\Scheduling\Presentation\Filament\Resources\ScheduleResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Scheduling\Presentation\Filament\Resources\ScheduleResource;

final class ListSchedules extends ListRecords
{
    protected static string $resource = ScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('individual_quran_placement')
                ->label(__('scheduling::filament.schedule.actions.individual_quran_placement'))
                ->icon('heroicon-o-users')
                ->visible(static function (): bool {
                    $user = auth()->user();

                    return $user !== null
                        && (bool) $user->can('schedule.manage')
                        && (bool) $user->can('student.view.any');
                })
                ->url(route('filament.admin.resources.students.individual-quran')),
            CreateAction::make(),
        ];
    }
}
