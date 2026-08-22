<?php

declare(strict_types=1);

namespace Modules\Organization\Presentation\Filament\Resources;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Modules\Organization\Domain\Models\Holiday;

final class HolidayFilamentResource extends Resource
{
    protected static ?string $model = Holiday::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-sun';

    protected static ?int $navigationSort = 102;

    public static function getNavigationGroup(): ?string
    {
        return __('organization::filament.navigation_group');
    }

    public static function getModelLabel(): string
    {
        return __('organization::filament.holiday.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('organization::filament.holiday.plural');
    }

    public static function canAccess(): bool
    {
        return (bool) (auth()->user()?->can('viewAny', Holiday::class) ?? false);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                TextInput::make('name.ar')
                    ->label(__('organization::fields.name_ar'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('name.en')
                    ->label(__('organization::fields.name_en'))
                    ->maxLength(255),
                DatePicker::make('starts_on')
                    ->label(__('organization::fields.starts_on'))
                    ->required(),
                DatePicker::make('ends_on')
                    ->label(__('organization::fields.ends_on'))
                    ->required()
                    ->afterOrEqual('starts_on'),
                Toggle::make('blocks_scheduling')
                    ->label(__('organization::fields.blocks_scheduling'))
                    ->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name.'.app()->getLocale())
                    ->label(__('organization::fields.holiday_name'))
                    ->searchable(),
                TextColumn::make('starts_on')
                    ->label(__('organization::fields.starts_on'))
                    ->date()
                    ->sortable(),
                TextColumn::make('ends_on')
                    ->label(__('organization::fields.ends_on'))
                    ->date()
                    ->sortable(),
                IconColumn::make('blocks_scheduling')
                    ->label(__('organization::fields.blocks_scheduling'))
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('blocks_scheduling')
                    ->label(__('organization::fields.blocks_scheduling')),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHolidays::route('/'),
            'create' => Pages\CreateHoliday::route('/create'),
            'edit' => Pages\EditHoliday::route('/{record}/edit'),
        ];
    }
}
