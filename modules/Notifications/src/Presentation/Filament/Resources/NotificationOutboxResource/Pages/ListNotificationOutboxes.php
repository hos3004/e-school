<?php

declare(strict_types=1);

namespace Modules\Notifications\Presentation\Filament\Resources\NotificationOutboxResource\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Gate;
use Modules\Notifications\Application\Actions\MarkAllNotificationsAsReadAction;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Modules\Notifications\Presentation\Filament\Resources\NotificationOutboxResource;

/** قائمة صندوق الإرسال مع إجراء جرس المستخدم الحالي دون قالب مخصص. */
final class ListNotificationOutboxes extends ListRecords
{
    protected static string $resource = NotificationOutboxResource::class;

    /** @return list<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('mark_all_as_read')
                ->label(__('notifications::actions.mark_all_as_read'))
                ->icon('heroicon-m-check-circle')
                ->authorize(fn (): bool => Gate::allows('markAllAsRead', NotificationOutbox::class))
                ->action(function (): void {
                    $markedCount = app(MarkAllNotificationsAsReadAction::class)->execute(
                        (string) auth()->id(),
                        (string) data_get(auth()->user(), 'organization_id'),
                    );

                    Notification::make()
                        ->title(__('notifications::messages.marked_all_as_read_count', [
                            'count' => $markedCount,
                        ]))
                        ->success()
                        ->send();
                }),
        ];
    }
}
