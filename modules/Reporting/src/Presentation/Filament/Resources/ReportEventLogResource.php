<?php

declare(strict_types=1);

namespace Modules\Reporting\Presentation\Filament\Resources;

use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Reporting\Domain\Models\ReportEventLog;

/**
 * مورد سجل الأحداث المُدخلة — قراءة تشخيصية فقط، السجل append-only.
 */
final class ReportEventLogResource extends Resource
{
    protected static ?string $model = ReportEventLog::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?int $navigationSort = 90;

    public static function getNavigationGroup(): ?string
    {
        return __('reporting::navigation.group');
    }

    public static function getModelLabel(): string
    {
        return __('reporting::navigation.event_log.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('reporting::navigation.event_log.plural');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label(__('reporting::fields.id'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('event_id')
                    ->label(__('reporting::fields.event_id'))
                    ->copyable()
                    ->limit(12),
                TextColumn::make('name')
                    ->label(__('reporting::fields.event_name'))
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('module')
                    ->label(__('reporting::fields.event_module'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('occurred_at')
                    ->label(__('reporting::fields.occurred_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('reporting::fields.ingested_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', direction: 'desc');
    }
}
