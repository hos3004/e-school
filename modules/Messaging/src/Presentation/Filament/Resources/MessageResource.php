<?php

declare(strict_types=1);

namespace Modules\Messaging\Presentation\Filament\Resources;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Modules\Messaging\Domain\Models\Message;
use Shared\Concerns\ScopesFilamentToOrganization;

/**
 * مورد إشراف على الرسائل في لوحة الإدارة.
 */
final class MessageResource extends Resource
{
    use ScopesFilamentToOrganization;

    protected static ?string $model = Message::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    protected static ?int $navigationSort = 70;

    public static function getNavigationGroup(): ?string
    {
        return __('messaging::navigation.group');
    }

    public static function getModelLabel(): string
    {
        return __('messaging::navigation.message.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('messaging::navigation.message.plural');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            RichEditor::make('body')
                ->label(__('messaging::fields.body'))
                ->required()
                ->columnSpanFull(),
            Select::make('conversation_id')
                ->label(__('messaging::fields.conversation'))
                ->relationship('conversation', 'subject')
                ->searchable()
                ->required(),
            Toggle::make('is_flagged')
                ->label(__('messaging::fields.is_flagged')),
            Textarea::make('flagged_reason')
                ->label(__('messaging::fields.flagged_reason'))
                ->maxLength(1000),
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
                TextColumn::make('conversation.subject')
                    ->label(__('messaging::fields.conversation'))
                    ->limit(30)
                    ->toggleable(),
                TextColumn::make('user_id')
                    ->label(__('messaging::fields.sender'))
                    ->copyable()
                    ->toggleable(),
                IconColumn::make('is_flagged')
                    ->label(__('messaging::fields.is_flagged'))
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label(__('messaging::fields.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_flagged')
                    ->label(__('messaging::fields.is_flagged')),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
