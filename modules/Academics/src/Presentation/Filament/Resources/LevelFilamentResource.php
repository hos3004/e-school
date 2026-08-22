<?php

declare(strict_types=1);

namespace Modules\Academics\Presentation\Filament\Resources;

use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Academics\Domain\Models\Level;
use Modules\Academics\Domain\Models\Program;

final class LevelFilamentResource extends Resource
{
    protected static ?string $model = Level::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null
            && ($user->can('academics.levels.view_any') || $user->can('academics.levels.view'));
    }

    public static function getModelLabel(): string
    {
        return __('academics::filament.level.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('academics::filament.level.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('academics::filament.group');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                Select::make('program_id')
                    ->label(__('academics::filament.level.fields.program'))
                    ->relationship('program', 'code')
                    ->getOptionLabelFromRecordUsing(fn (Program $record): string => (string) ($record->name['ar'] ?? $record->name['en'] ?? $record->code))
                    ->required(),

                TextInput::make('code')
                    ->label(__('academics::filament.level.fields.code'))
                    ->required()
                    ->maxLength(32),

                TextInput::make('name.ar')
                    ->label(__('academics::filament.level.fields.name_ar'))
                    ->required()
                    ->maxLength(255),

                TextInput::make('name.en')
                    ->label(__('academics::filament.level.fields.name_en'))
                    ->maxLength(255),

                TextInput::make('sort_order')
                    ->label(__('academics::filament.level.fields.sort_order'))
                    ->numeric()
                    ->default(0)
                    ->minValue(0),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label(__('academics::filament.level.fields.code'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label(__('academics::filament.level.fields.name'))
                    ->formatStateUsing(fn ($state): string => (string) ($state['ar'] ?? $state['en'] ?? '')),

                TextColumn::make('program.code')
                    ->label(__('academics::filament.level.fields.program'))
                    ->badge()
                    ->sortable(),

                TextColumn::make('sort_order')
                    ->label(__('academics::filament.level.fields.sort_order'))
                    ->numeric()
                    ->sortable(),
            ])
            ->actions([
                EditAction::make()
                    ->visible(fn (Level $record): bool => auth()->user()?->can('update', $record) === true),
            ])
            ->defaultSort('sort_order');
    }
}
