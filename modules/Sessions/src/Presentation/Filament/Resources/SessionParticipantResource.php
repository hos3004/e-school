<?php

declare(strict_types=1);

namespace Modules\Sessions\Presentation\Filament\Resources;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Sessions\Domain\Models\SessionParticipant;

/**
 * مورد مشاركو الحصص في لوحة الإدارة.
 */
final class SessionParticipantResource extends Resource
{
    protected static ?string $model = SessionParticipant::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?int $navigationSort = 41;

    public static function getNavigationGroup(): ?string
    {
        return __('sessions::navigation.group');
    }

    public static function getModelLabel(): string
    {
        return __('sessions::navigation.participant.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('sessions::navigation.participant.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('sessions::fields.participation'))
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('session_id')
                            ->label(__('sessions::fields.session'))
                            ->required()
                            ->maxLength(26),
                        TextInput::make('student_profile_id')
                            ->label(__('sessions::fields.student_profile'))
                            ->required()
                            ->maxLength(26),
                        TextInput::make('enrollment_id')
                            ->label(__('sessions::fields.enrollment'))
                            ->required()
                            ->maxLength(26),
                        TextInput::make('join_url_token')
                            ->label(__('sessions::fields.join_url_token'))
                            ->required()
                            ->maxLength(255),
                        DateTimePicker::make('invited_at')
                            ->label(__('sessions::fields.invited_at'))
                            ->required(),
                        TextInput::make('attended_minutes')
                            ->label(__('sessions::fields.attended_minutes'))
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                    ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('session_id')
                    ->label(__('sessions::fields.session'))
                    ->searchable()
                    ->copyable(),
                TextColumn::make('student_profile_id')
                    ->label(__('sessions::fields.student_profile'))
                    ->searchable()
                    ->copyable(),
                TextColumn::make('invited_at')
                    ->label(__('sessions::fields.invited_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('first_joined_at')
                    ->label(__('sessions::fields.first_joined_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('last_left_at')
                    ->label(__('sessions::fields.last_left_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('attended_minutes')
                    ->label(__('sessions::fields.attended_minutes'))
                    ->numeric()
                    ->sortable(),
            ])
            ->defaultSort('created_at');
    }

    public static function getPages(): array
    {
        return [
            'index' => SessionParticipantResource\Pages\ListSessionParticipants::route('/'),
        ];
    }
}

