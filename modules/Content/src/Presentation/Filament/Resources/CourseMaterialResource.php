<?php

declare(strict_types=1);

namespace Modules\Content\Presentation\Filament\Resources;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Modules\Content\Domain\Enums\MaterialType;
use Modules\Content\Domain\Models\CourseMaterial;
use Shared\Support\LocalizedJsonColumn;

/**
 * مورد إدارة المواد التعليمية في لوحة الإدارة.
 */
final class CourseMaterialResource extends Resource
{
    protected static ?string $model = CourseMaterial::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static ?int $navigationSort = 12;

    public static function getNavigationGroup(): ?string
    {
        return __('content::navigation.group');
    }

    public static function getModelLabel(): string
    {
        return __('content::navigation.material.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('content::navigation.material.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('course_id')
                ->label(__('content::fields.course'))
                ->required()
                ->maxLength(26),
            Grid::make(2)->schema([
                TextInput::make('title.ar')
                    ->label(__('content::fields.title_ar'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('title.en')
                    ->label(__('content::fields.title_en'))
                    ->maxLength(255),
            ]),
            Grid::make(2)->schema([
                Select::make('type')
                    ->label(__('content::fields.type'))
                    ->options(MaterialType::options())
                    ->default(MaterialType::File->value)
                    ->required()
                    ->live(),
                FileUpload::make('path')
                    ->label(__('content::fields.file'))
                    ->disk((string) config('content.uploads.disk'))
                    ->directory('course-materials')
                    ->maxSize(((int) config('content.uploads.max_size_mb', 100)) * 1024)
                    ->visible(fn (string $operation, ?CourseMaterial $record): bool => ($record?->type ?? MaterialType::tryFrom((string) request()->input('type')))?->requiresFile() ?? false),
                TextInput::make('external_url')
                    ->label(__('content::fields.external_url'))
                    ->url()
                    ->maxLength(2048),
                TextInput::make('size_bytes')
                    ->label(__('content::fields.size_bytes'))
                    ->numeric()
                    ->minValue(0)
                    ->maxSize?->ignore(),
            ]),
            Section_Visibility::make(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('content::fields.title'))
                    ->formatStateUsing(static fn ($state): string => LocalizedJsonColumn::display($state))
                    // عمود jsonb: البحث الافتراضي يبني LIKE عليه فينهار الطلب.
                    ->searchable(query: LocalizedJsonColumn::search('course_materials.title'))
                    ->limit(40),
                TextColumn::make('type')
                    ->label(__('content::fields.type'))
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof MaterialType
                        ? $state->label()
                        : (string) $state),
                TextColumn::make('course_id')
                    ->label(__('content::fields.course'))
                    ->copyable()
                    ->toggleable(),
                IconColumn::make('is_currently_visible')
                    ->label(__('content::fields.visible_now'))
                    ->boolean()
                    ->getStateUsing(fn (CourseMaterial $record): bool => $record->isCurrentlyVisible()),
                TextColumn::make('size_bytes')
                    ->label(__('content::fields.size_bytes'))
                    ->numeric()
                    ->toggleable(),
                TextColumn::make('visible_from')
                    ->label(__('content::fields.visible_from'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('visible_to')
                    ->label(__('content::fields.visible_to'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label(__('content::fields.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('content::fields.type'))
                    ->options(MaterialType::options()),
                TernaryFilter::make('visible_now')
                    ->label(__('content::filters.visible_now'))
                    ->queries(
                        true: fn ($query) => $query->active(),
                        false: fn ($query) => $query->where(function ($q): void {
                            $q->where('visible_from', '>', now())
                                ->orWhere('visible_to', '<=', now());
                        }),
                    ),
            ])
            ->defaultSort('created_at', direction: 'desc');
    }
}
