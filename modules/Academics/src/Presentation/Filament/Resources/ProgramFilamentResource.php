<?php

declare(strict_types=1);

namespace Modules\Academics\Presentation\Filament\Resources;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Modules\Academics\Domain\Models\Program;

final class ProgramFilamentResource extends Resource
{
    protected static ?string $model = Program::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null
            && ($user->can('academics.programs.view_any') || $user->can('academics.programs.view'));
    }

    public static function getModelLabel(): string
    {
        return __('academics::filament.program.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('academics::filament.program.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('academics::filament.group');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                TextInput::make('code')
                    ->label(__('academics::filament.program.fields.code'))
                    ->required()
                    ->maxLength(32)
                    ->unique(ignoreRecord: true)
                    ->disabled(fn (?Program $record): bool => $record !== null),

                TextInput::make('name.ar')
                    ->label(__('academics::filament.program.fields.name_ar'))
                    ->required()
                    ->maxLength(255),

                TextInput::make('name.en')
                    ->label(__('academics::filament.program.fields.name_en'))
                    ->maxLength(255),

                TextInput::make('duration_weeks')
                    ->label(__('academics::filament.program.fields.duration_weeks'))
                    ->numeric()
                    ->minValue(1)
                    ->nullable(),

                TextInput::make('default_session_minutes')
                    ->label(__('academics::filament.program.fields.default_session_minutes'))
                    ->numeric()
                    ->required()
                    ->minValue(15),

                TextInput::make('default_rate')
                    ->label(__('academics::filament.program.fields.default_rate'))
                    ->numeric()
                    ->minValue(0)
                    ->nullable(),

                Select::make('currency')
                    ->label(__('academics::filament.program.fields.currency'))
                    ->options([
                        'EGP' => __('academics::filament.currencies.EGP'),
                        'SAR' => __('academics::filament.currencies.SAR'),
                        'AED' => __('academics::filament.currencies.AED'),
                        'USD' => __('academics::filament.currencies.USD'),
                    ])
                    ->default('EGP')
                    ->required(),

                Toggle::make('is_active')
                    ->label(__('academics::filament.program.fields.is_active'))
                    ->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label(__('academics::filament.program.fields.code'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label(__('academics::filament.program.fields.name'))
                    ->formatStateUsing(fn ($state): string => (string) ($state['ar'] ?? $state['en'] ?? ''))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('duration_weeks')
                    ->label(__('academics::filament.program.fields.duration_weeks'))
                    ->numeric()
                    ->sortable(),

                TextColumn::make('default_rate')
                    ->label(__('academics::filament.program.fields.default_rate'))
                    ->money(currency: fn (Program $record): string => (string) $record->currency, divideBy: 100)
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label(__('academics::filament.program.fields.is_active'))
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label(__('academics::filament.fields.created_at'))
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('academics::filament.program.filters.active')),
            ])
            ->actions([
                EditAction::make()
                    ->visible(fn (Program $record): bool => auth()->user()?->can('update', $record) === true),
                DeleteAction::make()
                    ->visible(fn (Program $record): bool => auth()->user()?->can('delete', $record) === true),
            ]);
    }
}
