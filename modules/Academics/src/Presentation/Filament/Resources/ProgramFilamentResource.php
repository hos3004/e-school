<?php

declare(strict_types=1);

namespace Modules\Academics\Presentation\Filament\Resources;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Modules\Academics\Domain\Enums\ProgramType;
use Modules\Academics\Domain\Enums\TargetGender;
use Modules\Academics\Domain\Models\Program;
use Modules\Organization\Domain\Contracts\GeographyQueries;
use Shared\Codes\EntityCodeGenerator;
use Shared\Concerns\ScopesFilamentToOrganization;
use Shared\Support\LocalizedJsonColumn;

final class ProgramFilamentResource extends Resource
{
    use ScopesFilamentToOrganization;

    protected static ?string $model = Program::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): string
    {
        return __('academics::filament.group');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', Program::class) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create', Program::class) ?? false;
    }

    public static function getModelLabel(): string
    {
        return __('academics::filament.program.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('academics::filament.program.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('academics::filament.program.sections.identity'))
                ->schema([
                    TextInput::make('code')->label(__('academics::filament.program.fields.code'))->required()->default(fn (EntityCodeGenerator $codes): string => $codes->next('program'))->maxLength(8)->unique(ignoreRecord: true),
                    TextInput::make('name.ar')->label(__('academics::filament.program.fields.name_ar'))->required()->maxLength(255),
                    TextInput::make('name.en')->label(__('academics::filament.program.fields.name_en'))->maxLength(255),
                    Textarea::make('description.ar')->label(__('academics::filament.program.fields.description_ar'))->rows(3)->maxLength(2000),
                    Textarea::make('description.en')->label(__('academics::filament.program.fields.description_en'))->rows(3)->maxLength(2000),
                    TextInput::make('language')->label(__('academics::filament.program.fields.language'))->maxLength(16),
                    TextInput::make('sort_order')->label(__('academics::filament.program.fields.sort_order'))->numeric()->minValue(0)->default(0),
                    Toggle::make('is_active')->label(__('academics::filament.program.fields.is_active'))->default(true),
                ])->columns(2),

            Section::make(__('academics::filament.program.sections.delivery'))
                ->schema([
                    Select::make('program_type')
                        ->label(__('academics::filament.program.fields.program_type'))
                        ->options(self::enumOptions(ProgramType::cases(), 'program_types'))
                        ->default(ProgramType::Ongoing->value)->required()->live()
                        ->afterStateUpdated(function (mixed $state, Set $set): void {
                            if ($state === ProgramType::Ongoing->value) {
                                $set('end_date', null);
                            }
                        })->native(false),
                    DatePicker::make('start_date')
                        ->label(__('academics::filament.program.fields.start_date'))
                        ->required(fn (Get $get): bool => $get('program_type') === ProgramType::FixedDuration->value),
                    DatePicker::make('end_date')
                        ->label(__('academics::filament.program.fields.end_date'))
                        ->required(fn (Get $get): bool => $get('program_type') === ProgramType::FixedDuration->value)
                        ->visible(fn (Get $get): bool => $get('program_type') === ProgramType::FixedDuration->value)
                        ->afterOrEqual('start_date'),
                    TextInput::make('duration_weeks')->label(__('academics::filament.program.fields.duration_weeks'))->numeric()->minValue(1),
                    Select::make('target_gender')
                        ->label(__('academics::filament.program.fields.target_gender'))
                        ->options(self::enumOptions(TargetGender::cases(), 'target_genders'))
                        ->default(TargetGender::All->value)->required()->native(false),
                    TextInput::make('age_from')->label(__('academics::filament.program.fields.age_from'))->numeric()->minValue((int) config('academics.age.minimum'))->maxValue((int) config('academics.age.maximum')),
                    TextInput::make('age_to')->label(__('academics::filament.program.fields.age_to'))->numeric()->minValue((int) config('academics.age.minimum'))->maxValue((int) config('academics.age.maximum'))->gte('age_from'),
                    KeyValue::make('objectives')
                        ->label(__('academics::filament.program.fields.objectives'))
                        ->keyLabel(__('academics::filament.program.fields.objective_key'))
                        ->valueLabel(__('academics::filament.program.fields.objective_value'))
                        ->columnSpanFull(),
                ])->columns(2),

            Section::make(__('academics::filament.program.sections.pricing'))
                ->schema([
                    TextInput::make('default_session_minutes')
                        ->label(__('academics::filament.program.fields.default_session_minutes'))
                        ->numeric()->required()->minValue((int) config('academics.session_minutes.minimum'))->default((int) config('academics.session_minutes.default')),
                    TextInput::make('default_rate')
                        ->label(__('academics::filament.program.fields.default_rate'))
                        ->helperText(__('academics::filament.program.fields.rate_minor_units_help'))
                        ->numeric()->minValue(0),
                    Select::make('currency')
                        ->label(__('academics::filament.program.fields.currency'))
                        ->options([
                            'EGP' => __('academics::filament.currencies.EGP'),
                            'SAR' => __('academics::filament.currencies.SAR'),
                            'AED' => __('academics::filament.currencies.AED'),
                            'USD' => __('academics::filament.currencies.USD'),
                        ])->default('EGP')->required()->native(false),
                ])->columns(3),

            Section::make(__('academics::filament.program.sections.eligibility'))
                ->description(__('academics::filament.program.sections.eligibility_help'))
                ->schema([
                    Select::make('eligibility.countries')
                        ->label(__('academics::filament.program.fields.countries'))
                        ->options(fn (): array => self::countryOptions())
                        ->multiple()->searchable()->preload()->live(),
                    Select::make('eligibility.regions')
                        ->label(__('academics::filament.program.fields.regions'))
                        ->options(fn (Get $get): array => self::regionOptions((array) $get('eligibility.countries')))
                        ->multiple()->searchable()->preload(),
                    TextInput::make('eligibility.age_from')->label(__('academics::filament.program.fields.age_from'))->numeric()->minValue((int) config('academics.age.minimum'))->maxValue((int) config('academics.age.maximum')),
                    TextInput::make('eligibility.age_to')->label(__('academics::filament.program.fields.age_to'))->numeric()->minValue((int) config('academics.age.minimum'))->maxValue((int) config('academics.age.maximum'))->gte('eligibility.age_from'),
                    Select::make('eligibility.gender')
                        ->label(__('academics::filament.program.fields.target_gender'))
                        ->options(self::enumOptions(TargetGender::cases(), 'target_genders'))->native(false),
                    Select::make('eligibility.teacher_gender_rule')
                        ->label(__('academics::filament.program.fields.teacher_gender_rule'))
                        ->options([
                            'any' => __('academics::filament.teacher_gender_rules.any'),
                            'same' => __('academics::filament.teacher_gender_rules.same'),
                            'opposite' => __('academics::filament.teacher_gender_rules.opposite'),
                        ])->default('any')->required()->native(false),
                    Toggle::make('eligibility.manual_approval_required')
                        ->label(__('academics::filament.program.fields.manual_approval_required'))->default(true),
                    Toggle::make('eligibility.requires_individual_sessions')
                        ->label(__('academics::filament.program.fields.requires_individual_sessions'))->default(false),
                ])->columns(2)->collapsed(),

            Section::make(__('academics::filament.sections.audit'))
                ->schema([
                    Textarea::make('reason')
                        ->label(__('academics::filament.fields.reason'))
                        ->helperText(__('academics::filament.fields.reason_help'))
                        ->required()->minLength((int) config('academics.reason.minimum_length'))->maxLength((int) config('academics.reason.maximum_length')),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('code')->label(__('academics::filament.program.fields.code'))->searchable()->sortable(),
                TextColumn::make('name')
                    ->label(__('academics::filament.program.fields.name'))
                    ->formatStateUsing(static fn ($state): string => LocalizedJsonColumn::display($state))
                    ->searchable(query: LocalizedJsonColumn::search('programs.name'))
                    ->sortable(query: LocalizedJsonColumn::sort('programs.name')),
                TextColumn::make('program_type')
                    ->label(__('academics::filament.program.fields.program_type'))
                    ->badge()->formatStateUsing(static fn (ProgramType $state): string => $state->label()),
                TextColumn::make('levels_count')->counts('levels')->label(__('academics::filament.program.fields.levels_count'))->numeric(),
                TextColumn::make('default_rate')
                    ->label(__('academics::filament.program.fields.default_rate'))
                    ->money(currency: fn (Program $record): string => (string) $record->currency, divideBy: 100)->sortable(),
                IconColumn::make('is_active')->label(__('academics::filament.program.fields.is_active'))->boolean(),
                TextColumn::make('created_at')->label(__('academics::filament.fields.created_at'))->since()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('program_type')->label(__('academics::filament.program.fields.program_type'))->options(self::enumOptions(ProgramType::cases(), 'program_types')),
                TernaryFilter::make('is_active')->label(__('academics::filament.program.filters.active')),
                TrashedFilter::make()->label(__('academics::filament.filters.trashed')),
            ])
            ->recordActions([
                ViewAction::make()->visible(fn (Program $record): bool => auth()->user()?->can('view', $record) ?? false),
                EditAction::make()->visible(fn (Program $record): bool => auth()->user()?->can('update', $record) ?? false),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ProgramFilamentResource\Pages\ListPrograms::route('/'),
            'create' => ProgramFilamentResource\Pages\CreateProgram::route('/create'),
            'view' => ProgramFilamentResource\Pages\ViewProgram::route('/{record}'),
            'edit' => ProgramFilamentResource\Pages\EditProgram::route('/{record}/edit'),
        ];
    }

    /**
     * @param array<int, \BackedEnum> $cases
     * @return array<string, string>
     */
    private static function enumOptions(array $cases, string $translationKey): array
    {
        return collect($cases)->mapWithKeys(static fn (\BackedEnum $case): array => [
            (string) $case->value => __('academics::filament.'.$translationKey.'.'.$case->value),
        ])->all();
    }

    /** @return array<string, string> */
    private static function countryOptions(): array
    {
        return collect(app(GeographyQueries::class)->countries())
            ->mapWithKeys(static fn ($country): array => [$country->id => LocalizedJsonColumn::display($country->name)])
            ->all();
    }

    /**
     * @param array<int, string> $countryIds
     * @return array<string, string>
     */
    private static function regionOptions(array $countryIds): array
    {
        return collect($countryIds)
            ->flatMap(static fn (string $countryId): array => app(GeographyQueries::class)->regionsOf($countryId))
            ->mapWithKeys(static fn ($region): array => [$region->id => LocalizedJsonColumn::display($region->name)])
            ->all();
    }
}
