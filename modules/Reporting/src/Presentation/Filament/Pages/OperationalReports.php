<?php

declare(strict_types=1);

namespace Modules\Reporting\Presentation\Filament\Pages;

use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Attendance\Domain\Enums\AttendanceStatus;
use Modules\Reporting\Application\Services\OperationalReportCriteriaFactory;
use Modules\Reporting\Domain\Contracts\OperationalReportQuery;
use Modules\Reporting\Domain\Exceptions\InvalidReportCriteria;
use Modules\Reporting\Domain\ValueObjects\OperationalReportCriteria;
use Modules\Reporting\Domain\ValueObjects\OperationalReportData;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Throwable;

/** كتالوج تقارير الحصص: موجز، طالب، معلم، ومجموعة من نفس مصدر الحقيقة. */
final class OperationalReports extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?int $navigationSort = 1;

    protected string $view = 'reporting::filament.operational-reports';

    public bool $hasRun = false;

    private ?string $reportError = null;

    /** @var array<string, OperationalReportData> */
    private array $reportCache = [];

    /** @var array{students: array<string, string>, teachers: array<string, string>, groups: array<string, string>, courses: array<string, string>}|null */
    private ?array $optionsCache = null;

    public static function getNavigationGroup(): string
    {
        return __('reporting::navigation.group');
    }

    public static function getNavigationLabel(): string
    {
        return __('reporting::operational.navigation');
    }

    public function getTitle(): string
    {
        return __('reporting::operational.title');
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('report.view');
    }

    /** @return list<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('run_report')
                ->label(__('reporting::operational.actions.run_report'))
                ->icon('heroicon-o-play')
                ->color('primary')
                ->action('runReport'),
            Action::make('export_pdf')
                ->label(__('reporting::operational.actions.export_pdf'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn (): bool => $this->hasRun && (bool) auth()->user()?->can('report.export'))
                ->url(fn (): string => route('reporting.operational.export-pdf', $this->exportParameters()))
                ->openUrlInNewTab(),
        ];
    }

    public function runReport(): void
    {
        $this->hasRun = true;
        $this->reportCache = [];
        $this->reportError = null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(function (
                array $filters,
                ?string $sortColumn,
                ?string $sortDirection,
                ?string $search,
                int $page,
                int $recordsPerPage,
            ): LengthAwarePaginator {
                $report = $this->report($this->input($filters, $search));
                $records = collect($report->rowsAsArray())->keyBy('id');
                $sortColumn = in_array($sortColumn, [
                    'title', 'scheduled_start', 'scheduled_start_display', 'duration_minutes', 'course', 'group',
                    'actual_teacher', 'present_count', 'status', 'status_label', 'report_status', 'report_status_label',
                ], true) ? $sortColumn : 'scheduled_start';

                /** @var Collection<string, array<string, mixed>> $records */
                $records = $records->sortBy(
                    $sortColumn,
                    SORT_REGULAR,
                    ($sortDirection ?? 'desc') === 'desc',
                );

                return new LengthAwarePaginator(
                    $records->forPage($page, $recordsPerPage),
                    $records->count(),
                    $recordsPerPage,
                    $page,
                    ['pageName' => 'page'],
                );
            })
            ->columns([
                TextColumn::make('title')
                    ->label(__('reporting::operational.columns.session'))
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('scheduled_start_display')
                    ->label(__('reporting::operational.columns.scheduled_at'))
                    ->description(fn (array $record): string => (string) $record['scheduled_end_display'])
                    ->sortable(),
                TextColumn::make('duration_minutes')
                    ->label(__('reporting::operational.columns.duration'))
                    ->formatStateUsing(static fn (int $state): string => __('reporting::operational.minutes', ['count' => $state]))
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('course')
                    ->label(__('reporting::operational.columns.course'))
                    ->sortable()
                    ->wrap(),
                TextColumn::make('group')
                    ->label(__('reporting::operational.columns.group'))
                    ->sortable()
                    ->wrap(),
                TextColumn::make('actual_teacher')
                    ->label(__('reporting::operational.columns.teacher'))
                    ->description(static fn (array $record): ?string => $record['has_substitute']
                        ? __('reporting::operational.substitute', ['teacher' => $record['original_teacher']])
                        : null)
                    ->sortable()
                    ->wrap(),
                TextColumn::make('students_display')
                    ->label(__('reporting::operational.columns.students'))
                    ->description(static fn (array $record): string => (string) $record['attendance_summary'])
                    ->wrap(),
                TextColumn::make('status_label')
                    ->label(__('reporting::operational.columns.status'))
                    ->badge()
                    ->color(static fn (array $record): string => (string) $record['status_color'])
                    ->sortable(),
                TextColumn::make('report_status_label')
                    ->label(__('reporting::operational.columns.report_status'))
                    ->badge()
                    ->color(static fn (array $record): string => match ($record['report_status']) {
                        'submitted' => 'success', 'late' => 'warning', 'missing' => 'danger', default => 'gray',
                    })
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('session_type_label')
                    ->label(__('reporting::operational.columns.session_type'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('cancellation_reason')
                    ->label(__('reporting::operational.columns.cancellation_reason'))
                    ->placeholder(__('reporting::operational.not_available'))
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('period')
                    ->label(__('reporting::operational.filters.period'))
                    ->schema([
                        Select::make('preset')
                            ->label(__('reporting::operational.filters.preset'))
                            ->options($this->periodOptions())
                            ->default((string) config('reporting.operational.default_preset'))
                            ->required(),
                        DatePicker::make('from')
                            ->label(__('reporting::operational.filters.from'))
                            ->default(CarbonImmutable::now()->startOfWeek()->toDateString()),
                        DatePicker::make('until')
                            ->label(__('reporting::operational.filters.until'))
                            ->default(CarbonImmutable::now()->endOfWeek()->toDateString()),
                    ])
                    ->indicateUsing(fn (array $data): string => $this->periodOptions()[$data['preset'] ?? 'this_week']
                        ?? __('reporting::operational.periods.this_week')),
                SelectFilter::make('status')
                    ->label(__('reporting::operational.filters.status'))
                    ->multiple()
                    ->options(collect(SessionStatus::cases())->mapWithKeys(
                        static fn (SessionStatus $status): array => [$status->value => $status->label()],
                    )->all()),
                SelectFilter::make('attendance_status')
                    ->label(__('reporting::operational.filters.attendance_status'))
                    ->multiple()
                    ->options(collect(AttendanceStatus::cases())->mapWithKeys(
                        static fn (AttendanceStatus $status): array => [$status->value => $status->label()],
                    )->all()),
                SelectFilter::make('session_type')
                    ->label(__('reporting::operational.filters.session_type'))
                    ->multiple()
                    ->options($this->sessionTypeOptions()),
                SelectFilter::make('student_profile_id')
                    ->label(__('reporting::operational.filters.student'))
                    ->options(fn (): array => $this->options()['students'])
                    ->searchable(),
                SelectFilter::make('staff_profile_id')
                    ->label(__('reporting::operational.filters.teacher'))
                    ->options(fn (): array => $this->options()['teachers'])
                    ->searchable(),
                SelectFilter::make('original_staff_profile_id')
                    ->label(__('reporting::operational.filters.original_teacher'))
                    ->options(fn (): array => $this->options()['teachers'])
                    ->searchable(),
                SelectFilter::make('group_id')
                    ->label(__('reporting::operational.filters.group'))
                    ->options(fn (): array => $this->options()['groups'])
                    ->searchable(),
                SelectFilter::make('course_id')
                    ->label(__('reporting::operational.filters.course'))
                    ->options(fn (): array => $this->options()['courses'])
                    ->searchable(),
                SelectFilter::make('report_status')
                    ->label(__('reporting::operational.filters.report_status'))
                    ->options([
                        'submitted' => __('reporting::operational.report_status.submitted'),
                        'late' => __('reporting::operational.report_status.late'),
                        'missing' => __('reporting::operational.report_status.missing'),
                    ]),
            ])
            ->searchable()
            ->defaultSort('scheduled_start', direction: 'desc')
            ->defaultPaginationPageOption((int) config('reporting.operational.default_per_page'))
            ->paginationPageOptions((array) config('reporting.operational.per_page_options'))
            ->emptyStateHeading(__('reporting::operational.empty'));
    }

    /** @return list<array{key: string, label: string, value: int|float|string}> */
    public function getSummaryCards(): array
    {
        $summary = $this->report($this->currentInput())->summary;
        $keys = ['total', 'completed', 'cancelled', 'postponed', 'no_show', 'excused', 'students', 'teachers', 'groups', 'attendance_rate'];

        return array_map(static fn (string $key): array => [
            'key' => $key,
            'label' => __('reporting::operational.summary.'.$key),
            'value' => $key === 'attendance_rate'
                ? number_format((float) ($summary[$key] ?? 0), 1).'%'
                : (int) ($summary[$key] ?? 0),
        ], $keys);
    }

    public function getReportError(): ?string
    {
        return $this->reportError;
    }

    public function isReportLimitExceeded(): bool
    {
        return $this->report($this->currentInput())->limitExceeded;
    }

    /** @param array<string, mixed> $input */
    private function report(array $input): OperationalReportData
    {
        try {
            $criteria = app(OperationalReportCriteriaFactory::class)->fromInput($input, auth()->user());
            $key = $criteria->cacheKey();
            $this->reportError = null;

            return $this->reportCache[$key] ??= app(OperationalReportQuery::class)->run($criteria);
        } catch (InvalidReportCriteria $exception) {
            $this->reportError = $exception->getMessage();
        } catch (Throwable $exception) {
            report($exception);
            $this->reportError = __('reporting::messages.report_failed');
        }

        return $this->emptyReport();
    }

    /** @return array{students: array<string, string>, teachers: array<string, string>, groups: array<string, string>, courses: array<string, string>} */
    private function options(): array
    {
        if ($this->optionsCache !== null) {
            return $this->optionsCache;
        }

        try {
            $criteria = app(OperationalReportCriteriaFactory::class)->fromInput($this->currentInput(), auth()->user());

            return $this->optionsCache = app(OperationalReportQuery::class)->options($criteria);
        } catch (Throwable) {
            return $this->optionsCache = ['students' => [], 'teachers' => [], 'groups' => [], 'courses' => []];
        }
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function input(array $filters, ?string $search = null): array
    {
        $period = is_array($filters['period'] ?? null) ? $filters['period'] : [];

        return [
            'preset' => $period['preset'] ?? config('reporting.operational.default_preset'),
            'from' => $period['from'] ?? null,
            'until' => $period['until'] ?? null,
            'statuses' => data_get($filters, 'status.values', []),
            'attendance_statuses' => data_get($filters, 'attendance_status.values', []),
            'session_types' => data_get($filters, 'session_type.values', []),
            'student_profile_id' => data_get($filters, 'student_profile_id.value'),
            'staff_profile_id' => data_get($filters, 'staff_profile_id.value'),
            'original_staff_profile_id' => data_get($filters, 'original_staff_profile_id.value'),
            'group_id' => data_get($filters, 'group_id.value'),
            'course_id' => data_get($filters, 'course_id.value'),
            'report_status' => data_get($filters, 'report_status.value'),
            'search' => $search ?? '',
        ];
    }

    /** @return array<string, mixed> */
    private function currentInput(): array
    {
        return $this->input(is_array($this->tableFilters ?? null) ? $this->tableFilters : [], $this->getTableSearch());
    }

    /** @return array<string, mixed> */
    private function exportParameters(): array
    {
        try {
            return app(OperationalReportCriteriaFactory::class)
                ->fromInput($this->currentInput(), auth()->user())
                ->toQueryParameters();
        } catch (Throwable) {
            return ['preset' => config('reporting.operational.default_preset')];
        }
    }

    /** @return array<string, string> */
    private function periodOptions(): array
    {
        return collect(['today', 'yesterday', 'this_week', 'previous_week', 'this_month', 'custom'])
            ->mapWithKeys(static fn (string $key): array => [$key => __('reporting::operational.periods.'.$key)])
            ->all();
    }

    /** @return array<string, string> */
    private function sessionTypeOptions(): array
    {
        return collect(array_keys((array) config('academic.session_types', [])))
            ->mapWithKeys(static fn (string $type): array => [$type => __('sessions::session_types.'.$type)])
            ->all();
    }

    private function emptyReport(): OperationalReportData
    {
        $now = CarbonImmutable::now('UTC');
        $criteria = new OperationalReportCriteria('', $now, $now->addDay(), 'UTC', 'today', $now->toDateString(), $now->toDateString());

        return new OperationalReportData($criteria, [], [], false);
    }
}
