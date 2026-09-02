<?php

declare(strict_types=1);

namespace Modules\Students\Presentation\Filament\Resources;

use App\Application\Actions\AssignStudentToGroupAction;
use App\Application\Queries\ProfileAdministrationQueryService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Organization\Domain\Contracts\GeographyQueries;
use Modules\Organization\Domain\ValueObjects\CountryData;
use Modules\Organization\Domain\ValueObjects\RegionData;
use Modules\Students\Application\Actions\AcceptRegistrationApplicationAction;
use Modules\Students\Application\Actions\RejectRegistrationApplicationAction;
use Modules\Students\Application\Actions\ReviewRegistrationApplicationAction;
use Modules\Students\Application\Actions\SubmitRegistrationApplicationAction;
use Modules\Students\Application\Queries\RegistrationApplicationFilterService;
use Modules\Students\Domain\Enums\RegistrationQuestionType;
use Modules\Students\Domain\Enums\RegistrationStatus;
use Modules\Students\Domain\Enums\StudentGender;
use Modules\Students\Domain\Models\RegistrationApplication;
use Modules\Students\Domain\Models\RegistrationForm;
use Modules\Students\Domain\ValueObjects\FilterableQuestionData;
use Modules\Students\Presentation\Filament\Resources\RegistrationApplicationResource\Pages;
use Modules\Students\Presentation\Filament\Resources\RegistrationApplicationResource\Support\BulkPlacementAction;
use Shared\Filament\RecordOriginGuide;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Locales;

/** شاشة مراجعة الطلبات؛ كل انتقال يمر عبر Application Action ولا يكتب Filament مباشرة. */
final class RegistrationApplicationResource extends Resource
{
    protected static ?string $model = RegistrationApplication::class;

    protected static ?string $slug = 'registration-applications';

    protected static ?int $navigationSort = 19;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-inbox-stack';

    // الخانة معلنة هنا لا في الصنف الأب: `$navigationParentItem` في Filament
    // مشتركة بين كل الموارد، فبلا إعادة إعلانها يدهس آخرُ إسناد ما قبله.
    // القيمة نفسها تُضبط مركزيًا في App\Filament\AdminNavigation.
    protected static ?string $navigationParentItem = null;

    public static function getNavigationGroup(): string
    {
        return __('students::filament.navigation_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('students::registration.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('students::registration.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('students::registration.plural_model_label');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * نطاق مؤسسة المستخدم دائمًا؛ غياب المؤسسة يغلق الاستعلام بدل كشف الجميع.
     *
     * @return Builder<RegistrationApplication>
     */
    public static function getEloquentQuery(): Builder
    {
        $organizationId = (string) data_get(auth()->user(), 'organization_id');
        /** @var Builder<RegistrationApplication> $query */
        $query = parent::getEloquentQuery();

        return $organizationId === ''
            ? $query->whereRaw('1 = 0')
            // تحميل مسبق لعمود كود الطالب — يمنع استعلامًا لكل صف في الجدول.
            : $query->forOrganization($organizationId)->with([
                'studentProfile:id,student_code',
                'registrationForm:id,slug,title',
            ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Placeholder::make('registration_source')
                ->label(__('students::registration.filters.registration_form'))
                ->content(fn (?RegistrationApplication $record): string => $record?->registrationForm?->localizedTitle()
                    ?? __('students::registration.filters.registration_form_unknown')),
            TextInput::make('full_name')
                ->label(__('students::attributes.full_name'))
                ->disabled(),
            DatePicker::make('date_of_birth')
                ->label(__('students::attributes.date_of_birth'))
                ->disabled(),
            Select::make('gender')
                ->label(__('students::attributes.gender'))
                ->options(self::genderOptions())
                ->disabled(),
            Select::make('country_id')
                ->label(__('students::attributes.country_id'))
                ->options(self::countryOptions())
                ->disabled(),
            Select::make('region_id')
                ->label(__('students::attributes.region_id'))
                ->options(self::regionOptions())
                ->disabled(),
            TextInput::make('email')
                ->label(__('students::attributes.email'))
                ->disabled(),
            TextInput::make('phone')
                ->label(__('students::attributes.phone'))
                ->disabled(),
            TextInput::make('preferred_program_id')
                ->label(__('students::attributes.preferred_program_id'))
                ->disabled(),
            TextInput::make('preferred_course_id')
                ->label(__('students::attributes.preferred_course_id'))
                ->disabled(),
            Textarea::make('notes')
                ->label(__('students::attributes.notes'))
                ->columnSpanFull()
                ->disabled(),
            Section::make(__('students::registration_questions.answers.section'))
                ->columnSpanFull()
                ->visible(fn (?RegistrationApplication $record): bool => $record !== null
                    && self::answersOf($record) !== [])
                ->components(fn (?RegistrationApplication $record): array => $record === null
                    ? []
                    : array_map(
                        static fn (array $answer): Component => Placeholder::make('answer_'.$answer['question_id'])
                            ->label((string) $answer['question'])
                            ->content(self::formatAnswer($answer['answer'] ?? ''))
                            ->columnSpan(1),
                        self::answersOf($record),
                    )),
            Textarea::make('decision_reason')
                ->label(__('students::attributes.decision_reason'))
                ->columnSpanFull()
                ->disabled(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return RecordOriginGuide::for(
            $table,
            'students::origin.application',
            'heroicon-o-inbox-stack',
            'filament.admin.resources.registration-forms.index',
        )
            ->columns([
                /*
                 * الكود المعروض للطالب لا الـULID. الطلب قبل القبول لا يملك
                 * ملفًا ولا كودًا بعد، فيُعرض بديل مترجم بدل معرّف داخلي.
                 */
                TextColumn::make('studentProfile.student_code')
                    ->label(__('students::attributes.student_code'))
                    ->placeholder(__('students::admin.common.not_available'))
                    ->searchable()
                    ->copyable(),
                TextColumn::make('full_name')
                    ->label(__('students::attributes.full_name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('registrationForm.slug')
                    ->label(__('students::registration.filters.registration_form'))
                    ->formatStateUsing(fn (mixed $state, RegistrationApplication $record): string => $record->registrationForm?->localizedTitle()
                        ?? __('students::registration.filters.registration_form_unknown')),
                TextColumn::make('status')
                    ->label(__('students::attributes.status'))
                    ->badge()
                    ->formatStateUsing(fn (RegistrationStatus $state): string => $state->label())
                    ->color(fn (RegistrationStatus $state): string => self::statusColor($state)),
                TextColumn::make('country_id')
                    ->label(__('students::attributes.country_id'))
                    ->formatStateUsing(fn (string $state): string => self::countryOptions()[$state] ?? $state),
                TextColumn::make('region_id')
                    ->label(__('students::attributes.region_id'))
                    ->formatStateUsing(fn (string $state): string => self::regionOptions()[$state] ?? $state),
                TextColumn::make('duplicate_of_application_id')
                    ->label(__('students::registration.duplicate'))
                    ->formatStateUsing(fn (?string $state): string => $state === null
                        ? __('students::registration.duplicate_no')
                        : __('students::registration.duplicate_yes')),
                TextColumn::make('submitted_at')
                    ->label(__('students::attributes.submitted_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters(self::tableFilters())
            ->filtersFormColumns(2)
            ->recordActions([
                self::submitAction(),
                self::reviewAction(),
                self::acceptAction(),
                self::rejectAction(),
                self::assignAction(),
            ])
            ->toolbarActions([
                BulkPlacementAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * لقطة إجابات التقييم كما خُزّنت في الطلب.
     *
     * @return list<array{question_id: string, question: string, type?: string, answer?: string|list<string>}>
     */
    private static function answersOf(RegistrationApplication $record): array
    {
        $answers = $record->evaluation_answers;

        return is_array($answers) ? array_values(array_filter($answers, 'is_array')) : [];
    }

    private static function formatAnswer(mixed $answer): string
    {
        if (is_array($answer)) {
            return implode('، ', array_values(array_filter($answer, 'is_string')));
        }

        return is_scalar($answer) ? (string) $answer : '';
    }

    /**
     * فلاتر شاشة التسجيلات.
     *
     * الثابتة منها تُبنى من البيانات المرجعية والـEnums؛ والديناميكية تأتي من
     * أسئلة النموذج التي سمحت الإدارة صراحة بالفلترة بها. كل تحويل استعلامي
     * يجري داخل `RegistrationApplicationFilterService` — لا SQL في هذه الشاشة.
     *
     * @return list<Filter|SelectFilter>
     */
    private static function tableFilters(): array
    {
        return [
            SelectFilter::make('registration_form_id')
                ->label(__('students::registration.filters.registration_form'))
                ->options(self::registrationFormOptions())
                ->searchable(),

            SelectFilter::make('status')
                ->label(__('students::registration.filters.status'))
                ->options(self::statusOptions())
                ->multiple(),

            SelectFilter::make('gender')
                ->label(__('students::attributes.gender'))
                ->options(self::genderOptions()),

            SelectFilter::make('country_id')
                ->label(__('students::registration.filters.country'))
                ->options(self::countryOptions())
                ->searchable(),

            // «المحافظة» — مرجع جغرافي بمعرّف، لا نص حر.
            SelectFilter::make('region_id')
                ->label(__('students::registration.filters.region'))
                ->options(self::regionOptions())
                ->searchable(),

            SelectFilter::make('preferred_language')
                ->label(__('students::registration.filters.language'))
                ->options(Locales::options('students::languages.'))
                ->multiple()
                ->query(fn (Builder $query, array $data): Builder => self::filters()->applyLanguage(
                    $query,
                    array_values(array_filter(
                        (array) ($data['values'] ?? []),
                        static fn (mixed $value): bool => is_string($value),
                    )),
                )),

            Filter::make('age_range')
                ->label(__('students::registration.filters.age_range'))
                ->schema([
                    TextInput::make('age_from')
                        ->label(__('students::registration.filters.age_from'))
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(120),
                    TextInput::make('age_to')
                        ->label(__('students::registration.filters.age_to'))
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(120)
                        ->gte('age_from'),
                ])
                ->query(fn (Builder $query, array $data): Builder => self::filters()->applyAgeRange(
                    $query,
                    self::boundedAge($data['age_from'] ?? null),
                    self::boundedAge($data['age_to'] ?? null),
                    self::viewerTimezone(),
                ))
                ->indicateUsing(fn (array $data): ?string => self::ageIndicator($data)),

            Filter::make('registered_at')
                ->label(__('students::registration.filters.registered_at'))
                ->schema([
                    DatePicker::make('registered_from')
                        ->label(__('students::registration.filters.registered_from')),
                    DatePicker::make('registered_until')
                        ->label(__('students::registration.filters.registered_until'))
                        ->afterOrEqual('registered_from'),
                ])
                ->query(fn (Builder $query, array $data): Builder => self::filters()->applySubmissionDateRange(
                    $query,
                    is_string($data['registered_from'] ?? null) ? $data['registered_from'] : null,
                    is_string($data['registered_until'] ?? null) ? $data['registered_until'] : null,
                    self::viewerTimezone(),
                )),

            ...self::dynamicQuestionFilters(),
        ];
    }

    /**
     * فلاتر أسئلة النموذج المسموح بها فقط.
     *
     * الأسئلة غير المدرجة لا يُبنى لها فلتر أصلًا، فمفتاح مزوَّر في الرابط لا
     * يقابله فلتر مسجَّل ويتجاهله Filament — الحماية بالبناء لا بالتحقق.
     *
     * @return list<Filter|SelectFilter>
     */
    private static function dynamicQuestionFilters(): array
    {
        $organizationId = self::organizationId();

        if ($organizationId === '') {
            return [];
        }

        return array_values(array_map(
            static fn (FilterableQuestionData $question): Filter|SelectFilter => match ($question->type) {
                RegistrationQuestionType::Select, RegistrationQuestionType::Radio => SelectFilter::make($question->filterKey())
                    ->label($question->label)
                    ->options(array_combine($question->options, $question->options) ?: [])
                    ->multiple()
                    ->query(fn (Builder $query, array $data): Builder => self::filters()->applySelectAnswer(
                        $query,
                        $question->id,
                        array_values(array_filter(
                            (array) ($data['values'] ?? []),
                            static fn (mixed $value): bool => is_string($value),
                        )),
                    )),
                default => Filter::make($question->filterKey())
                    ->label($question->label)
                    ->schema([
                        TextInput::make('from')
                            ->label(__('students::registration.filters.value_from'))
                            ->numeric(),
                        TextInput::make('until')
                            ->label(__('students::registration.filters.value_until'))
                            ->numeric(),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => self::filters()->applyNumberAnswerRange(
                        $query,
                        $question->id,
                        is_numeric($data['from'] ?? null) ? (float) $data['from'] : null,
                        is_numeric($data['until'] ?? null) ? (float) $data['until'] : null,
                    )),
            },
            app(RegistrationApplicationFilterService::class)->filterableQuestions($organizationId),
        ));
    }

    private static function filters(): RegistrationApplicationFilterService
    {
        return app(RegistrationApplicationFilterService::class);
    }

    /** @return array<string, string> */
    private static function registrationFormOptions(): array
    {
        $organizationId = self::organizationId();

        if ($organizationId === '') {
            return [];
        }

        return RegistrationForm::query()
            ->forOrganization($organizationId)
            ->orderBy('created_at')
            ->get()
            ->mapWithKeys(static fn (RegistrationForm $form): array => [
                (string) $form->getKey() => $form->localizedTitle(),
            ])
            ->all();
    }

    /** العمر يُقبل عددًا صحيحًا غير سالب فقط؛ ما عداه يُهمل الفلتر. */
    private static function boundedAge(mixed $value): ?int
    {
        if (!is_numeric($value)) {
            return null;
        }

        $age = (int) $value;

        return $age >= 0 && $age <= 120 ? $age : null;
    }

    /** @param array<string, mixed> $data */
    private static function ageIndicator(array $data): ?string
    {
        $from = self::boundedAge($data['age_from'] ?? null);
        $until = self::boundedAge($data['age_to'] ?? null);

        if ($from === null && $until === null) {
            return null;
        }

        return __('students::registration.filters.age_indicator', [
            'from' => $from ?? __('students::admin.common.not_available'),
            'to' => $until ?? __('students::admin.common.not_available'),
        ]);
    }

    /** التواريخ تُعرض وتُدخل بتوقيت المستخدم وتُخزَّن UTC. */
    private static function viewerTimezone(): string
    {
        $timezone = data_get(auth()->user(), 'timezone');

        return is_string($timezone) && $timezone !== '' ? $timezone : (string) config('app.timezone');
    }

    private static function submitAction(): Action
    {
        return Action::make('submit')
            ->label(__('students::registration.actions.submit'))
            ->visible(fn (RegistrationApplication $record): bool => $record->status === RegistrationStatus::Draft
                && (bool) auth()->user()?->can('submit', $record))
            ->action(function (RegistrationApplication $record): void {
                app(SubmitRegistrationApplicationAction::class)->execute($record);
                self::successNotification('submitted');
            });
    }

    private static function reviewAction(): Action
    {
        return Action::make('review')
            ->label(__('students::registration.actions.review'))
            ->visible(fn (RegistrationApplication $record): bool => $record->status === RegistrationStatus::Submitted
                && (bool) auth()->user()?->can('review', $record))
            ->action(function (RegistrationApplication $record): void {
                app(ReviewRegistrationApplicationAction::class)->execute($record, (string) auth()->id());
                self::successNotification('under_review');
            });
    }

    private static function acceptAction(): Action
    {
        return Action::make('accept')
            ->label(__('students::registration.actions.accept'))
            ->color('primary')
            ->requiresConfirmation()
            ->form([
                Textarea::make('reason')
                    ->label(__('students::attributes.decision_reason'))
                    ->required((bool) config('admission.application.acceptance_requires_reason', true))
                    ->maxLength(2000),
            ])
            ->visible(fn (RegistrationApplication $record): bool => in_array($record->status, [
                RegistrationStatus::Submitted,
                RegistrationStatus::UnderReview,
                RegistrationStatus::Accepted,
            ], true) && (bool) auth()->user()?->can('accept', $record))
            ->action(function (RegistrationApplication $record, array $data): void {
                app(AcceptRegistrationApplicationAction::class)->execute(
                    $record,
                    (string) auth()->id(),
                    (string) ($data['reason'] ?? ''),
                );
                self::successNotification('accepted');
            });
    }

    private static function rejectAction(): Action
    {
        return Action::make('reject')
            ->label(__('students::registration.actions.reject'))
            ->color('danger')
            ->modalHeading(__('students::registration.actions.reject_heading'))
            ->modalDescription(__('students::registration.actions.reject_description'))
            ->visible(fn (RegistrationApplication $record): bool => in_array($record->status, [
                RegistrationStatus::Submitted,
                RegistrationStatus::UnderReview,
            ], true) && (bool) auth()->user()?->can('reject', $record))
            ->form([
                Textarea::make('reason')
                    ->label(__('students::attributes.decision_reason'))
                    ->required((bool) config('admission.application.rejection_requires_reason', true))
                    ->maxLength(2000),
            ])
            ->action(function (RegistrationApplication $record, array $data): void {
                app(RejectRegistrationApplicationAction::class)->execute(
                    $record,
                    (string) ($data['reason'] ?? ''),
                    (string) auth()->id(),
                );
                self::successNotification('rejected');
            });
    }

    private static function successNotification(string $key): void
    {
        Notification::make()
            ->title(__('students::registration.messages.'.$key))
            ->success()
            ->send();
    }

    /** توزيع الطالب على مجموعة بعد القبول — عبر المنسق العام بحدود الموديولات. */
    private static function assignAction(): Action
    {
        return Action::make('assign')
            ->label(__('students::admin.placement.action'))
            ->color('primary')
            ->icon('heroicon-m-user-group')
            ->visible(fn (RegistrationApplication $record): bool => in_array($record->status, [
                RegistrationStatus::WaitingAssignment,
                RegistrationStatus::Assigned,
            ], true)
                && $record->student_profile_id !== null
                && (bool) auth()->user()?->can('assign', $record))
            ->form([
                Select::make('program_id')
                    ->label(__('students::admin.placement.program'))
                    ->options(fn (): array => app(ProfileAdministrationQueryService::class)->programOptions(self::organizationId()))
                    ->live()
                    ->afterStateUpdated(function (Set $set): void {
                        $set('course_id', null);
                        $set('group_id', null);
                    })
                    ->default(fn (RegistrationApplication $record): ?string => $record->preferred_program_id)
                    ->required(),
                Select::make('course_id')
                    ->label(__('students::admin.placement.course'))
                    ->options(fn (Get $get): array => app(ProfileAdministrationQueryService::class)->courseOptions(
                        self::organizationId(),
                        is_string($get('program_id')) ? $get('program_id') : null,
                    ))
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('group_id', null))
                    ->default(fn (RegistrationApplication $record): ?string => $record->preferred_course_id)
                    ->searchable()
                    ->required(),
                Select::make('group_id')
                    ->label(__('students::admin.placement.group'))
                    ->options(fn (Get $get): array => app(ProfileAdministrationQueryService::class)->placementGroupOptions(
                        self::organizationId(),
                        is_string($get('program_id')) ? $get('program_id') : null,
                        is_string($get('course_id')) ? $get('course_id') : null,
                    ))
                    ->searchable()
                    ->preload()
                    ->required(),
                Textarea::make('reason')
                    ->label(__('students::attributes.decision_reason'))
                    ->maxLength(2000)
                    ->required(),
            ])
            ->action(function (RegistrationApplication $record, array $data): void {
                try {
                    app(AssignStudentToGroupAction::class)->execute(
                        actorOrganizationId: self::organizationId(),
                        studentProfileId: (string) $record->student_profile_id,
                        programId: (string) $data['program_id'],
                        groupId: (string) $data['group_id'],
                        courseId: (string) $data['course_id'],
                        actorId: (string) auth()->id(),
                        correlationId: request()->header('X-Correlation-Id'),
                        reason: (string) $data['reason'],
                    );
                } catch (BusinessRuleViolation $violation) {
                    Notification::make()->title($violation->getMessage())->danger()->send();

                    return;
                }
                self::successNotification('assigned');
            });
    }

    private static function organizationId(): string
    {
        return (string) data_get(auth()->user(), 'organization_id');
    }

    /** @return array<string, string> */
    private static function statusOptions(): array
    {
        return collect(RegistrationStatus::cases())
            ->mapWithKeys(fn (RegistrationStatus $status): array => [$status->value => $status->label()])
            ->all();
    }

    /** @return array<string, string> */
    private static function genderOptions(): array
    {
        return collect(StudentGender::cases())
            ->mapWithKeys(fn (StudentGender $gender): array => [$gender->value => $gender->label()])
            ->all();
    }

    /** @return array<string, string> */
    private static function countryOptions(): array
    {
        /** @var GeographyQueries $geography */
        $geography = app(GeographyQueries::class);

        return collect($geography->countries())
            ->mapWithKeys(fn (CountryData $country): array => [
                $country->id => self::localizedName($country->name),
            ])
            ->all();
    }

    /** @return array<string, string> */
    private static function regionOptions(): array
    {
        /** @var GeographyQueries $geography */
        $geography = app(GeographyQueries::class);
        $options = [];

        foreach ($geography->countries() as $country) {
            foreach ($geography->regionsOf($country->id) as $region) {
                $options[$region->id] = self::localizedRegionName($country, $region);
            }
        }

        return $options;
    }

    /** @param array<string, string> $name */
    private static function localizedName(array $name): string
    {
        return $name[app()->getLocale()] ?? $name['ar'] ?? $name['en'] ?? (string) reset($name);
    }

    private static function localizedRegionName(CountryData $country, RegionData $region): string
    {
        return self::localizedName($country->name).' — '.self::localizedName($region->name);
    }

    private static function statusColor(RegistrationStatus $status): string
    {
        return match ($status) {
            RegistrationStatus::Draft => 'gray',
            RegistrationStatus::Submitted, RegistrationStatus::UnderReview => 'warning',
            RegistrationStatus::Accepted, RegistrationStatus::WaitingAssignment => 'info',
            RegistrationStatus::Assigned => 'success',
            RegistrationStatus::Rejected => 'danger',
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRegistrationApplications::route('/'),
            'view' => Pages\ViewRegistrationApplication::route('/{record}'),
        ];
    }
}
