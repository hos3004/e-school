<?php

declare(strict_types=1);

namespace Modules\Academics\Presentation\Filament\Resources;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Academics\Domain\Models\Level;
use Modules\Academics\Domain\Models\Program;
use Shared\Concerns\ScopesFilamentToOrganizationVia;
use Shared\Support\LocalizedJsonColumn;

final class LevelFilamentResource extends Resource
{
    use ScopesFilamentToOrganizationVia;

    protected static ?string $model = Level::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?int $navigationSort = 11;

    protected static function organizationRelation(): string
    {
        return 'program';
    }

    public static function getNavigationGroup(): string
    {
        return __('academics::filament.group');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', Level::class) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create', Level::class) ?? false;
    }

    public static function getModelLabel(): string
    {
        return __('academics::filament.level.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('academics::filament.level.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('academics::filament.level.sections.identity'))->schema([
                Select::make('program_id')
                    ->label(__('academics::filament.level.fields.program'))
                    ->options(fn (): array => self::programOptions())
                    ->required()->searchable()->preload()->native(false)
                    ->disabled(fn (?Level $record): bool => $record !== null)
                    ->dehydrated(fn (?Level $record): bool => $record === null),
                TextInput::make('code')->label(__('academics::filament.level.fields.code'))->required()->maxLength(32),
                TextInput::make('name.ar')->label(__('academics::filament.level.fields.name_ar'))->required()->maxLength(255),
                TextInput::make('name.en')->label(__('academics::filament.level.fields.name_en'))->maxLength(255),
                TextInput::make('sort_order')->label(__('academics::filament.level.fields.sort_order'))->numeric()->default(0)->minValue(0),
            ])->columns(2),
            Section::make(__('academics::filament.sections.audit'))->schema([
                Textarea::make('reason')->label(__('academics::filament.fields.reason'))->helperText(__('academics::filament.fields.reason_help'))->required()->minLength((int) config('academics.reason.minimum_length'))->maxLength((int) config('academics.reason.maximum_length')),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label(__('academics::filament.level.fields.code'))->searchable()->sortable(),
                TextColumn::make('name')->label(__('academics::filament.level.fields.name'))->formatStateUsing(static fn ($state): string => LocalizedJsonColumn::display($state)),
                TextColumn::make('program.name')->label(__('academics::filament.level.fields.program'))->formatStateUsing(static fn ($state): string => LocalizedJsonColumn::display($state))->badge()->sortable(),
                TextColumn::make('courses_count')->counts('courses')->label(__('academics::filament.level.fields.courses_count'))->numeric(),
                TextColumn::make('sort_order')->label(__('academics::filament.level.fields.sort_order'))->numeric()->sortable(),
            ])
            ->filters([
                SelectFilter::make('program_id')->label(__('academics::filament.level.fields.program'))->options(fn (): array => self::programOptions())->searchable(),
            ])
            ->recordActions([
                ViewAction::make()->visible(fn (Level $record): bool => auth()->user()?->can('view', $record) ?? false),
                EditAction::make()->visible(fn (Level $record): bool => auth()->user()?->can('update', $record) ?? false),
            ])
            ->bulkActions([])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => LevelFilamentResource\Pages\ListLevels::route('/'),
            'create' => LevelFilamentResource\Pages\CreateLevel::route('/create'),
            'view' => LevelFilamentResource\Pages\ViewLevel::route('/{record}'),
            'edit' => LevelFilamentResource\Pages\EditLevel::route('/{record}/edit'),
        ];
    }

    /** @return array<string, string> */
    private static function programOptions(): array
    {
        $organizationId = data_get(auth()->user(), 'organization_id');
        if (!is_string($organizationId) || $organizationId === '') {
            return [];
        }

        return Program::query()->where('organization_id', $organizationId)->orderBy('sort_order')->get()
            ->mapWithKeys(static fn (Program $program): array => [
                (string) $program->getKey() => sprintf('%s — %s', $program->code, LocalizedJsonColumn::display($program->name)),
            ])->all();
    }
}
