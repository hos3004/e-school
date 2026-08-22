<?php

declare(strict_types=1);

namespace Modules\Notifications\Presentation\Filament\Resources;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Notifications\Domain\Enums\Channel;
use Modules\Notifications\Domain\Enums\OutboxStatus;
use Modules\Notifications\Domain\Models\NotificationOutbox;

/**
 * مورد صندوق الإرسال في لوحة الإدارة — قراءة وتشغيل (إلغاء/إعادة)،
 * لا تحرير يدوي لنصوص الرسائل.
 */
final class NotificationOutboxResource extends Resource
{
    protected static ?string $model = NotificationOutbox::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bell-alert';

    protected static \UnitEnum|string|null $navigationGroup = 'التواصل';

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
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label(__('notifications::fields.id'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('user_id')
                    ->label(__('notifications::fields.user_id'))
                    ->searchable(),
                TextColumn::make('category')
                    ->label(__('notifications::fields.category'))
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
            ])
            ->defaultSort('scheduled_for');
    }
}
