<?php

declare(strict_types=1);

namespace Modules\Organization\Presentation\Filament\Resources;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Organization\Domain\Enums\Weekday;
use Modules\Organization\Domain\Models\Organization;

final class OrganizationFilamentResource extends Resource
{
    protected static ?string $model = Organization::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static \UnitEnum|string|null $navigationGroup = 'النظام';

    protected static ?int $navigationSort = 102;

    public static function getModelLabel(): string
    {
        return __('organization::filament.organization.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('organization::filament.organization.plural');
    }

    public static function canAccess(): bool
    {
        return (bool) (auth()->user()?->can('viewAny', Organization::class) ?? false);
    }

    /**
     * @return array<int, Component>
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('organization::filament.organization.section_identity'))
                ->schema([
                    TextInput::make('name.ar')
                        ->label(__('organization::fields.name_ar'))
                        ->required()
                        ->maxLength(255),
                    TextInput::make('name.en')
                        ->label(__('organization::fields.name_en'))
                        ->maxLength(255),
                    TextInput::make('slug')
                        ->label(__('organization::fields.slug'))
                        ->unique(ignoreRecord: true)
                        ->required()
                        ->maxLength(64)
                        ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/'),
                ])
                ->columns(2),
            Section::make(__('organization::filament.organization.section_defaults'))
                ->schema([
                    Select::make('default_locale')
                        ->label(__('organization::fields.default_locale'))
                        ->options([
                            'ar' => 'العربية',
                            'en' => 'English',
                            'fr' => 'Français',
                        ])
                        ->required(),
                    Select::make('week_starts_on')
                        ->label(__('organization::fields.week_starts_on'))
                        ->options(array_combine(
                            array_column(Weekday::cases(), 'value'),
                            array_map(
                                static fn (Weekday $day): string => __('organization::enums.weekday.'.$day->value),
                                Weekday::cases(),
                            ),
                        ))
                        ->required(),
                    TextInput::make('default_timezone')
                        ->label(__('organization::fields.default_timezone'))
                        ->required(),
                    TextInput::make('default_currency')
                        ->label(__('organization::fields.default_currency'))
                        ->length(3)
                        ->required(),
                ])
                ->columns(2),
            Section::make(__('organization::filament.organization.section_settings'))
                ->schema([
                    Textarea::make('settings')
                        ->label(__('organization::fields.settings'))
                        ->formatStateUsing(static fn ($state): ?string => $state === null ? null : json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
                        ->dehydrateStateUsing(static fn (?string $state): ?array => $state === null || trim($state) === '' ? null : (array) json_decode($state, true))
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name.'.app()->getLocale())
                    ->label(__('organization::fields.name_ar'))
                    ->searchable(),
                TextColumn::make('slug')
                    ->label(__('organization::fields.slug'))
                    ->badge(),
                TextColumn::make('default_currency')
                    ->label(__('organization::fields.default_currency')),
                TextColumn::make('created_at')
                    ->label(__('organization::fields.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrganizations::route('/'),
            'create' => Pages\CreateOrganization::route('/create'),
            'edit' => Pages\EditOrganization::route('/{record}/edit'),
        ];
    }
}
