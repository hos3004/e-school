<?php

declare(strict_types=1);

namespace Modules\Certificates\Presentation\Filament\Resources;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Modules\Certificates\Domain\Models\BadgeAward;

/**
 * مورد عرض منح الشارات في لوحة الإدارة.
 *
 * قيود المنح لصيقة (append-only): تُعرض فقط — لا تعديل ولا حذف.
 */
final class BadgeAwardFilamentResource extends Resource
{
    protected static ?string $model = BadgeAward::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-star';

    protected static ?int $navigationSort = 53;

    public static function getNavigationGroup(): ?string
    {
        return __('certificates::navigation.group');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getModelLabel(): string
    {
        return __('certificates::navigation.award.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('certificates::navigation.award.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('certificates::fields.award'))
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('badge_id')
                            ->label(__('certificates::navigation.badge.label'))
                            ->disabled(),
                        TextInput::make('user_id')
                            ->label(__('certificates::fields.user'))
                            ->disabled(),
                    ]),
                    Textarea::make('reason')
                        ->label(__('certificates::fields.reason'))
                        ->disabled()
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('badge_id')
                    ->label(__('certificates::navigation.badge.label'))
                    ->copyable(),
                TextColumn::make('user_id')
                    ->label(__('certificates::fields.user'))
                    ->copyable(),
                TextColumn::make('reason')
                    ->label(__('certificates::fields.reason'))
                    ->limit(50)
                    ->toggleable(),
                TextColumn::make('awarded_at')
                    ->label(__('certificates::fields.awarded_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('awarded_at', direction: 'desc');
    }
}
