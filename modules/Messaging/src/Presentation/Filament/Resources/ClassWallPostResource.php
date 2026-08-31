<?php

declare(strict_types=1);

namespace Modules\Messaging\Presentation\Filament\Resources;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Modules\Messaging\Domain\Models\ClassWallPost;
use Shared\Concerns\ScopesFilamentToOrganization;

/**
 * مورد إدارة حائط الصفوف في لوحة الإدارة.
 */
final class ClassWallPostResource extends Resource
{
    use ScopesFilamentToOrganization;

    protected static ?string $model = ClassWallPost::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?int $navigationSort = 70;

    public static function getNavigationGroup(): string
    {
        return __('messaging::navigation.group');
    }

    public static function getModelLabel(): string
    {
        return __('messaging::navigation.wall_post.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('messaging::navigation.wall_post.plural');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('group_id')
                ->label(__('messaging::fields.group'))
                ->required()
                ->maxLength(26),
            RichEditor::make('body')
                ->label(__('messaging::fields.body'))
                ->required()
                ->columnSpanFull(),
            Toggle::make('is_pinned')
                ->label(__('messaging::fields.is_pinned')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label(__('messaging::fields.id'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('body')
                    ->label(__('messaging::fields.body'))
                    ->limit(60)
                    ->searchable(),
                TextColumn::make('group_id')
                    ->label(__('messaging::fields.group'))
                    ->copyable(),
                TextColumn::make('user_id')
                    ->label(__('messaging::fields.author'))
                    ->copyable()
                    ->toggleable(),
                IconColumn::make('is_pinned')
                    ->label(__('messaging::fields.is_pinned'))
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label(__('messaging::fields.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_pinned')
                    ->label(__('messaging::fields.is_pinned')),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
