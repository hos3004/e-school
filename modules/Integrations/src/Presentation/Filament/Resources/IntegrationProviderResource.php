<?php

declare(strict_types=1);

namespace Modules\Integrations\Presentation\Filament\Resources;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Integrations\Domain\Models\IntegrationProvider;

/**
 * مورد إدارة مزوّدي التكاملات في لوحة الإدارة.
 */
final class IntegrationProviderResource extends Resource
{
    protected static ?string $model = IntegrationProvider::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cloud';

    protected static ?int $navigationSort = 104;

    public static function getNavigationGroup(): string
    {
        return __('integrations::navigation.group');
    }

    public static function getModelLabel(): string
    {
        return __('integrations::navigation.provider.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('integrations::navigation.provider.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('integrations::fields.identity'))
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('key')
                            ->label(__('integrations::fields.key'))
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(80)
                            ->alphaDash(),
                        TextInput::make('category')
                            ->label(__('integrations::fields.category'))
                            ->required()
                            ->maxLength(50),
                        TextInput::make('driver')
                            ->label(__('integrations::fields.driver'))
                            ->maxLength(120),
                        Toggle::make('is_active')
                            ->label(__('integrations::fields.is_active'))
                            ->default(true),
                    ]),
                ]),
            Section::make(__('integrations::fields.details'))
                ->schema([
                    KeyValue::make('name')
                        ->label(__('integrations::fields.name'))
                        ->keyLabel('locale')
                        ->valueLabel('value')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')
                    ->label(__('integrations::fields.key'))
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray'),
                TextColumn::make('name')
                    ->label(__('integrations::fields.name'))
                    ->formatStateUsing(fn ($state): string => is_array($state)
                        ? ($state[app()->getLocale()] ?? reset($state))
                        : (string) $state)
                    ->limit(40),
                TextColumn::make('category')
                    ->label(__('integrations::fields.category'))
                    ->badge()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label(__('integrations::fields.is_active'))
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label(__('integrations::fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label(__('integrations::fields.category')),
            ])
            ->defaultSort('key');
    }

    /**
     * @return array<string, mixed>
     */
    public static function getPages(): array
    {
        return [
            'index' => IntegrationProviderResource\Pages\ListIntegrationProviders::route('/'),
        ];
    }
}
