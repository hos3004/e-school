<?php

declare(strict_types=1);

namespace Modules\Assignments\Presentation\Filament\Resources;

use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Assignments\Application\Queries\AssignmentAdministrationQueryService;
use Modules\Assignments\Domain\Enums\AssignmentSubmissionStatus;
use Modules\Assignments\Domain\Models\Assignment;
use Modules\Assignments\Presentation\Filament\Resources\AssignmentFilamentResource\Pages;
use Modules\Assignments\Presentation\Filament\Resources\AssignmentFilamentResource\RelationManagers\SubmissionsRelationManager;
use Shared\Concerns\ScopesFilamentToOrganization;
use Shared\Support\Locales;
use Shared\Support\LocalizedJsonColumn;

final class AssignmentFilamentResource extends Resource
{
    use ScopesFilamentToOrganization;

    protected static ?string $model = Assignment::class;

    protected static ?string $slug = 'assignments';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?int $navigationSort = 50;

    public static function getNavigationGroup(): string
    {
        return __('assignments::filament.navigation_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('assignments::filament.assignments.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('assignments::filament.assignments.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('assignments::filament.assignments.plural_model_label');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', Assignment::class) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create', Assignment::class) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('assignments::filament.sections.audience'))
                ->description(__('assignments::filament.sections.audience_help'))
                ->schema([
                    Select::make('program_id')
                        ->label(__('assignments::attributes.program'))
                        ->options(fn (): array => self::queries()->programOptions(self::organizationId()))
                        ->default(fn (?Assignment $record): ?string => self::queries()->programIdForCourse(
                            self::organizationId(),
                            $record?->course_id === null ? null : (string) $record->course_id,
                        ))
                        ->searchable()
                        ->preload()
                        ->live()
                        ->dehydrated(false)
                        ->afterStateUpdated(function (Set $set): void {
                            $set('course_id', null);
                            $set('group_id', null);
                            $set('staff_profile_id', null);
                        })
                        ->required(),
                    Select::make('course_id')
                        ->label(__('assignments::attributes.course'))
                        ->options(fn (Get $get): array => self::queries()->courseOptions(
                            self::organizationId(),
                            is_string($get('program_id')) ? $get('program_id') : null,
                        ))
                        ->searchable()
                        ->preload()
                        ->live()
                        ->afterStateUpdated(function (Set $set): void {
                            $set('group_id', null);
                            $set('staff_profile_id', null);
                        })
                        ->required(),
                    Select::make('group_id')
                        ->label(__('assignments::attributes.group'))
                        ->helperText(__('assignments::filament.course_wide_help'))
                        ->options(fn (Get $get): array => self::queries()->groupOptions(
                            self::organizationId(),
                            is_string($get('course_id')) ? $get('course_id') : null,
                        ))
                        ->searchable()
                        ->preload()
                        ->live()
                        ->afterStateUpdated(fn (Set $set): mixed => $set('staff_profile_id', null)),
                    Select::make('staff_profile_id')
                        ->label(__('assignments::attributes.teacher'))
                        ->options(fn (Get $get): array => self::queries()->teacherOptions(
                            self::organizationId(),
                            is_string($get('course_id')) ? $get('course_id') : null,
                            is_string($get('group_id')) ? $get('group_id') : null,
                        ))
                        ->searchable()
                        ->preload()
                        ->required(),
                ])->columns(2),
            Section::make(__('assignments::filament.sections.content'))
                ->schema([
                    TextInput::make('title.ar')->label(__('assignments::attributes.title_ar'))->required()->maxLength(255),
                    TextInput::make('title.en')->label(__('assignments::attributes.title_en'))->maxLength(255),
                    TextInput::make('title.fr')->label(__('assignments::attributes.title_fr'))->maxLength(255)
                        ->hidden(!Locales::isSupported('fr'))
                        ->dehydratedWhenHidden(),
                    Textarea::make('instructions.ar')->label(__('assignments::attributes.instructions_ar'))->rows(4),
                    Textarea::make('instructions.en')->label(__('assignments::attributes.instructions_en'))->rows(4),
                    Textarea::make('instructions.fr')->label(__('assignments::attributes.instructions_fr'))->rows(4)
                        ->hidden(!Locales::isSupported('fr'))
                        ->dehydratedWhenHidden(),
                ])->columns(3),
            Section::make(__('assignments::filament.sections.grading'))
                ->schema([
                    DateTimePicker::make('assigned_at')
                        ->label(__('assignments::attributes.assigned_at'))
                        ->default(now()->utc())
                        ->seconds(false)
                        ->required(),
                    DateTimePicker::make('due_at')
                        ->label(__('assignments::attributes.due_at'))
                        ->seconds(false)
                        ->after('assigned_at')
                        ->required(),
                    TextInput::make('max_score')
                        ->label(__('assignments::attributes.max_score'))
                        ->numeric()->minValue(1)->maxValue(1000)->default(100)->required(),
                    Toggle::make('allows_late')
                        ->label(__('assignments::attributes.allows_late'))->default(true)->live(),
                    TextInput::make('late_penalty_percent')
                        ->label(__('assignments::attributes.late_penalty_percent'))
                        ->numeric()->minValue(0)->maxValue(100)->default(0)
                        ->visible(fn (Get $get): bool => (bool) $get('allows_late'))
                        ->required(fn (Get $get): bool => (bool) $get('allows_late')),
                ])->columns(3),
            Textarea::make('reason')
                ->label(__('assignments::attributes.reason'))
                ->helperText(__('assignments::filament.reason_help'))
                ->required()
                ->maxLength((int) config('assignments.reason_max_length', 1000))
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('assignments::attributes.title'))
                    ->formatStateUsing(static fn (mixed $state): string => LocalizedJsonColumn::display($state))
                    ->searchable(query: LocalizedJsonColumn::search('assignments.title'))
                    ->sortable(query: LocalizedJsonColumn::sort('assignments.title')),
                TextColumn::make('course_id')
                    ->label(__('assignments::attributes.course'))
                    ->formatStateUsing(fn (mixed $state, Assignment $record): string => self::queries()
                        ->courseLabel((string) $record->organization_id, (string) $state)),
                TextColumn::make('group_id')
                    ->label(__('assignments::attributes.group'))
                    ->formatStateUsing(fn (mixed $state, Assignment $record): string => self::queries()
                        ->groupLabel((string) $record->organization_id, is_string($state) ? $state : null))
                    ->toggleable(),
                TextColumn::make('staff_profile_id')
                    ->label(__('assignments::attributes.teacher'))
                    ->formatStateUsing(fn (mixed $state, Assignment $record): string => self::queries()
                        ->teacherLabel((string) $record->organization_id, (string) $state))
                    ->toggleable(),
                TextColumn::make('operational_status')
                    ->label(__('assignments::attributes.status'))
                    ->state(fn (Assignment $record): string => $record->operationalStatus()->value)
                    ->formatStateUsing(fn (mixed $state, Assignment $record): string => $record->operationalStatus()->label())
                    ->color(fn (Assignment $record): string => $record->operationalStatus()->color())
                    ->badge(),
                TextColumn::make('due_at')
                    ->label(__('assignments::attributes.due_at'))->dateTime()->sortable(),
                TextColumn::make('submissions_count')
                    ->label(__('assignments::filament.metrics.recipients'))->numeric()->sortable(),
                TextColumn::make('submitted_count')
                    ->label(__('assignments::filament.metrics.awaiting_grading'))->numeric()
                    ->color(fn (mixed $state): string => (int) $state > 0 ? 'warning' : 'gray'),
                TextColumn::make('graded_count')
                    ->label(__('assignments::filament.metrics.graded'))->numeric(),
            ])
            ->filters([
                SelectFilter::make('course_id')
                    ->label(__('assignments::attributes.course'))
                    ->options(fn (): array => self::queries()->courseOptions(self::organizationId()))
                    ->searchable()->preload(),
                TernaryFilter::make('allows_late')->label(__('assignments::attributes.allows_late')),
            ])
            ->recordActions([
                ViewAction::make()->visible(fn (Assignment $record): bool => auth()->user()?->can('view', $record) ?? false),
                EditAction::make()->visible(fn (Assignment $record): bool => auth()->user()?->can('update', $record) ?? false),
            ])
            ->bulkActions([])
            ->defaultSort('due_at');
    }

    /** @return Builder<Assignment> */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Assignment> $query */
        $query = parent::getEloquentQuery();

        return $query->withCount([
            'submissions',
            'submissions as submitted_count' => static fn (Builder $query): Builder => $query->whereIn('status', [
                AssignmentSubmissionStatus::Submitted->value,
                AssignmentSubmissionStatus::Late->value,
            ]),
            'submissions as graded_count' => static fn (Builder $query): Builder => $query
                ->where('status', AssignmentSubmissionStatus::Graded->value),
        ]);
    }

    /** @return array<class-string> */
    public static function getRelations(): array
    {
        return [SubmissionsRelationManager::class];
    }

    /** @return array<string, mixed> */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssignments::route('/'),
            'create' => Pages\CreateAssignment::route('/create'),
            'edit' => Pages\EditAssignment::route('/{record}/edit'),
            'view' => Pages\ViewAssignment::route('/{record}'),
        ];
    }

    public static function organizationId(): string
    {
        return (string) auth()->user()?->getAttribute('organization_id');
    }

    private static function queries(): AssignmentAdministrationQueryService
    {
        return app(AssignmentAdministrationQueryService::class);
    }
}
