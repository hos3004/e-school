<?php

declare(strict_types=1);

namespace Modules\Messaging\Presentation\Filament\Resources;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Modules\Messaging\Domain\Enums\ConversationType;
use Modules\Messaging\Domain\Models\Conversation;

/**
 * مورد إدارة المحادثات في لوحة الإدارة.
 */
final class ConversationResource extends Resource
{
    protected static ?string $model = Conversation::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static \UnitEnum|string|null $navigationGroup = 'التواصل';

    protected static ?int $navigationSort = 70;

    public static function getModelLabel(): string
    {
        return __('messaging::navigation.conversation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('messaging::navigation.conversation.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('messaging::fields.conversation'))
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('subject')
                            ->label(__('messaging::fields.subject'))
                            ->required()
                            ->maxLength((int) config('messaging.limits.conversation_subject_max')),
                        Select::make('type')
                            ->label(__('messaging::fields.type'))
                            ->options(collect(ConversationType::cases())
                                ->mapWithKeys(fn (ConversationType $t): array => [$t->value => $t->label()])
                                ->all())
                            ->required(),
                        Toggle::make('is_moderated')
                            ->label(__('messaging::fields.is_moderated'))
                            ->default(true),
                        DateTimePicker::make('last_message_at')
                            ->label(__('messaging::fields.last_message_at'))
                            ->disabled(),
                    ]),
                ]),
            Section::make(__('messaging::fields.related_record'))
                ->schema([
                    TextInput::make('related_type')
                        ->label(__('messaging::fields.related_type'))
                        ->maxLength(64),
                    TextInput::make('related_id')
                        ->label(__('messaging::fields.related_id'))
                        ->maxLength(26),
                ])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label(__('messaging::fields.id'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('subject')
                    ->label(__('messaging::fields.subject'))
                    ->searchable()
                    ->limit(40),
                TextColumn::make('type')
                    ->label(__('messaging::fields.type'))
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof ConversationType
                        ? $state->label()
                        : (string) $state),
                IconColumn::make('is_moderated')
                    ->label(__('messaging::fields.is_moderated'))
                    ->boolean(),
                TextColumn::make('created_by')
                    ->label(__('messaging::fields.created_by'))
                    ->copyable()
                    ->toggleable(),
                TextColumn::make('last_message_at')
                    ->label(__('messaging::fields.last_message_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('messaging::fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('messaging::fields.type'))
                    ->options(collect(ConversationType::cases())
                        ->mapWithKeys(fn (ConversationType $t): array => [$t->value => $t->label()])
                        ->all()),
                TernaryFilter::make('is_moderated')
                    ->label(__('messaging::fields.is_moderated')),
            ])
            ->defaultSort('last_message_at', 'desc');
    }
}
