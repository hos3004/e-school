<?php

declare(strict_types=1);

namespace Modules\Certificates\Presentation\Filament\Resources;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Modules\Certificates\Domain\Enums\BadgeTier;
use Modules\Certificates\Domain\Models\Badge;

/**
 * مورد إدارة شارات الكتالوج في لوحة الإدارة.
 */
final class BadgeFilamentResource extends Resource
{
    protected static ?string $model = Badge::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-trophy';

    protected static ?int $navigationSort = 53;

    public static function getNavigationGroup(): ?string
    {
        return __('certificates::navigation.group');
    }

    public static function getModelLabel(): string
    {
        return __('certificates::navigation.badge.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('certificates::navigation.badge.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('certificates::fields.identity'))
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('organization_id')
                            ->label(__('certificates::fields.organization'))
                            ->required()
                            ->length(26),
                        TextInput::make('code')
                            ->label(__('certificates::fields.code'))
                            ->required()
                            ->alphaDash()
                            ->maxLength(100),
                        Select::make('tier')
                            ->label(__('certificates::fields.tier'))
                            ->options(collect(BadgeTier::cases())
                                ->mapWithKeys(fn (BadgeTier $tier): array => [$tier->value => $tier->label()])
                                ->all())
                            ->required(),
                        Toggle::make('is_active')
                            ->label(__('certificates::fields.is_active'))
                            ->default(true),
                    ]),
                    Textarea::make('name')
                        ->label(__('certificates::fields.name'))
                        ->formatStateUsing(fn ($state): string => is_array($state)
                            ? json_encode($state, JSON_UNESCAPED_UNICODE)
                            : (string) $state)
                        ->required()
                        ->columnSpanFull(),
                    Textarea::make('description')
                        ->label(__('certificates::fields.description'))
                        ->formatStateUsing(fn ($state): string => is_array($state)
                            ? json_encode($state, JSON_UNESCAPED_UNICODE)
                            : (string) $state)
                        ->columnSpanFull(),
                    TextInput::make('icon_path')
                        ->label(__('certificates::fields.icon'))
                        ->maxLength(2048)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label(__('certificates::fields.code'))
                    ->searchable()
                    ->badge(),
                TextColumn::make('name')
                    ->label(__('certificates::fields.name'))
                    ->formatStateUsing(fn ($state): string => is_array($state)
                        ? ($state[app()->getLocale()] ?? reset($state))
                        : (string) $state)
                    ->limit(40)
                    ->searchable(),
                TextColumn::make('tier')
                    ->label(__('certificates::fields.tier'))
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof BadgeTier
                        ? $state->label()
                        : (string) $state)
                    ->color(fn ($state): string => $state instanceof BadgeTier
                        ? $state->color()
                        : 'gray'),
                IconColumn::make('is_active')
                    ->label(__('certificates::fields.is_active'))
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('tier')
                    ->label(__('certificates::fields.tier'))
                    ->options(collect(BadgeTier::cases())
                        ->mapWithKeys(fn (BadgeTier $tier): array => [$tier->value => $tier->label()])
                        ->all()),
                TernaryFilter::make('is_active')
                    ->label(__('certificates::fields.is_active')),
            ])
            ->defaultSort('code');
    }
}
