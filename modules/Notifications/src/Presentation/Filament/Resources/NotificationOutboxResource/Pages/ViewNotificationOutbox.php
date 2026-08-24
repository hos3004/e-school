<?php

declare(strict_types=1);

namespace Modules\Notifications\Presentation\Filament\Resources\NotificationOutboxResource\Pages;

use Filament\Actions\Action;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Notifications\Application\Actions\CancelNotificationAction;
use Modules\Notifications\Application\Actions\RetryNotificationAction;
use Modules\Notifications\Domain\Enums\Channel;
use Modules\Notifications\Domain\Enums\OutboxStatus;
use Modules\Notifications\Domain\Models\NotificationDeliveryAttempt;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Modules\Notifications\Presentation\Filament\Resources\NotificationOutboxResource;

/**
 * عرض تفاصيل رسالة في صندوق الإرسال مع سجل محاولات التسليم.
 */
final class ViewNotificationOutbox extends ViewRecord
{
    protected static string $resource = NotificationOutboxResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('retry')
                ->label(__('notifications::actions.manual_retry'))
                ->icon('heroicon-m-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->authorize('retry')
                ->visible(fn (NotificationOutbox $record): bool => $record->status === OutboxStatus::Failed)
                ->action(function (NotificationOutbox $record): void {
                    app(RetryNotificationAction::class)->executeManually(
                        $record,
                        (string) auth()->id(),
                    );

                    Notification::make()
                        ->title(__('notifications::messages.manual_retry_queued'))
                        ->success()
                        ->send();
                }),

            Action::make('cancel')
                ->label(__('notifications::actions.cancel'))
                ->icon('heroicon-m-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->authorize('cancel')
                ->visible(fn (NotificationOutbox $record): bool => $record->status === OutboxStatus::Queued)
                ->action(function (NotificationOutbox $record): void {
                    app(CancelNotificationAction::class)->execute(
                        $record,
                        __('notifications::fields.reason'),
                        (string) auth()->id(),
                    );

                    Notification::make()
                        ->title(__('notifications::messages.cancelled'))
                        ->success()
                        ->send();
                }),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('notifications::fields.routing'))
                ->schema([
                    TextEntry::make('id')
                        ->label(__('notifications::fields.id'))
                        ->copyable(),
                    TextEntry::make('user_id')
                        ->label(__('notifications::fields.user_id'))
                        ->copyable(),
                    TextEntry::make('channel')
                        ->label(__('notifications::fields.channel'))
                        ->badge()
                        ->formatStateUsing(fn ($state): string => $state instanceof Channel
                            ? $state->label()
                            : Channel::tryFrom((string) $state)?->label() ?? (string) $state),
                    TextEntry::make('category')
                        ->label(__('notifications::fields.category'))
                        ->badge(),
                ])->columns(2),

            Section::make(__('notifications::fields.content'))
                ->schema([
                    TextEntry::make('subject')
                        ->label(__('notifications::fields.subject'))
                        ->formatStateUsing(fn ($state): string => is_array($state)
                            ? ($state[app()->getLocale()] ?? reset($state))
                            : (string) $state),
                    TextEntry::make('body')
                        ->label(__('notifications::fields.body'))
                        ->formatStateUsing(fn ($state): string => is_array($state)
                            ? json_encode($state, JSON_UNESCAPED_UNICODE)
                            : (string) $state),
                ]),

            Section::make(__('notifications::fields.dispatching'))
                ->schema([
                    TextEntry::make('status')
                        ->label(__('notifications::fields.status'))
                        ->badge()
                        ->formatStateUsing(fn ($state): string => $state instanceof OutboxStatus
                            ? $state->label()
                            : OutboxStatus::tryFrom((string) $state)?->label() ?? (string) $state)
                        ->color(fn ($state): string => $state instanceof OutboxStatus
                            ? $state->color()
                            : 'gray'),
                    TextEntry::make('attempts')
                        ->label(__('notifications::fields.attempts')),
                    TextEntry::make('external_message_id')
                        ->label(__('notifications::fields.external_message_id'))
                        ->copyable()
                        ->placeholder(__('notifications::fields.external_message_id')),
                    TextEntry::make('last_error')
                        ->label(__('notifications::fields.last_error'))
                        ->placeholder(__('notifications::fields.last_error')),
                ])->columns(2),

            Section::make(__('notifications::fields.attempts_history'))
                ->schema([
                    RepeatableEntry::make('delivery_attempts')
                        ->label(__('notifications::fields.attempts_history'))
                        ->getStateUsing(fn (NotificationOutbox $record) => NotificationDeliveryAttempt::query()
                            ->forOutbox($record->id)
                            ->orderBy('attempt_number')
                            ->get())
                        ->schema([
                            TextEntry::make('attempt_number')
                                ->label(__('notifications::fields.attempt_number')),
                            TextEntry::make('succeeded')
                                ->label(__('notifications::fields.result'))
                                ->badge()
                                ->formatStateUsing(fn (bool $state): string => $state
                                    ? __('notifications::fields.succeeded')
                                    : __('notifications::status.failed'))
                                ->color(fn (bool $state): string => $state ? 'success' : 'danger'),
                            TextEntry::make('attempted_at')
                                ->label(__('notifications::fields.attempted_at'))
                                ->dateTime(),
                            TextEntry::make('error')
                                ->label(__('notifications::fields.error'))
                                ->placeholder(__('notifications::fields.error')),
                        ])->columns(4),
                ]),
        ]);
    }
}
