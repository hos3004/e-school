<?php

declare(strict_types=1);

namespace Modules\Students\Presentation\Filament\Resources\StudentProfileResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Students\Presentation\Filament\Resources\StudentProfileResource;

final class ListStudentProfiles extends ListRecords
{
    protected static string $resource = StudentProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('individual_quran')
                ->label(__('students::admin.individual_quran.open_page'))
                ->icon('heroicon-o-book-open')
                ->color('primary')
                ->url(StudentProfileResource::getUrl('individual-quran'))
                ->visible(static fn (): bool => (bool) auth()->user()?->can('schedule.manage')),
            CreateAction::make()
                ->label(__('students::admin.onboarding.action')),
        ];
    }
}
