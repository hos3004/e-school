<?php

declare(strict_types=1);

namespace Modules\Sessions\Presentation\Filament\Resources\SessionResource\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Artisan;
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
            Action::make('dispatch_reminders')
                ->label(__('sessions::actions.dispatch_reminders'))
                ->icon('heroicon-o-paper-airplane')
                ->requiresConfirmation()
                ->modalHeading(__('sessions::actions.dispatch_reminders_heading'))
                ->modalDescription(__('sessions::actions.dispatch_reminders_description'))
                ->visible(static fn (): bool => (bool) auth()->user()?->can('session.create'))
                ->action(function (): void {
                    Artisan::call('sessions:dispatch-reminders');

                    Notification::make()
                        ->title(__('sessions::messages.reminders_dispatched_manually'))
                        ->success()
                        ->send();
                }),
        ];
    }
}
