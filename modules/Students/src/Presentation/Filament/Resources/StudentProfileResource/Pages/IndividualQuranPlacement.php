<?php

declare(strict_types=1);

namespace Modules\Students\Presentation\Filament\Resources\StudentProfileResource\Pages;

use App\Application\Actions\BulkCreateIndividualQuranSchedulesAction;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\Students\Domain\Models\StudentProfile;
use Modules\Students\Presentation\Filament\Resources\StudentProfileResource;
use Shared\Support\BusinessRuleViolation;

final class IndividualQuranPlacement extends ListRecords
{
    protected static string $resource = StudentProfileResource::class;

    /** @var array<string, array<string, mixed>> */
    public array $placementRows = [];

    /** @var array<string, string>|null */
    private ?array $activeScheduleIds = null;

    /** @var array<string, string>|null */
    private ?array $teacherOptionsCache = null;

    public function mount(): void
    {
        parent::mount();

        $timezone = (string) (auth()->user()?->getAttribute('timezone') ?: config('app.timezone'));
        $startsOn = now($timezone)->toDateString();

        foreach ($this->placement()->eligibleStudentIds($this->organizationId()) as $studentId) {
            $this->placementRows[$studentId] = [
                'staff_profile_id' => '',
                'weekdays' => [],
                'duration_minutes' => (int) config('scheduling.default_individual_duration_minutes'),
                'start_time' => '',
                'interval_weeks' => 1,
                'timezone' => $timezone,
                'starts_on' => $startsOn,
                'ends_on' => '',
            ];
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
                    ->label(__('students::admin.individual_quran.weekdays'))
                    ->view('students::filament.tables.columns.individual-quran-field', ['field' => 'weekdays'])
                    ->extraCellAttributes(['style' => 'min-width: 15rem']),
                ViewColumn::make('placement_duration')
                    ->label(__('students::admin.individual_quran.duration'))
                    ->view('students::filament.tables.columns.individual-quran-field', ['field' => 'duration'])
                    ->extraCellAttributes(['style' => 'min-width: 8rem']),
                ViewColumn::make('placement_start_time')
                    ->label(__('students::admin.individual_quran.start_time'))
                    ->view('students::filament.tables.columns.individual-quran-field', ['field' => 'start_time'])
                    ->extraCellAttributes(['style' => 'min-width: 9rem']),
                ViewColumn::make('placement_period')
                    ->label(__('students::admin.individual_quran.period'))
                    ->view('students::filament.tables.columns.individual-quran-field', ['field' => 'period'])
                    ->extraCellAttributes(['style' => 'min-width: 11rem']),
                ViewColumn::make('placement_interval')
                    ->label(__('students::admin.individual_quran.interval_weeks'))
                    ->view('students::filament.tables.columns.individual-quran-field', ['field' => 'interval'])
                    ->extraCellAttributes(['style' => 'min-width: 8rem']),
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
            ->filters([])
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

        if ($field !== 'start_time' && isset($this->placementRows[$studentId])) {
            $this->placementRows[$studentId]['start_time'] = '';
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
        $validator = Validator::make($row, [
            'staff_profile_id' => ['required', 'string', Rule::in(array_keys($this->teacherOptions()))],
            'weekdays' => ['required', 'array', 'min:1'],
            'weekdays.*' => ['integer', Rule::in(range(0, 6))],
            'duration_minutes' => ['required', 'integer', Rule::in((array) config('scheduling.individual_session_durations'))],
            'start_time' => ['required', 'date_format:H:i'],
            'interval_weeks' => ['required', 'integer', 'between:1,'.$maxInterval],
            'timezone' => ['required', 'timezone'],
            'starts_on' => ['required', 'date', 'after_or_equal:today'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ], [
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
            'start_time' => __('students::admin.individual_quran.start_time'),
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

        try {
            $scheduleId = $this->placement()->executeSingle(
                organizationId: $this->organizationId(),
                studentProfileId: $studentId,
                staffProfileId: (string) $data['staff_profile_id'],
                weekdays: array_map('intval', (array) $data['weekdays']),
                intervalWeeks: (int) $data['interval_weeks'],
                durationMinutes: (int) $data['duration_minutes'],
                startTime: (string) $data['start_time'],
                timezone: (string) $data['timezone'],
                startsOn: (string) $data['starts_on'],
                endsOn: $this->nullableString($data['ends_on'] ?? null),
                actorId: (string) auth()->id(),
                reason: __('students::admin.individual_quran.placement_audit_reason'),
            );
        } catch (BusinessRuleViolation $violation) {
            $this->addError('placementRows.'.$studentId.'.start_time', $violation->getMessage());
            Notification::make()->title($violation->getMessage())->danger()->send();

            return;
        }

        $scheduleIds = $this->scheduleIds();
        $scheduleIds[$studentId] = $scheduleId;
        $this->activeScheduleIds = $scheduleIds;
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

    /** @return list<string> */
    public function availableTimesFor(string $studentId): array
    {
        $row = $this->placementRows[$studentId] ?? [];

        return $this->placement()->availableStartTimes(
            organizationId: $this->organizationId(),
            staffProfileId: $this->nullableString($row['staff_profile_id'] ?? null),
            weekdays: (array) ($row['weekdays'] ?? []),
            intervalWeeks: max(1, (int) ($row['interval_weeks'] ?? 1)),
            durationMinutes: (int) ($row['duration_minutes'] ?? config('scheduling.default_individual_duration_minutes')),
            timezone: (string) ($row['timezone'] ?? config('app.timezone')),
            startsOn: $this->nullableString($row['starts_on'] ?? null),
            endsOn: $this->nullableString($row['ends_on'] ?? null),
        );
    }

    public function isStudentScheduled(string $studentId): bool
    {
        return isset($this->scheduleIds()[$studentId]);
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
}
