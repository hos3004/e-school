<?php

declare(strict_types=1);

namespace Modules\Sessions\Presentation\Filament\Resources\SessionResource\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Modules\Sessions\Presentation\Filament\Resources\SessionResource;

final class ListSessions extends ListRecords
{
    protected static string $resource = SessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('calendar')
                ->label(__('sessions::actions.open_calendar'))
                ->icon('heroicon-o-calendar-days')
                ->url(SessionResource::getUrl('calendar')),
        ];
    }
}
