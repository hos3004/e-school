<?php

declare(strict_types=1);

namespace Modules\Academics\Presentation\Filament\Resources;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Academics\Application\Queries\AcademicAdministrationQueryService;
use Modules\Academics\Domain\Enums\SessionMode;
use Modules\Academics\Domain\Enums\TargetGender;
use Modules\Academics\Domain\Models\Course;
use Modules\Academics\Domain\Models\Level;
use Modules\Academics\Domain\Models\Program;
use Shared\Codes\EntityCodeGenerator;
use Shared\Concerns\ScopesFilamentToOrganization;
use Shared\Support\LocalizedJsonColumn;

final class CourseFilamentResource extends Resource
{
    use ScopesFilamentToOrganization;

    protected static ?string $model = Course::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): string
    {
        return __('academics::filament.group');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', Course::class) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create', Course::class) ?? false;
    }

    public static function getModelLabel(): string
    {
        return __('academics::filament.course.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('academics::filament.course.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            /*
             * `organization_id` ليس حقلًا في النموذج عمدًا.
             * كان مربّع نص يكتب فيه المستخدم ULID بيده — أي أن أي مدير يستطيع
             * إسناد كورس إلى مؤسسة أخرى بكتابة معرّفها. القيمة الآن تُشتق من
             * الجلسة في `CreateCourse::mutateFormDataBeforeCreate()`.
             */
            Section::make(__('academics::filament.course.sections.identity'))
                ->schema([
                    Select::make('level_id')
                        ->label(__('academics::filament.course.fields.level'))
                        ->options(fn (): array => self::levelOptions())
                        ->searchable()
                        ->required()
                        ->live()
                        ->native(false),

                    TextInput::make('code')
                        ->label(__('academics::filament.course.fields.code'))
                        ->required()
                        ->default(fn (EntityCodeGenerator $codes): string => $codes->next('course'))
                        ->maxLength(8)
                        ->unique(ignoreRecord: true),

                    TextInput::make('name.ar')
                        ->label(__('academics::filament.course.fields.name_ar'))
                        ->required()
                        ->maxLength(255),

                    TextInput::make('name.en')
                        ->label(__('academics::filament.course.fields.name_en'))
                        ->maxLength(255),

                    Textarea::make('description.ar')
                        ->label(__('academics::filament.course.fields.description_ar'))
                        ->rows(3)
                        ->maxLength(2000),

                    Textarea::make('description.en')
                        ->label(__('academics::filament.course.fields.description_en'))
                        ->rows(3)
                        ->maxLength(2000),

                    Toggle::make('is_active')
                        ->label(__('academics::filament.course.fields.is_active'))
                        ->default(true),
                ])
                ->columns(2),

            /*
             * حقول التصنيف: تدخل في مطابقة الطالب بالكورس والمعلم، وكانت
             * موجودة في الهجرة والنموذج بلا أي واجهة تحرّرها.
             */
            Section::make(__('academics::filament.course.sections.delivery'))
                ->schema([
                    Select::make('session_mode')
                        ->label(__('academics::filament.course.fields.session_mode'))
                        ->options(self::enumOptions(SessionMode::cases(), 'session_modes'))
                        ->default(SessionMode::Both->value)
                        ->required()
                        ->native(false),

                    Select::make('target_gender')
                        ->label(__('academics::filament.course.fields.target_gender'))
                        ->options(self::enumOptions(TargetGender::cases(), 'target_genders'))
                        ->placeholder(__('academics::filament.course.fields.inherits_program'))
                        ->native(false),

                    TextInput::make('age_from')
                        ->label(__('academics::filament.course.fields.age_from'))
                        ->numeric()
                        ->minValue((int) config('academics.age.minimum'))
                        ->maxValue((int) config('academics.age.maximum'))
                        ->lte('age_to'),

                    TextInput::make('age_to')
                        ->label(__('academics::filament.course.fields.age_to'))
                        ->numeric()
                        ->minValue((int) config('academics.age.minimum'))
                        ->maxValue((int) config('academics.age.maximum'))
                        ->gte('age_from'),

                    TextInput::make('default_duration_minutes')
                        ->label(__('academics::filament.course.fields.default_duration_minutes'))
                        ->numeric()
                        ->minValue((int) config('academics.session_minutes.course_minimum'))
                        ->maxValue((int) config('academics.session_minutes.maximum'))
                        ->helperText(__('academics::filament.course.fields.duration_help')),

                    TextInput::make('sessions_per_week')
                        ->label(__('academics::filament.course.fields.sessions_per_week'))
                        ->numeric()
                        ->minValue((int) config('academics.sessions_per_week.minimum'))
                        ->maxValue((int) config('academics.sessions_per_week.maximum')),

                    TextInput::make('total_sessions')
                        ->label(__('academics::filament.course.fields.total_sessions'))
                        ->numeric()
                        ->minValue(1),
                ])
                ->columns(2),

            Section::make(__('academics::filament.course.sections.rules'))
                ->schema([
                    KeyValue::make('completion_rules')
                        ->label(__('academics::filament.course.fields.completion_rules'))
                        ->keyLabel(__('academics::filament.course.fields.rule_key'))
                        ->valueLabel(__('academics::filament.course.fields.rule_value')),

                    KeyValue::make('prerequisites')
                        ->label(__('academics::filament.course.fields.prerequisites'))
                        ->keyLabel(__('academics::filament.course.fields.rule_key'))
                        ->valueLabel(__('academics::filament.course.fields.rule_value')),

                    Select::make('category_ids')
                        ->label(__('academics::filament.course.fields.categories'))
                        ->options(fn (Get $get): array => self::categoryOptions(is_string($get('level_id')) ? $get('level_id') : null))
                        ->multiple()
                        ->searchable()
                        ->preload(),
                ])
                ->columns(2)
                ->collapsed(),

            Section::make(__('academics::filament.sections.audit'))
                ->schema([
                    Textarea::make('reason')
                        ->label(__('academics::filament.fields.reason'))
                        ->helperText(__('academics::filament.fields.reason_help'))
                        ->required()
                        ->minLength((int) config('academics.reason.minimum_length'))
                        ->maxLength((int) config('academics.reason.maximum_length')),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('code')
            ->columns([
                TextColumn::make('code')
                    ->label(__('academics::filament.course.fields.code'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label(__('academics::filament.course.fields.name'))
                    ->formatStateUsing(static fn ($state): string => LocalizedJsonColumn::display($state))
                    /*
                     * `name` عمود jsonb: البحث الافتراضي يبني `LIKE` عليه
                     * فينهار الطلب بـ`operator does not exist: jsonb ~~`.
                     */
                    ->searchable(query: LocalizedJsonColumn::search('courses.name'))
                    ->sortable(query: LocalizedJsonColumn::sort('courses.name')),

                TextColumn::make('level.program.code')
                    ->label(__('academics::filament.course.fields.program'))
                    ->badge()
                    ->toggleable(),

                TextColumn::make('level.code')
                    ->label(__('academics::filament.course.fields.level'))
                    ->badge()
                    ->sortable(),

                TextColumn::make('session_mode')
                    ->label(__('academics::filament.course.fields.session_mode'))
                    ->badge()
                    ->formatStateUsing(static fn ($state): string => $state instanceof SessionMode
                        ? __('academics::filament.session_modes.'.$state->value)
                        : (string) $state)
                    ->toggleable(),

                TextColumn::make('target_gender')
                    ->label(__('academics::filament.course.fields.target_gender'))
                    ->badge()
                    ->formatStateUsing(static fn ($state): string => $state instanceof TargetGender
                        ? __('academics::filament.target_genders.'.$state->value)
                        : __('academics::filament.course.fields.inherits_program'))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('age_range')
                    ->label(__('academics::filament.course.fields.age_range'))
                    ->state(static fn (Course $record): string => self::ageRange($record))
                    ->toggleable(),

                TextColumn::make('total_sessions')
                    ->label(__('academics::filament.course.fields.total_sessions'))
                    ->numeric()
                    ->sortable()
                    ->toggleable(),

                IconColumn::make('is_active')
                    ->label(__('academics::filament.course.fields.is_active'))
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label(__('academics::filament.fields.created_at'))
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('program')
                    ->label(__('academics::filament.course.filters.program'))
                    ->options(fn (): array => self::programOptions())
                    ->query(static function (Builder $query, array $data): Builder {
                        $programId = $data['value'] ?? null;

                        return $programId === null || $programId === ''
                            ? $query
                            : $query->whereHas(
                                'level',
                                static fn (Builder $level): Builder => $level->where('program_id', $programId),
                            );
                    }),

                SelectFilter::make('level_id')
                    ->label(__('academics::filament.course.fields.level'))
                    ->options(fn (): array => self::levelOptions())
                    ->searchable(),

                SelectFilter::make('session_mode')
                    ->label(__('academics::filament.course.fields.session_mode'))
                    ->options(self::enumOptions(SessionMode::cases(), 'session_modes')),

                SelectFilter::make('target_gender')
                    ->label(__('academics::filament.course.fields.target_gender'))
                    ->options(self::enumOptions(TargetGender::cases(), 'target_genders')),

                TernaryFilter::make('is_active')
                    ->label(__('academics::filament.course.filters.active')),

                TrashedFilter::make()
                    ->label(__('academics::filament.course.filters.trashed')),
            ])
            ->recordActions([
                ViewAction::make()
                    ->visible(static fn (Course $record): bool => auth()->user()?->can('view', $record) === true),
                EditAction::make()
                    ->visible(static fn (Course $record): bool => auth()->user()?->can('update', $record) === true),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => CourseFilamentResource\Pages\ListCourses::route('/'),
            'create' => CourseFilamentResource\Pages\CreateCourse::route('/create'),
            'view' => CourseFilamentResource\Pages\ViewCourse::route('/{record}'),
            'edit' => CourseFilamentResource\Pages\EditCourse::route('/{record}/edit'),
        ];
    }

    /**
     * مستويات مؤسسة المستخدم فقط، معروضة كـ«برنامج — مستوى».
     *
     * الاعتماد على `relationship()` المجرّد كان يعرض مستويات كل المؤسسات،
     * ويعرض الكود وحده فلا يميّز المستخدم بين مستويات متشابهة الترميز.
     *
     * @return array<string, string>
     */
    private static function levelOptions(): array
    {
        $organizationId = data_get(auth()->user(), 'organization_id');

        if (!is_string($organizationId) || $organizationId === '') {
            return [];
        }

        return Level::query()
            ->whereHas(
                'program',
                static fn (Builder $program): Builder => $program
                    ->where('organization_id', $organizationId)
                    ->whereNull('deleted_at'),
            )
            ->with('program')
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(static fn (Level $level): array => [
                (string) $level->getKey() => sprintf(
                    '%s — %s',
                    LocalizedJsonColumn::display($level->program?->name),
                    LocalizedJsonColumn::display($level->name),
                ),
            ])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private static function programOptions(): array
    {
        $organizationId = data_get(auth()->user(), 'organization_id');

        if (!is_string($organizationId) || $organizationId === '') {
            return [];
        }

        return Program::query()
            ->where('organization_id', $organizationId)
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(static fn (Program $program): array => [
                (string) $program->getKey() => LocalizedJsonColumn::display($program->name),
            ])
            ->all();
    }

    /** @return array<string, string> */
    private static function categoryOptions(?string $levelId): array
    {
        $organizationId = data_get(auth()->user(), 'organization_id');
        if (!is_string($organizationId) || $organizationId === '') {
            return [];
        }

        $programId = $levelId === null ? null : Level::query()->whereKey($levelId)->value('program_id');

        return app(AcademicAdministrationQueryService::class)->categoryOptions(
            $organizationId,
            is_string($programId) ? $programId : null,
        );
    }

    /**
     * @param list<SessionMode|TargetGender> $cases
     * @return array<string, string>
     */
    private static function enumOptions(array $cases, string $translationKey): array
    {
        $options = [];

        foreach ($cases as $case) {
            $options[$case->value] = __('academics::filament.'.$translationKey.'.'.$case->value);
        }

        return $options;
    }

    private static function ageRange(Course $record): string
    {
        $from = $record->age_from;
        $to = $record->age_to;

        if ($from === null && $to === null) {
            return __('academics::filament.course.fields.any_age');
        }

        if ($from !== null && $to !== null) {
            return $from.'–'.$to;
        }

        return $from !== null
            ? __('academics::filament.course.fields.age_from_only', ['age' => $from])
            : __('academics::filament.course.fields.age_to_only', ['age' => $to]);
    }
}
