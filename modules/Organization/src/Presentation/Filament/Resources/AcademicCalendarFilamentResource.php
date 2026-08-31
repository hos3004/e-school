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
use Modules\Organization\Domain\Models\AcademicCalendar;
use Shared\Concerns\ScopesFilamentToOrganization;

final class AcademicCalendarFilamentResource extends Resource
{
    use ScopesFilamentToOrganization;

    protected static ?string $model = AcademicCalendar::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?int $navigationSort = 102;

    public static function getNavigationGroup(): string
    {
        return __('organization::filament.navigation_group');
    }

    public static function getModelLabel(): string
    {
        return __('organization::filament.calendar.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('organization::filament.calendar.plural');
    }

    public static function canAccess(): bool
    {
        return (bool) (auth()->user()?->can('viewAny', AcademicCalendar::class) ?? false);
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
                    ->required()
                    ->minDate(fn (?AcademicCalendar $record): ?string => $record?->starts_on?->toDateString()),
                DatePicker::make('ends_on')
                    ->label(__('organization::fields.ends_on'))
                    ->required()
                    ->after('starts_on'),
                Toggle::make('is_active')
                    ->label(__('organization::fields.is_active')),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name.'.app()->getLocale())
                    ->label(__('organization::fields.calendar_name'))
                    ->searchable(),
                TextColumn::make('starts_on')
                    ->label(__('organization::fields.starts_on'))
                    ->date()
                    ->sortable(),
                TextColumn::make('ends_on')
                    ->label(__('organization::fields.ends_on'))
                    ->date()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label(__('organization::fields.is_active'))
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('organization::fields.is_active')),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAcademicCalendars::route('/'),
            'create' => Pages\CreateAcademicCalendar::route('/create'),
            'edit' => Pages\EditAcademicCalendar::route('/{record}/edit'),
        ];
    }
}
