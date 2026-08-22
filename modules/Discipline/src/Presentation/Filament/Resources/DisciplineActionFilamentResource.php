<?php

declare(strict_types=1);

namespace Modules\Discipline\Presentation\Filament\Resources;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Discipline\Domain\Enums\DisciplineActionType;
use Modules\Discipline\Domain\Models\DisciplineAction;
use Modules\Discipline\Presentation\Filament\Resources\DisciplineActionFilamentResource\Pages;

/**
 * مورد قيود إجراءات الانضباط — سجل تاريخي للقراءة فقط.
 */
final class DisciplineActionFilamentResource extends Resource
{
    protected static ?string $model = DisciplineAction::class;

    protected static ?string $slug = 'discipline-actions';

    protected static \UnitEnum|string|null $navigationGroup = 'الانضباط';

    protected static ?int $navigationSort = 60;

    public static function getNavigationLabel(): string
    {
        return __('discipline::filament.actions.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('discipline::filament.actions.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('discipline::filament.actions.plural_model_label');
    }

    public static function canCreate(): bool
    {
        return false; // الإجراءات تُنشأ من محرّك التصعيد فقط.
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('enrollment_id')
                ->label(__('discipline::attributes.enrollment_id'))
                ->disabled()
                ->dehydrated(false),

            Select::make('action')
                ->label(__('discipline::attributes.action'))
                ->options(collect(DisciplineActionType::cases())
                    ->mapWithKeys(fn (DisciplineActionType $a): array => [$a->value => $a->label()])
                    ->all())
                ->disabled()
                ->dehydrated(false),

            TextInput::make('threshold_reached')
                ->label(__('discipline::filament.threshold_reached'))
                ->numeric()
                ->disabled()
                ->dehydrated(false),

            DateTimePicker::make('applied_at')
                ->label(__('discipline::filament.applied_at'))
                ->disabled()
                ->dehydrated(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('enrollment_id')
                    ->label(__('discipline::attributes.enrollment_id'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('action')
                    ->label(__('discipline::attributes.action'))
                    ->badge()
                    ->formatStateUsing(fn (?DisciplineActionType $state): ?string => $state?->label())
                    ->sortable(),

                TextColumn::make('threshold_reached')
                    ->label(__('discipline::filament.threshold_reached'))
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('window_key')
                    ->label(__('discipline::filament.window_key'))
                    ->badge()
                    ->toggleable(),

                IconColumn::make('is_automatic')
                    ->label(__('discipline::filament.is_automatic'))
                    ->boolean()
                    ->toggleable(),

                TextColumn::make('applied_at')
                    ->label(__('discipline::filament.applied_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->label(__('discipline::attributes.action'))
                    ->options(collect(DisciplineActionType::cases())
                        ->mapWithKeys(fn (DisciplineActionType $a): array => [$a->value => $a->label()])
                        ->all()),

                SelectFilter::make('window_key')
                    ->label(__('discipline::filament.window_key')),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDisciplineActions::route('/'),
            'view' => Pages\ViewDisciplineAction::route('/{record}'),
        ];
    }
}
