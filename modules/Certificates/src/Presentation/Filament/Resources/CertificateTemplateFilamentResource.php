<?php

declare(strict_types=1);

namespace Modules\Certificates\Presentation\Filament\Resources;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Modules\Certificates\Domain\Models\CertificateTemplate;

/**
 * مورد إدارة قوالب الشهادات في لوحة الإدارة.
 */
final class CertificateTemplateFilamentResource extends Resource
{
    protected static ?string $model = CertificateTemplate::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-duplicate';

    protected static \UnitEnum|string|null $navigationGroup = 'التعلّم';

    protected static ?int $navigationSort = 53;

    public static function getModelLabel(): string
    {
        return __('certificates::navigation.template.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('certificates::navigation.template.plural');
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
                        TextInput::make('program_id')
                            ->label(__('certificates::fields.program'))
                            ->maxLength(26),
                        Toggle::make('is_active')
                            ->label(__('certificates::fields.is_active'))
                            ->default(true),
                    ]),
                ]),
            Section::make(__('certificates::fields.design'))
                ->schema([
                    KeyValue::make('name')
                        ->label(__('certificates::fields.name'))
                        ->keyLabel(__('certificates::fields.locale'))
                        ->valueLabel(__('certificates::fields.value'))
                        ->required()
                        ->columnSpanFull(),
                    KeyValue::make('layout')
                        ->label(__('certificates::fields.layout'))
                        ->keyLabel(__('certificates::fields.locale'))
                        ->valueLabel(__('certificates::fields.value'))
                        ->required()
                        ->columnSpanFull(),
                    TextInput::make('background_image_path')
                        ->label(__('certificates::fields.background_image'))
                        ->maxLength(2048)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label(__('certificates::fields.id'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('name')
                    ->label(__('certificates::fields.name'))
                    ->formatStateUsing(fn ($state): string => is_array($state)
                        ? ($state[app()->getLocale()] ?? reset($state))
                        : (string) $state)
                    ->limit(40)
                    ->searchable(),
                TextColumn::make('program_id')
                    ->label(__('certificates::fields.program'))
                    ->copyable()
                    ->toggleable(),
                IconColumn::make('is_active')
                    ->label(__('certificates::fields.is_active'))
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label(__('certificates::fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('certificates::fields.is_active')),
            ])
            ->defaultSort('created_at', direction: 'desc');
    }
}
