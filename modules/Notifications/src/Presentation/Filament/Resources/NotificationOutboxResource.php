<?php

declare(strict_types=1);

namespace Modules\Notifications\Presentation\Filament\Resources;

use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Identity\Domain\Contracts\UserAccountDirectory;
use Modules\Notifications\Application\Actions\CancelNotificationAction;
use Modules\Notifications\Application\Actions\MarkNotificationAsReadAction;
use Modules\Notifications\Application\Actions\RetryNotificationAction;
use Modules\Notifications\Domain\Enums\Channel;
use Modules\Notifications\Domain\Enums\OutboxStatus;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Modules\Notifications\Presentation\Filament\Resources\NotificationOutboxResource\Pages;
use Shared\Filament\RecordOriginGuide;

/**
 * مورد صندوق الإرسال في لوحة الإدارة — قراءة وتشغيل (إلغاء/إعادة)،
 * لا تحرير يدوي لنصوص الرسائل.
 */
final class NotificationOutboxResource extends Resource
{
    protected static ?string $model = NotificationOutbox::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?int $navigationSort = 71;

    protected static ?string $recordTitleAttribute = 'id';

    public static function getModelLabel(): string
    {
        return __('notifications::navigation.outbox.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('notifications::navigation.outbox.plural');
    }

    public static function getNavigationGroup(): string
    {
        return __('notifications::navigation.group');
    }

    /** عدّاد جرس المستخدم الحالي؛ البث اللحظي له polling fallback عبر الـAPI. */
    public static function getNavigationBadge(): ?string
    {
        $userId = (string) auth()->id();
        $organizationId = (string) data_get(auth()->user(), 'organization_id');

        if ($userId === '' || $organizationId === '') {
            return null;
        }

        return (string) NotificationOutbox::query()
            ->forOrganization($organizationId)
            ->forUser($userId)
            ->where('channel', Channel::InApp->value)
            ->where('status', OutboxStatus::Sent)
            ->whereNull('read_at')
            ->count();
    }

    public static function canCreate(): bool
    {
        // الإدخال الوحيد للصندوق هو QueueNotificationAction — لا إنشاء يدوي.
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('notifications::fields.routing'))
                ->schema([
                    TextInput::make('user_id')
                        ->label(__('notifications::fields.user_id'))
                        ->disabled()
                        ->dehydrated(false),
                    Select::make('channel')
                        ->label(__('notifications::fields.channel'))
                        ->options(collect(Channel::cases())
                            ->mapWithKeys(fn (Channel $c): array => [$c->value => $c->label()])
                            ->all())
                        ->disabled()
                        ->dehydrated(false),
                    TextInput::make('category')
                        ->label(__('notifications::fields.category'))
                        ->disabled()
                        ->dehydrated(false),
                    TextInput::make('locale')
                        ->label(__('notifications::fields.locale'))
                        ->disabled()
                        ->dehydrated(false),
                ])
                ->columns(2)
                ->columnSpanFull(),
            Section::make(__('notifications::fields.content'))
                ->schema([
                    Textarea::make('subject')
                        ->label(__('notifications::fields.subject'))
                        ->formatStateUsing(fn ($state): string => is_array($state)
                            ? ($state[app()->getLocale()] ?? reset($state))
                            : (string) $state)
                        ->disabled()
                        ->dehydrated(false)
                        ->columnSpanFull(),
                    Textarea::make('body')
                        ->label(__('notifications::fields.body'))
                        ->formatStateUsing(fn ($state): string => is_array($state)
                            ? json_encode($state, JSON_UNESCAPED_UNICODE)
                            : (string) $state)
                        ->disabled()
                        ->dehydrated(false)
                        ->rows(4)
                        ->columnSpanFull(),
                ]),
            Section::make(__('notifications::fields.dispatching'))
                ->schema([
                    Select::make('status')
                        ->label(__('notifications::fields.status'))
                        ->options(collect(OutboxStatus::cases())
                            ->mapWithKeys(fn (OutboxStatus $s): array => [$s->value => $s->label()])
                            ->all())
                        ->disabled()
                        ->dehydrated(false),
                    TextInput::make('attempts')
                        ->label(__('notifications::fields.attempts'))
                        ->numeric()
                        ->disabled()
                        ->dehydrated(false),
                    DateTimePicker::make('scheduled_for')
                        ->label(__('notifications::fields.scheduled_for'))
                        ->disabled()
                        ->dehydrated(false),
                    DateTimePicker::make('sent_at')
                        ->label(__('notifications::fields.sent_at'))
                        ->disabled()
                        ->dehydrated(false),
                    TextInput::make('external_message_id')
                        ->label(__('notifications::fields.external_message_id'))
                        ->disabled()
                        ->dehydrated(false),
                    TextInput::make('provider_status')
                        ->label(__('notifications::fields.provider_status'))
                        ->disabled()
                        ->dehydrated(false),
                    DateTimePicker::make('read_at')
                        ->label(__('notifications::fields.read_at'))
                        ->disabled()
                        ->dehydrated(false),
                    TextInput::make('last_manual_retry_by')
                        ->label(__('notifications::fields.last_manual_retry_by'))
                        ->disabled()
                        ->dehydrated(false),
                    DateTimePicker::make('last_manual_retry_at')
                        ->label(__('notifications::fields.last_manual_retry_at'))
                        ->disabled()
                        ->dehydrated(false),
                    Textarea::make('failure_reason')
                        ->label(__('notifications::fields.failure_reason'))
                        ->disabled()
                        ->dehydrated(false)
                        ->columnSpanFull(),
                    Textarea::make('last_error')
                        ->label(__('notifications::fields.last_error'))
                        ->disabled()
                        ->dehydrated(false)
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return RecordOriginGuide::for(
            $table,
            'notifications::origin.outbox',
            'heroicon-o-bell-alert',
        )
            ->columns([
                TextColumn::make('id')
                    ->label(__('notifications::fields.id'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('user_id')
                    ->label(__('notifications::fields.recipient'))
                    ->formatStateUsing(fn (mixed $state, NotificationOutbox $record): string => self::accountName(
                        (string) $record->organization_id,
                        (string) $state,
                    )),
                TextColumn::make('category')
                    ->label(__('notifications::fields.category'))
                    // كان يظهر المفتاح الخام — يُعرض الاسم المترجم للفئة.
                    ->formatStateUsing(fn ($state): string => (string) (__('notifications::categories.'.(string) $state))
                        ?: (string) $state)
                    ->badge()
                    ->sortable(),
                TextColumn::make('channel')
                    ->label(__('notifications::fields.channel'))
                    ->formatStateUsing(fn ($state): string => $state instanceof Channel
                        ? $state->label()
                        : Channel::tryFrom((string) $state)?->label() ?? (string) $state)
                    ->badge()
                    ->sortable(),
                TextColumn::make('locale')
                    ->label(__('notifications::fields.locale'))
                    ->toggleable(),
                TextColumn::make('event_name')
                    ->label(__('notifications::fields.event_name'))
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label(__('notifications::fields.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof OutboxStatus
                        ? $state->label()
                        : OutboxStatus::tryFrom((string) $state)?->label() ?? (string) $state)
                    ->color(fn ($state): string => $state instanceof OutboxStatus
                        ? $state->color()
                        : 'gray'),
                TextColumn::make('attempts')
                    ->label(__('notifications::fields.attempts'))
                    ->alignCenter(),
                TextColumn::make('scheduled_for')
                    ->label(__('notifications::fields.scheduled_for'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('sent_at')
                    ->label(__('notifications::fields.sent_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('external_message_id')
                    ->label(__('notifications::fields.external_message_id'))
                    ->limit(24)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('failure_reason')
                    ->label(__('notifications::fields.failure_reason'))
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('read_at')
                    ->label(__('notifications::fields.read_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('last_manual_retry_by')
                    ->label(__('notifications::fields.last_manual_retry_by'))
                    ->formatStateUsing(fn (mixed $state, NotificationOutbox $record): string => $state === null
                        ? (string) __('notifications::messages.not_available')
                        : self::accountName((string) $record->organization_id, (string) $state))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('last_manual_retry_at')
                    ->label(__('notifications::fields.last_manual_retry_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label(__('notifications::fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('notifications::fields.status'))
                    ->options(collect(OutboxStatus::cases())
                        ->mapWithKeys(fn (OutboxStatus $s): array => [$s->value => $s->label()])
                        ->all()),
                SelectFilter::make('channel')
                    ->label(__('notifications::fields.channel'))
                    ->options(collect(Channel::cases())
                        ->mapWithKeys(fn (Channel $c): array => [$c->value => $c->label()])
                        ->all()),
                TernaryFilter::make('read_at')
                    ->label(__('notifications::fields.read_status'))
                    ->nullable(),
            ])
            ->recordActions([
                Action::make('view')
                    ->label(__('notifications::actions.view'))
                    ->icon('heroicon-m-eye')
                    ->url(fn (NotificationOutbox $record): string => self::getUrl('view', ['record' => $record])),
                self::markAsReadAction(),
                self::manualRetryAction(),
                self::cancelAction(),
            ])
            ->defaultSort('scheduled_for');
    }

    /**
     * نطاق مؤسسة المشرف دائمًا؛ غياب المؤسسة يغلق الاستعلام بدل كشف الجميع.
     *
     * @return Builder<NotificationOutbox>
     */
    public static function getEloquentQuery(): Builder
    {
        $organizationId = (string) data_get(auth()->user(), 'organization_id');
        /** @var Builder<NotificationOutbox> $query */
        $query = parent::getEloquentQuery();

        return $organizationId === ''
            ? $query->whereRaw('1 = 0')
            : $query->forOrganization($organizationId);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNotificationOutboxes::route('/'),
            'view' => Pages\ViewNotificationOutbox::route('/{record}'),
        ];
    }

    private static function markAsReadAction(): Action
    {
        return Action::make('mark_as_read')
            ->label(__('notifications::actions.mark_as_read'))
            ->icon('heroicon-m-check')
            ->authorize('markAsRead')
            ->visible(fn (NotificationOutbox $record): bool => $record->read_at === null
                && $record->channel === Channel::InApp->value
                && $record->status === OutboxStatus::Sent)
            ->action(function (NotificationOutbox $record): void {
                app(MarkNotificationAsReadAction::class)->execute(
                    $record,
                    (string) auth()->id(),
                    (string) data_get(auth()->user(), 'organization_id'),
                );

                Notification::make()
                    ->title(__('notifications::messages.marked_as_read'))
                    ->success()
                    ->send();
            });
    }

    private static function manualRetryAction(): Action
    {
        return Action::make('manual_retry')
            ->label(__('notifications::actions.manual_retry'))
            ->icon('heroicon-m-arrow-path')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading(__('notifications::actions.manual_retry_heading'))
            ->modalDescription(__('notifications::actions.manual_retry_description'))
            ->authorize('retry')
            ->visible(fn (NotificationOutbox $record): bool => $record->status === OutboxStatus::Failed)
            ->form([
                Textarea::make('reason')
                    ->label(__('notifications::fields.retry_reason'))
                    ->required()
                    ->minLength(3)
                    ->maxLength(1000),
            ])
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
            });
    }

    private static function cancelAction(): Action
    {
        return Action::make('cancel')
            ->label(__('notifications::actions.cancel'))
            ->icon('heroicon-m-x-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(__('notifications::actions.cancel_heading'))
            ->modalDescription(__('notifications::actions.cancel_description'))
            ->form([
                TextInput::make('reason')
                    ->label(__('notifications::fields.cancel_reason'))
                    ->required(),
            ])
            ->authorize('cancel')
            ->visible(fn (NotificationOutbox $record): bool => $record->status === OutboxStatus::Queued)
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
            });
    }

    private static function accountName(string $organizationId, string $userId): string
    {
        $account = app(UserAccountDirectory::class)->find($organizationId, $userId);

        return $account === null
            ? (string) __('notifications::messages.not_available')
            : $account->name;
    }
}
