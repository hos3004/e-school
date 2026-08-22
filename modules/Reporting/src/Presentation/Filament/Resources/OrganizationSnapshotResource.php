<?php

declare(strict_types=1);

namespace Modules\Reporting\Presentation\Filament\Resources;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Reporting\Domain\Enums\SnapshotType;
use Modules\Reporting\Domain\Models\OrganizationSnapshot;

/**
 * مورد اللقطات التنظيمية — قراءة فقط، والبناء عبر الإجراء المجدول.
 */
final class OrganizationSnapshotResource extends Resource
{
    protected static ?string $model = OrganizationSnapshot::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static \UnitEnum|string|null $navigationGroup = 'التقارير';

    protected static ?int $navigationSort = 90;

    public static function getModelLabel(): string
    {
        return __('reporting::navigation.snapshot.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('reporting::navigation.snapshot.plural');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false; // اللقطات append/upsert عبر الإجراء — لا تعديل يدوي.
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('reporting::sections.snapshot'))
                ->schema([
                    Grid::make(2)->schema([
                        DatePicker::make('snapshot_date')
                            ->label(__('reporting::fields.snapshot_date'))
                            ->required(),
                        Select::make('period_type')
                            ->label(__('reporting::fields.period_type'))
                            ->options(collect(SnapshotType::cases())
                                ->mapWithKeys(fn (SnapshotType $type): array => [$type->value => $type->label()])
                                ->all())
                            ->required(),
                        TextInput::make('students_active')
                            ->label(__('reporting::fields.students_active'))
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('students_frozen')
                            ->label(__('reporting::fields.students_frozen'))
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('teachers_active')
                            ->label(__('reporting::fields.teachers_active'))
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('sessions_held')
                            ->label(__('reporting::fields.sessions_held'))
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('sessions_cancelled')
                            ->label(__('reporting::fields.sessions_cancelled'))
                            ->numeric()
                            ->minValue(0),
                    ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label(__('reporting::fields.id'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('snapshot_date')
                    ->label(__('reporting::fields.snapshot_date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('period_type')
                    ->label(__('reporting::fields.period_type'))
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof SnapshotType
                        ? $state->label()
                        : (string) $state),
                TextColumn::make('students_active')
                    ->label(__('reporting::fields.students_active'))
                    ->sortable()
                    ->alignEnd(),
                TextColumn::make('students_frozen')
                    ->label(__('reporting::fields.students_frozen'))
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(),
                TextColumn::make('teachers_active')
                    ->label(__('reporting::fields.teachers_active'))
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(),
                TextColumn::make('sessions_held')
                    ->label(__('reporting::fields.sessions_held'))
                    ->sortable()
                    ->alignEnd(),
                TextColumn::make('sessions_cancelled')
                    ->label(__('reporting::fields.sessions_cancelled'))
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(),
                TextColumn::make('attendance_rate_bp')
                    ->label(__('reporting::fields.attendance_rate'))
                    ->formatStateUsing(fn ($state): string => number_format(((int) $state) / 100, 1).'%')
                    ->sortable()
                    ->alignEnd(),
            ])
            ->filters([
                SelectFilter::make('period_type')
                    ->label(__('reporting::fields.period_type'))
                    ->options(collect(SnapshotType::cases())
                        ->mapWithKeys(fn (SnapshotType $type): array => [$type->value => $type->label()])
                        ->all()),
            ])
            ->defaultSort('snapshot_date', direction: 'desc');
    }
}
