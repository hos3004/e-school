<?php

declare(strict_types=1);

namespace Modules\Assessments\Presentation\Filament\Resources;

use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Assessments\Application\Queries\AssessmentAdministrationQueryService;
use Modules\Assessments\Domain\Enums\AssessmentType;
use Modules\Assessments\Domain\Models\Assessment;
use Modules\Assessments\Presentation\Filament\Resources\AssessmentResource\Pages;
use Shared\Concerns\ScopesFilamentToOrganization;
use Shared\Support\Locales;
use Shared\Support\LocalizedJsonColumn;

final class AssessmentResource extends Resource
{
    use ScopesFilamentToOrganization;

    protected static ?string $model = Assessment::class;

    protected static ?string $slug = 'assessments';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?int $navigationSort = 51;

    public static function getNavigationGroup(): string
    {
        return __('assessments::navigation.group');
    }

    public static function getModelLabel(): string
    {
        return __('assessments::navigation.assessment.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('assessments::navigation.assessment.plural');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', Assessment::class) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create', Assessment::class) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('assessments::filament.sections.audience'))
                ->description(__('assessments::filament.helpers.course_optional'))
                ->schema([
                    Select::make('type')
                        ->label(__('assessments::fields.type'))
                        ->options(collect(AssessmentType::cases())->mapWithKeys(
                            static fn (AssessmentType $type): array => [$type->value => $type->label()],
                        )->all())
                        ->live()
                        ->required(),
                    Select::make('program_id')
                        ->label(__('assessments::fields.program'))
                        ->options(fn (): array => self::queries()->programOptions(self::organizationId()))
                        ->default(fn (?Assessment $record): ?string => self::queries()->programIdForCourse(
                            self::organizationId(),
                            $record?->course_id === null ? null : (string) $record->course_id,
                        ))
                        ->searchable()->preload()->live()->dehydrated(false)
                        ->afterStateUpdated(fn (Set $set): mixed => $set('course_id', null)),
                    Select::make('course_id')
                        ->label(__('assessments::fields.course'))
                        ->options(fn (Get $get): array => self::queries()->courseOptions(
                            self::organizationId(),
                            is_string($get('program_id')) ? $get('program_id') : null,
                        ))
                        ->searchable()->preload()
                        ->required(fn (Get $get): bool => in_array($get('type'), [
                            AssessmentType::Quiz->value,
                            AssessmentType::Exam->value,
                        ], true)),
                ])->columns(3),
            Section::make(__('assessments::filament.sections.content'))
                ->schema([
                    TextInput::make('title.ar')->label(__('assessments::fields.title_ar'))->required()->maxLength(255),
                    TextInput::make('title.en')->label(__('assessments::fields.title_en'))->maxLength(255),
                    TextInput::make('title.fr')->label(__('assessments::fields.title_fr'))->maxLength(255)
                        ->hidden(!Locales::isSupported('fr'))
                        ->dehydratedWhenHidden(),
                    Textarea::make('instructions.ar')->label(__('assessments::fields.instructions_ar'))->rows(4),
                    Textarea::make('instructions.en')->label(__('assessments::fields.instructions_en'))->rows(4),
                    Textarea::make('instructions.fr')->label(__('assessments::fields.instructions_fr'))->rows(4)
                        ->hidden(!Locales::isSupported('fr'))
                        ->dehydratedWhenHidden(),
                ])->columns(3),
            Section::make(__('assessments::filament.sections.scoring'))
                ->description(__('assessments::filament.helpers.score_lock'))
                ->schema([
                    TextInput::make('total_score')->label(__('assessments::fields.total_score'))
                        ->numeric()->minValue(1)->default(100)->required(),
                    TextInput::make('passing_score')->label(__('assessments::fields.passing_score'))
                        ->numeric()->minValue(0)->default(60)->required(),
                    TextInput::make('duration_minutes')->label(__('assessments::fields.duration'))
                        ->numeric()->minValue(1),
                    TextInput::make('max_attempts')->label(__('assessments::fields.max_attempts'))
                        ->numeric()->minValue(1)->default(1)->required(),
                ])->columns(4),
            Section::make(__('assessments::filament.sections.availability'))
                ->schema([
                    DateTimePicker::make('available_from')->label(__('assessments::fields.available_from'))
                        ->default(now()->utc())->seconds(false)->required(),
                    DateTimePicker::make('available_to')->label(__('assessments::fields.available_to'))
                        ->seconds(false)->after('available_from')->required(),
                ])->columns(2),
            Textarea::make('reason')
                ->label(__('assessments::fields.reason'))
                ->helperText(__('assessments::filament.helpers.reason'))
                ->required()
                ->maxLength((int) config('assessments.reason_max_length', 1000))
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label(__('assessments::fields.title'))
                    ->formatStateUsing(static fn (mixed $state): string => LocalizedJsonColumn::display($state))
                    ->searchable(query: LocalizedJsonColumn::search('assessments.title'))
                    ->sortable(query: LocalizedJsonColumn::sort('assessments.title')),
                TextColumn::make('course_id')->label(__('assessments::fields.course'))
                    ->formatStateUsing(fn (mixed $state, Assessment $record): string => self::queries()->courseLabel(
                        (string) $record->organization_id,
                        is_string($state) ? $state : null,
                    )),
                TextColumn::make('type')->label(__('assessments::fields.type'))->badge()
                    ->formatStateUsing(fn (AssessmentType $state): string => $state->label())
                    ->color(fn (AssessmentType $state): string => $state->color()),
                TextColumn::make('operational_status')->label(__('assessments::fields.status'))
                    ->state(fn (Assessment $record): string => $record->operationalStatus()->label())
                    ->color(fn (Assessment $record): string => $record->operationalStatus()->color())->badge(),
                TextColumn::make('questions_count')->label(__('assessments::fields.questions'))->numeric()->sortable(),
                TextColumn::make('questions_sum_score')->label(__('assessments::fields.allocated_score'))->numeric(),
                TextColumn::make('attempts_count')->label(__('assessments::fields.attempts'))->numeric()->sortable(),
                TextColumn::make('awaiting_grading_count')->label(__('assessments::fields.awaiting_grading'))
                    ->numeric()->color(fn (mixed $state): string => (int) $state > 0 ? 'warning' : 'gray'),
                TextColumn::make('available_to')->label(__('assessments::fields.available_to'))
                    ->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')->label(__('assessments::fields.type'))
                    ->options(collect(AssessmentType::cases())->mapWithKeys(
                        static fn (AssessmentType $type): array => [$type->value => $type->label()],
                    )->all()),
                SelectFilter::make('course_id')->label(__('assessments::fields.course'))
                    ->options(fn (): array => self::queries()->courseOptions(self::organizationId()))
                    ->searchable()->preload(),
            ])
            ->recordActions([
                ViewAction::make()->visible(fn (Assessment $record): bool => auth()->user()?->can('view', $record) ?? false),
                EditAction::make()->visible(fn (Assessment $record): bool => auth()->user()?->can('update', $record) ?? false),
            ])
            ->bulkActions([])
            ->defaultSort('available_from', 'desc');
    }

    /** @return Builder<Assessment> */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Assessment> $query */
        $query = parent::getEloquentQuery();

        return $query->withCount([
            'questions',
            'attempts',
            'attempts as awaiting_grading_count' => static fn (Builder $query): Builder => $query
                ->whereNotNull('submitted_at')->whereNull('graded_at'),
        ])->withSum('questions', 'score');
    }

    /** @return array<class-string> */
    public static function getRelations(): array
    {
        return [];
    }

    /** @return array<string, mixed> */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssessments::route('/'),
            'create' => Pages\CreateAssessment::route('/create'),
            'view' => Pages\ViewAssessment::route('/{record}'),
            'edit' => Pages\EditAssessment::route('/{record}/edit'),
        ];
    }

    public static function organizationId(): string
    {
        return (string) auth()->user()?->getAttribute('organization_id');
    }

    private static function queries(): AssessmentAdministrationQueryService
    {
        return app(AssessmentAdministrationQueryService::class);
    }
}
