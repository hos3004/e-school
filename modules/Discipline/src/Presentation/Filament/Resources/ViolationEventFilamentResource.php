<?php

declare(strict_types=1);

namespace Modules\Discipline\Presentation\Filament\Resources;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Modules\Discipline\Domain\Enums\ViolationType;
use Modules\Discipline\Domain\Models\ViolationEvent;
use Modules\Discipline\Presentation\Filament\Resources\ViolationEventFilamentResource\Pages;

/**
 * مورد أحداث المخالفات في لوحة التحكم — قراءة فقط مع فعل عفو موثّق.
 * كل النصوص عبر ملفات الترجمة، وكل الأنواع من الـ Enum.
 */
final class ViolationEventFilamentResource extends Resource
{
    protected static ?string $model = ViolationEvent::class;

    protected static ?string $slug = 'discipline-violations';

    protected static \UnitEnum|string|null $navigationGroup = 'الانضباط';

    protected static ?int $navigationSort = 60;

    public static function getNavigationLabel(): string
    {
        return __('discipline::filament.violations.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('discipline::filament.violations.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('discipline::filament.violations.plural_model_label');
    }

    public static function canCreate(): bool
    {
        return false; // التسجيل يمر حصرًا عبر RecordViolationAction.
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('organization_id')
                ->label(__('discipline::attributes.organization_id'))
                ->disabled()
                ->dehydrated(false),

            TextInput::make('enrollment_id')
                ->label(__('discipline::attributes.enrollment_id'))
                ->disabled()
                ->dehydrated(false),

            TextInput::make('student_profile_id')
                ->label(__('discipline::attributes.student_profile_id'))
                ->disabled()
                ->dehydrated(false),

            Select::make('type')
                ->label(__('discipline::attributes.type'))
                ->options(collect(ViolationType::cases())
                    ->mapWithKeys(fn (ViolationType $t): array => [$t->value => $t->label()])
                    ->all())
                ->disabled()
                ->dehydrated(false),

            DateTimePicker::make('occurred_at')
                ->label(__('discipline::attributes.occurred_at'))
                ->disabled()
                ->dehydrated(false),

            Textarea::make('waiver_reason')
                ->label(__('discipline::attributes.waiver_reason'))
                ->disabled()
                ->dehydrated(false)
                ->columnSpanFull(),
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

                TextColumn::make('type')
                    ->label(__('discipline::attributes.type'))
                    ->badge()
                    ->formatStateUsing(fn (?ViolationType $state): ?string => $state?->label())
                    ->color(fn (?ViolationType $state): string => $state?->color() ?? 'gray')
                    ->sortable(),

                TextColumn::make('occurred_at')
                    ->label(__('discipline::attributes.occurred_at'))
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('window_key')
                    ->label(__('discipline::filament.window_key'))
                    ->badge()
                    ->toggleable(),

                IconColumn::make('is_countable')
                    ->label(__('discipline::filament.is_countable'))
                    ->boolean()
                    ->toggleable(),

                IconColumn::make('waived_at')
                    ->label(__('discipline::filament.waived'))
                    ->boolean()
                    ->state(fn (ViolationEvent $record): bool => $record->isWaived())
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('discipline::attributes.type'))
                    ->options(collect(ViolationType::cases())
                        ->mapWithKeys(fn (ViolationType $t): array => [$t->value => $t->label()])
                        ->all()),

                TernaryFilter::make('waived_at')
                    ->label(__('discipline::filament.waived'))
                    ->nullable(),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListViolationEvents::route('/'),
            'view' => Pages\ViewViolationEvent::route('/{record}'),
        ];
    }
}
