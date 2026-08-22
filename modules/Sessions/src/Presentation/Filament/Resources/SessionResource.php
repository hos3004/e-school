<?php

declare(strict_types=1);

namespace Modules\Sessions\Presentation\Filament\Resources;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Models\Session;

/**
 * مورد إدارة الحصص في لوحة الإدارة.
 */
final class SessionResource extends Resource
{
    protected static ?string $model = Session::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static \UnitEnum|string|null $navigationGroup = 'التشغيل';

    protected static ?int $navigationSort = 41;

    public static function getModelLabel(): string
    {
        return __('sessions::navigation.session.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('sessions::navigation.session.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('sessions::fields.scheduling'))
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('course_id')
                            ->label(__('sessions::fields.course'))
                            ->required()
                            ->maxLength(26),
                        Select::make('staff_profile_id')
                            ->label(__('sessions::fields.staff_profile'))
                            ->required()
                            ->maxLength(26),
                        DateTimePicker::make('scheduled_start')
                            ->label(__('sessions::fields.scheduled_start'))
                            ->required(),
                        DateTimePicker::make('scheduled_end')
                            ->label(__('sessions::fields.scheduled_end'))
                            ->required(),
                        TextInput::make('session_type')
                            ->label(__('sessions::fields.session_type'))
                            ->required()
                            ->maxLength(50),
                        Select::make('status')
                            ->label(__('sessions::fields.status'))
                            ->options(collect(SessionStatus::cases())
                                ->mapWithKeys(fn (SessionStatus $s): array => [$s->value => $s->label()])
                                ->all())
                            ->required(),
                    ]),
                ]),
            Section::make(__('sessions::fields.details'))
                ->schema([
                    Textarea::make('title')
                        ->label(__('sessions::fields.title'))
                        ->required()
                        ->columnSpanFull(),
                    Textarea::make('notes')
                        ->label(__('sessions::fields.notes'))
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label(__('sessions::fields.id'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('title')
                    ->label(__('sessions::fields.title'))
                    ->formatStateUsing(fn ($state): string => is_array($state)
                        ? ($state[app()->getLocale()] ?? reset($state))
                        : (string) $state)
                    ->limit(40),
                TextColumn::make('status')
                    ->label(__('sessions::fields.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof SessionStatus
                        ? $state->label()
                        : (string) $state)
                    ->color(fn ($state): string => $state instanceof SessionStatus
                        ? $state->color()
                        : 'gray'),
                TextColumn::make('staff_profile_id')
                    ->label(__('sessions::fields.staff_profile'))
                    ->copyable(),
                TextColumn::make('scheduled_start')
                    ->label(__('sessions::fields.scheduled_start'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('scheduled_end')
                    ->label(__('sessions::fields.scheduled_end'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('actual_start')
                    ->label(__('sessions::fields.actual_start'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('sessions::fields.status'))
                    ->options(collect(SessionStatus::cases())
                        ->mapWithKeys(fn (SessionStatus $s): array => [$s->value => $s->label()])
                        ->all()),
            ])
            ->defaultSort('scheduled_start');
    }
}
