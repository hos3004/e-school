<?php

declare(strict_types=1);

namespace Modules\Recordings\Presentation\Filament\Resources;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Recordings\Domain\Enums\RecordingStatus;
use Modules\Recordings\Domain\Models\Recording;

/**
 * مورد إدارة التسجيلات في لوحة الإدارة.
 */
final class RecordingResource extends Resource
{
    protected static ?string $model = Recording::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-video-camera';

    protected static ?int $navigationSort = 44;

    public static function getNavigationGroup(): ?string
    {
        return __('recordings::navigation.group');
    }

    public static function getModelLabel(): string
    {
        return __('recordings::navigation.recording.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('recordings::navigation.recording.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('status')
                ->label(__('recordings::fields.status'))
                ->options(collect(RecordingStatus::cases())
                    ->mapWithKeys(fn (RecordingStatus $s): array => [$s->value => $s->label()])
                    ->all())
                ->required(),
            TextInput::make('provider')
                ->label(__('recordings::fields.provider'))
                ->required()
                ->maxLength(50),
            TextInput::make('external_recording_id')
                ->label(__('recordings::fields.external_recording_id'))
                ->required()
                ->maxLength(255),
            TextInput::make('duration_seconds')
                ->label(__('recordings::fields.duration'))
                ->numeric()
                ->minValue(0)
                ->maxValue(86400),
            TextInput::make('size_bytes')
                ->label(__('recordings::fields.size'))
                ->numeric()
                ->minValue(0),
            DateTimePicker::make('available_from')
                ->label(__('recordings::fields.available_from'))
                ->required(),
            DateTimePicker::make('expires_at')
                ->label(__('recordings::fields.expires_at'))
                ->required(),
            Textarea::make('deletion_reason')
                ->label(__('recordings::fields.deletion_reason'))
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label(__('recordings::fields.id'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('session_id')
                    ->label(__('recordings::fields.session'))
                    ->copyable()
                    ->limit(12),
                TextColumn::make('provider')
                    ->label(__('recordings::fields.provider'))
                    ->badge(),
                TextColumn::make('status')
                    ->label(__('recordings::fields.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof RecordingStatus
                        ? $state->label()
                        : (string) $state)
                    ->color(fn ($state): string => $state instanceof RecordingStatus
                        ? $state->color()
                        : 'gray'),
                TextColumn::make('duration_seconds')
                    ->label(__('recordings::fields.duration'))
                    ->formatStateUsing(fn ($state): string => $state === null
                        ? '—'
                        : __('recordings::messages.duration_minutes', ['minutes' => (int) ceil($state / 60)]))
                    ->sortable(),
                TextColumn::make('available_from')
                    ->label(__('recordings::fields.available_from'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('expires_at')
                    ->label(__('recordings::fields.expires_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('deleted_at')
                    ->label(__('recordings::fields.deleted_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('recordings::fields.status'))
                    ->options(collect(RecordingStatus::cases())
                        ->mapWithKeys(fn (RecordingStatus $s): array => [$s->value => $s->label()])
                        ->all()),
            ])
            ->defaultSort('available_from', direction: 'desc');
    }
}
