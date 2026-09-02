<?php

declare(strict_types=1);

namespace Modules\Notifications\Presentation\Filament\Resources\NotificationOutboxResource\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Audit\Domain\Contracts\AuditQueryService;
use Modules\Identity\Domain\Contracts\UserAccountDirectory;
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
                ->color('primary')
                ->requiresConfirmation()
                ->authorize('retry')
                ->visible(fn (NotificationOutbox $record): bool => $record->status === OutboxStatus::Failed)
                ->form([$this->reasonField('retry_reason')])
                ->action(function (NotificationOutbox $record, array $data): void {
                    app(RetryNotificationAction::class)->executeManually(
                        $record,
                        (string) auth()->id(),
                        (string) $data['reason'],
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
                ->form([$this->reasonField('cancel_reason')])
                ->action(function (NotificationOutbox $record, array $data): void {
                    app(CancelNotificationAction::class)->execute(
                        $record,
                        (string) $data['reason'],
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
                        ->label(__('notifications::fields.recipient'))
                        ->formatStateUsing(fn (mixed $state, NotificationOutbox $record): string => $this->accountName(
                            (string) $record->organization_id,
                            (string) $state,
                        )),
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
                            ? (string) ($state[app()->getLocale()] ?? reset($state))
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

            Section::make(__('notifications::fields.audit_history'))
                ->schema([
                    RepeatableEntry::make('audit_entries')
                        ->hiddenLabel()
                        ->placeholder(__('notifications::messages.no_audit_entries'))
                        ->getStateUsing(fn (NotificationOutbox $record): array => $this->auditRows($record))
                        ->schema([
                            TextEntry::make('action')->label(__('notifications::fields.action')),
                            TextEntry::make('actor')->label(__('notifications::fields.actor')),
                            TextEntry::make('reason')->label(__('notifications::fields.reason')),
                            TextEntry::make('created_at')->label(__('notifications::fields.created_at'))->dateTime(),
                        ])->columns(4),
                ]),
        ]);
    }

    private function reasonField(string $label): Textarea
    {
        return Textarea::make('reason')
            ->label(__('notifications::fields.'.$label))
            ->required()
            ->minLength(3)
            ->maxLength(1000);
    }

    /** @return list<array<string, mixed>> */
    private function auditRows(NotificationOutbox $record): array
    {
        $entries = app(AuditQueryService::class)->paginateForOrganization(
            (string) $record->organization_id,
            ['auditable_type' => 'notification_outbox', 'auditable_id' => (string) $record->getKey()],
            50,
        )->items();
        $actorIds = array_values(array_unique(array_filter(array_map(
            static fn (mixed $entry): ?string => $entry->actorId,
            $entries,
        ))));
        $actors = app(UserAccountDirectory::class)->findMany((string) $record->organization_id, $actorIds);

        return array_values(array_map(static fn (mixed $entry): array => [
            'action' => (string) __('notifications::audit_actions.'.str_replace('.', '_', $entry->action)),
            'actor' => $entry->actorId === null
                ? (string) __('notifications::messages.system_actor')
                : (isset($actors[$entry->actorId])
                    ? $actors[$entry->actorId]->name
                    : (string) __('notifications::messages.system_actor')),
            'reason' => $entry->reason,
            'created_at' => $entry->createdAt,
        ], $entries));
    }

    private function accountName(string $organizationId, string $userId): string
    {
        $account = app(UserAccountDirectory::class)->find($organizationId, $userId);

        return $account === null
            ? (string) __('notifications::messages.not_available')
            : $account->name;
    }
}
