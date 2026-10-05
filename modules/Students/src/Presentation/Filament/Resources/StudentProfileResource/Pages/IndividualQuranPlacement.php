<?php

declare(strict_types=1);

namespace Modules\Students\Presentation\Filament\Resources\StudentProfileResource\Pages;

use App\Application\Actions\BulkCreateIndividualQuranSchedulesAction;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\View\PanelsRenderHook;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\Identity\Domain\Contracts\DTOs\UserSummary;
use Modules\Identity\Domain\Contracts\UserQueryService;
use Modules\Students\Domain\Models\StudentProfile;
use Modules\Students\Presentation\Filament\Resources\StudentProfileResource;
use Shared\Support\BusinessRuleViolation;

final class IndividualQuranPlacement extends ListRecords
{
    protected static string $resource = StudentProfileResource::class;

    /** @var array<string, array<string, mixed>> */
    public array $placementRows = [];

    /** @var array<string, mixed> */
    public array $sharedSettings = [];

    /** @var array<string, string>|null */
    private ?array $activeScheduleIds = null;

    /** @var array<string, array<string, mixed>>|null */
    private ?array $activeScheduleSummaries = null;

    /** @var array<string, string>|null */
    private ?array $teacherOptionsCache = null;

    /** @var array<string, UserSummary> */
    private array $studentUserSummaries = [];

    public function mount(): void
    {
        parent::mount();

        $timezone = (string) (auth()->user()?->getAttribute('timezone') ?: config('app.timezone'));
        $startsOn = now($timezone)->toDateString();

        $this->sharedSettings = [
            'duration_minutes' => (int) config('scheduling.default_individual_duration_minutes'),
            'interval_weeks' => 1,
            'timezone' => $timezone,
            'starts_on' => $startsOn,
            'ends_on' => '',
            'selection_window_start' => (string) config('scheduling.individual_quran.selection_window_start'),
            'selection_window_end' => (string) config('scheduling.individual_quran.selection_window_end'),
        ];

        foreach ($this->placement()->eligibleStudentIds($this->organizationId()) as $studentId) {
            $this->placementRows[$studentId] = array_merge($this->sharedScheduleSettings(), [
                'staff_profile_id' => '',
                'weekdays' => [],
                'slot_times' => [],
                'use_custom_settings' => false,
            ]);
        }
    }

    public static function canAccess(array $parameters = []): bool
    {
        $user = auth()->user();

        return $user !== null
            && (bool) $user->can('student.view.any')
            && (bool) $user->can('schedule.manage');
    }

    public function getTitle(): string
    {
        return __('students::admin.individual_quran.page_title');
    }

    public function getSubheading(): string
    {
        return __('students::admin.individual_quran.page_description');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getTabsContentComponent()->columnSpanFull(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE)->columnSpanFull(),
                EmbeddedTable::make()->columnSpanFull(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER)->columnSpanFull(),
            ]);
    }

    /** @return array<string, Tab> */
    public function getTabs(): array
    {
        $all = $this->placement()->individualQuranStudentIds($this->organizationId());
        $scheduled = array_keys($this->scheduleIds());
        $pending = array_values(array_diff($all, $scheduled));
        $needsAttention = array_keys(array_filter(
            $this->scheduleSummaries(),
            static fn (array $summary): bool => ($summary['weekly_slots'] ?? []) === [],
        ));

        return [
            'all' => Tab::make(__('students::admin.individual_quran.tabs.all'))->badge(count($all)),
            'pending' => Tab::make(__('students::admin.individual_quran.tabs.pending'))
                ->badge(count($pending))
                ->query(fn (Builder $query): Builder => $this->whereIds($query, $pending)),
            'scheduled' => Tab::make(__('students::admin.individual_quran.tabs.scheduled'))
                ->badge(count($scheduled))
                ->query(fn (Builder $query): Builder => $this->whereIds($query, $scheduled)),
            'needs_attention' => Tab::make(__('students::admin.individual_quran.tabs.needs_attention'))
                ->badge(count($needsAttention))
                ->query(fn (Builder $query): Builder => $this->whereIds($query, $needsAttention)),
        ];
    }

    /** @return Builder<StudentProfile> */
    protected function getTableQuery(): Builder
    {
        $organizationId = (string) auth()->user()?->getAttribute('organization_id');
        $studentIds = $this->placement()->individualQuranStudentIds($organizationId);
        $query = StudentProfileResource::getEloquentQuery();

        return $studentIds === [] ? $query->whereRaw('1 = 0') : $query->whereKey($studentIds);
    }

    public function table(Table $table): Table
    {
        return $table
            ->header(view('students::filament.tables.individual-quran-shared-settings'))
            ->columns([
                TextColumn::make('student_code')
                    ->label(__('students::attributes.student_code'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('registrationApplication.full_name')
                    ->label(__('students::filament.student_name'))
                    ->searchable(),
                TextColumn::make('individual_quran_status')
                    ->label(__('students::admin.individual_quran.status'))
                    ->state(fn (StudentProfile $record): string => $this->isStudentScheduled((string) $record->getKey())
                        ? __('students::admin.individual_quran.status_scheduled')
                        : __('students::admin.individual_quran.status_pending'))
                    ->badge()
                    ->color(fn (StudentProfile $record): string => $this->isStudentScheduled((string) $record->getKey())
                        ? 'success'
                        : 'gray'),
                ViewColumn::make('placement_teacher')
                    ->label(__('students::admin.individual_quran.teacher'))
                    ->view('students::filament.tables.columns.individual-quran-field', ['field' => 'teacher'])
                    ->extraCellAttributes(['style' => 'min-width: 13rem']),
                ViewColumn::make('placement_weekdays')
                    ->label(__('students::admin.individual_quran.days_and_times'))
                    ->view('students::filament.tables.columns.individual-quran-field', ['field' => 'weekdays'])
                    ->extraCellAttributes(['style' => 'min-width: 28rem']),
            ])
            ->recordActions([
                Action::make('save_individual_quran_placement')
                    ->label(__('students::admin.individual_quran.save_action'))
                    ->tooltip(__('students::admin.individual_quran.save_action'))
                    ->icon('heroicon-o-check-circle')
                    ->color('primary')
                    ->visible(fn (StudentProfile $record): bool => !$this->isStudentScheduled((string) $record->getKey()))
                    ->action(function (StudentProfile $record): void {
                        $this->savePlacement((string) $record->getKey());
                    }),
                Action::make('edit_individual_quran_placement')
                    ->label(__('students::admin.individual_quran.edit_action'))
                    ->tooltip(__('students::admin.individual_quran.edit_action'))
                    ->icon('heroicon-o-pencil-square')
                    ->iconButton()
                    ->color('success')
                    ->visible(fn (StudentProfile $record): bool => $this->isStudentScheduled((string) $record->getKey()))
                    ->url(fn (StudentProfile $record): string => $this->editScheduleUrl($record)),
            ])
            ->recordActionsColumnLabel(__('students::admin.individual_quran.actions'))
            ->toolbarActions([])
            ->filters([
                SelectFilter::make('individual_quran_teacher')
                    ->label(__('students::admin.individual_quran.filter_teacher'))
                    ->options(fn (): array => $this->teacherOptions())
                    ->query(function (Builder $query, array $data): Builder {
                        $teacherId = $data['value'] ?? null;
                        if (!is_string($teacherId) || $teacherId === '') {
                            return $query;
                        }

                        $studentIds = array_keys(array_filter(
                            $this->scheduleSummaries(),
                            static fn (array $summary): bool => ($summary['staff_profile_id'] ?? null) === $teacherId,
                        ));

                        return $this->whereIds($query, $studentIds);
                    }),
            ])
            ->selectable(false)
            ->recordClasses(fn (StudentProfile $record): ?string => $this->isStudentScheduled((string) $record->getKey())
                ? '!bg-success-50 dark:!bg-success-950/30'
                : null)
            ->recordAction(null)
            ->recordUrl(null);
    }

    public function updatedPlacementRows(mixed $value, ?string $key = null): void
    {
        if (!is_string($key) || !str_contains($key, '.')) {
            return;
        }

        [$studentId, $field] = explode('.', $key, 2);
        $this->resetErrorBag('placementRows.'.$key);

        if ($field === 'use_custom_settings' && !(bool) $value && isset($this->placementRows[$studentId])) {
            $this->placementRows[$studentId] = array_merge(
                $this->placementRows[$studentId],
                $this->sharedScheduleSettings(),
                ['slot_times' => []],
            );

            return;
        }

        if (
            !isset($this->placementRows[$studentId])
            || $field === 'slot_times'
            || str_starts_with($field, 'slot_times.')
        ) {
            return;
        }

        if (str_starts_with($field, 'weekdays')) {
            $selected = array_map('intval', (array) ($this->placementRows[$studentId]['weekdays'] ?? []));
            $times = (array) ($this->placementRows[$studentId]['slot_times'] ?? []);
            $this->placementRows[$studentId]['slot_times'] = array_filter(
                $times,
                static fn (mixed $time, int|string $day): bool => in_array((int) $day, $selected, true),
                ARRAY_FILTER_USE_BOTH,
            );

            return;
        }

        $this->placementRows[$studentId]['slot_times'] = [];
    }

    public function updatedSharedSettings(mixed $value, ?string $key = null): void
    {
        if (!is_string($key)) {
            return;
        }

        $this->resetErrorBag('sharedSettings.'.$key);
        foreach ($this->placementRows as $studentId => $row) {
            if ((bool) ($row['use_custom_settings'] ?? false)) {
                continue;
            }

            if (array_key_exists($key, $this->sharedScheduleSettings())) {
                $this->placementRows[$studentId][$key] = $value;
            }
            $this->placementRows[$studentId]['slot_times'] = [];
        }
    }

    public function savePlacement(string $studentId): void
    {
        if ($this->isStudentScheduled($studentId)) {
            return;
        }

        $row = $this->placementRows[$studentId] ?? [];
        $this->resetErrorBag();
        $maxInterval = (int) config('scheduling.individual_quran.max_interval_weeks');
        $rules = [
            'staff_profile_id' => ['required', 'string', Rule::in(array_keys($this->teacherOptions()))],
            'weekdays' => ['required', 'array', 'min:1'],
            'weekdays.*' => ['integer', Rule::in(range(0, 6))],
            'slot_times' => ['required', 'array'],
            'duration_minutes' => ['required', 'integer', Rule::in((array) config('scheduling.individual_session_durations'))],
            'interval_weeks' => ['required', 'integer', 'between:1,'.$maxInterval],
            'timezone' => ['required', 'timezone'],
            'starts_on' => ['required', 'date', 'after_or_equal:today'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ];
        foreach (array_map('intval', (array) ($row['weekdays'] ?? [])) as $weekday) {
            $rules['slot_times.'.$weekday] = ['required', 'date_format:H:i'];
        }

        $validator = Validator::make($row, $rules, [
            'required' => __('students::admin.individual_quran.validation.required'),
            'array' => __('students::admin.individual_quran.validation.invalid'),
            'integer' => __('students::admin.individual_quran.validation.invalid'),
            'in' => __('students::admin.individual_quran.validation.invalid'),
            'min' => __('students::admin.individual_quran.validation.required'),
            'between' => __('students::admin.individual_quran.validation.invalid'),
            'timezone' => __('students::admin.individual_quran.validation.invalid'),
            'date' => __('students::admin.individual_quran.validation.invalid'),
            'date_format' => __('students::admin.individual_quran.validation.invalid'),
            'after_or_equal' => __('students::admin.individual_quran.validation.date_order'),
        ], [
            'staff_profile_id' => __('students::admin.individual_quran.teacher'),
            'weekdays' => __('students::admin.individual_quran.weekdays'),
            'duration_minutes' => __('students::admin.individual_quran.duration'),
            'slot_times' => __('students::admin.individual_quran.days_and_times'),
            'interval_weeks' => __('students::admin.individual_quran.interval_weeks'),
            'timezone' => __('students::admin.individual_quran.timezone'),
            'starts_on' => __('students::admin.individual_quran.starts_on'),
            'ends_on' => __('students::admin.individual_quran.ends_on'),
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->messages() as $field => $messages) {
                $this->addError('placementRows.'.$studentId.'.'.$field, $messages[0]);
            }

            Notification::make()
                ->title(__('students::admin.individual_quran.validation.fix_fields'))
                ->danger()
                ->send();

            return;
        }

        /** @var array<string, mixed> $data */
        $data = $validator->validated();
        $weeklySlots = collect((array) $data['weekdays'])
            ->map(fn (mixed $weekday): array => [
                'weekday' => (int) $weekday,
                'start_time' => (string) $data['slot_times'][(int) $weekday],
            ])
            ->values()
            ->all();

        try {
            $scheduleId = $this->placement()->executeSingle(
                organizationId: $this->organizationId(),
                studentProfileId: $studentId,
                staffProfileId: (string) $data['staff_profile_id'],
                weeklySlots: $weeklySlots,
                intervalWeeks: (int) $data['interval_weeks'],
                durationMinutes: (int) $data['duration_minutes'],
                timezone: (string) $data['timezone'],
                startsOn: (string) $data['starts_on'],
                endsOn: $this->nullableString($data['ends_on'] ?? null),
                actorId: (string) auth()->id(),
                reason: __('students::admin.individual_quran.placement_audit_reason'),
            );
        } catch (BusinessRuleViolation $violation) {
            $weekday = $violation->context['weekday'] ?? null;
            $field = is_int($weekday) ? 'slot_times.'.$weekday : 'slot_times';
            $this->addError('placementRows.'.$studentId.'.'.$field, $violation->getMessage());
            Notification::make()->title($violation->getMessage())->danger()->send();

            return;
        }

        $scheduleIds = $this->scheduleIds();
        $scheduleIds[$studentId] = $scheduleId;
        $this->activeScheduleIds = $scheduleIds;
        $this->activeScheduleSummaries = null;
        unset($this->placementRows[$studentId]);
        $this->resetTable();

        Notification::make()
            ->title(__('students::admin.individual_quran.save_success'))
            ->success()
            ->send();
    }

    /** @return array<string, string> */
    public function teacherOptions(): array
    {
        return $this->teacherOptionsCache ??= $this->placement()->teacherOptions($this->organizationId());
    }

    /** @return array<int, string> */
    public function weekdayOptions(): array
    {
        return collect(range(0, 6))->mapWithKeys(static fn (int $day): array => [
            $day => __('scheduling::filament.schedule.weekdays.'.$day),
        ])->all();
    }

    /** @return array<int, string> */
    public function durationOptions(): array
    {
        return collect((array) config('scheduling.individual_session_durations'))
            ->mapWithKeys(static fn (mixed $minutes): array => [
                (int) $minutes => __('scheduling::filament.schedule.minutes', ['minutes' => $minutes]),
            ])->all();
    }

    public function timeLabel(string $time): string
    {
        $parsed = CarbonImmutable::createFromFormat('H:i', $time, 'UTC');

        return $parsed === null ? $time : $parsed->translatedFormat('g:i A');
    }

    /** @return array{times: list<string>, confirmed: bool} */
    public function availabilityForDay(string $studentId, int $weekday): array
    {
        $row = $this->placementRows[$studentId] ?? [];

        $details = $this->placement()->availabilityDetails(
            organizationId: $this->organizationId(),
            staffProfileId: $this->nullableString($row['staff_profile_id'] ?? null),
            weekdays: [$weekday],
            intervalWeeks: max(1, (int) ($row['interval_weeks'] ?? 1)),
            durationMinutes: (int) ($row['duration_minutes'] ?? config('scheduling.default_individual_duration_minutes')),
            timezone: (string) ($row['timezone'] ?? config('app.timezone')),
            startsOn: $this->nullableString($row['starts_on'] ?? null),
            endsOn: $this->nullableString($row['ends_on'] ?? null),
        );

        $windowStart = (string) ($this->sharedSettings['selection_window_start'] ?? '00:00');
        $windowEnd = (string) ($this->sharedSettings['selection_window_end'] ?? '23:59');
        $times = array_values(array_filter(
            $details['available_start_times'],
            static fn (string $time): bool => $time >= $windowStart && $time <= $windowEnd,
        ));

        return [
            'times' => $times,
            'confirmed' => $details['has_declared_availability'],
        ];
    }

    public function studentTimezone(StudentProfile $student): string
    {
        $this->loadStudentUserSummaries();
        $userId = (string) $student->user_id;

        return $this->studentUserSummaries[$userId]->timezone ?? (string) config('app.timezone');
    }

    /**
     * @param array<string, mixed> $slot
     * @param array<string, mixed> $summary
     */
    public function studentSlotLabel(StudentProfile $student, array $slot, array $summary): ?string
    {
        $scheduleTimezone = (string) ($summary['timezone'] ?? config('app.timezone'));
        $studentTimezone = $this->studentTimezone($student);
        if ($studentTimezone === $scheduleTimezone) {
            return null;
        }

        $startsOn = (string) ($summary['starts_on'] ?? now($scheduleTimezone)->toDateString());
        $weekday = (int) ($slot['weekday'] ?? 0);
        $date = CarbonImmutable::parse($startsOn, $scheduleTimezone);
        $daysAhead = ($weekday - $date->dayOfWeek + 7) % 7;
        $local = $date->addDays($daysAhead)->setTimeFromTimeString((string) ($slot['start_time'] ?? '00:00'));
        $studentLocal = $local->setTimezone($studentTimezone);

        return __('students::admin.individual_quran.student_time', [
            'day' => $studentLocal->translatedFormat('l'),
            'time' => $studentLocal->translatedFormat('g:i A'),
            'timezone' => $studentTimezone,
        ]);
    }

    public function isStudentScheduled(string $studentId): bool
    {
        return isset($this->scheduleIds()[$studentId]);
    }

    /** @return array<string, mixed> */
    public function scheduleSummary(string $studentId): array
    {
        return $this->scheduleSummaries()[$studentId] ?? [];
    }

    private function editScheduleUrl(StudentProfile $student): string
    {
        $scheduleId = $this->scheduleIds()[(string) $student->getKey()] ?? null;

        return $scheduleId === null
            ? '#'
            : route('filament.admin.resources.schedules.edit', ['record' => $scheduleId]);
    }

    /** @return array<string, string> */
    private function scheduleIds(): array
    {
        return $this->activeScheduleIds ??= $this->placement()->activeScheduleIdsByStudent(
            (string) auth()->user()?->getAttribute('organization_id'),
        );
    }

    /** @return array<string, array<string, mixed>> */
    private function scheduleSummaries(): array
    {
        return $this->activeScheduleSummaries ??= $this->placement()->activeScheduleSummariesByStudent(
            $this->organizationId(),
        );
    }

    private function placement(): BulkCreateIndividualQuranSchedulesAction
    {
        return app(BulkCreateIndividualQuranSchedulesAction::class);
    }

    private function organizationId(): string
    {
        $organizationId = auth()->user()?->getAttribute('organization_id');
        abort_unless(is_string($organizationId) && $organizationId !== '', 403);

        return $organizationId;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function loadStudentUserSummaries(): void
    {
        if ($this->studentUserSummaries !== []) {
            return;
        }

        $studentIds = array_keys($this->scheduleSummaries());
        if ($studentIds === []) {
            return;
        }

        $userIds = StudentProfile::query()
            ->forOrganization($this->organizationId())
            ->whereKey($studentIds)
            ->pluck('user_id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->values()
            ->all();
        $this->studentUserSummaries = app(UserQueryService::class)->summariesByIds($userIds);
    }

    /** @return array<string, mixed> */
    private function sharedScheduleSettings(): array
    {
        return collect($this->sharedSettings)
            ->only(['duration_minutes', 'interval_weeks', 'timezone', 'starts_on', 'ends_on'])
            ->all();
    }

    /**
     * @param Builder<StudentProfile> $query
     * @param list<string> $ids
     * @return Builder<StudentProfile>
     */
    private function whereIds(Builder $query, array $ids): Builder
    {
        return $ids === [] ? $query->whereRaw('1 = 0') : $query->whereKey($ids);
    }
}
