<?php

declare(strict_types=1);

namespace Modules\Scheduling\Presentation\Filament\Resources;

use Carbon\CarbonImmutable;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Modules\Scheduling\Application\Queries\SchedulingAdministrationQueryService;
use Modules\Scheduling\Application\Services\TeacherAvailabilityPlanner;
use Modules\Scheduling\Domain\Models\Schedule;
use Modules\Scheduling\Domain\ValueObjects\WeeklyRecurrence;
use Modules\Scheduling\Presentation\Filament\Resources\ScheduleResource\Pages;
use Shared\Concerns\ScopesFilamentToOrganization;

final class ScheduleResource extends Resource
{
    use ScopesFilamentToOrganization;

    protected static ?string $model = Schedule::class;

    protected static ?string $slug = 'schedules';

    protected static ?int $navigationSort = 30;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-table-cells';

    public static function getNavigationGroup(): string
    {
        return __('scheduling::filament.group');
    }

    public static function getNavigationLabel(): string
    {
        return __('scheduling::filament.schedule.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('scheduling::filament.schedule.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('scheduling::filament.schedule.plural');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', Schedule::class) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create', Schedule::class) ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('scheduling::filament.schedule.sections.target'))
                ->description(__('scheduling::filament.schedule.sections.target_description'))
                ->icon('heroicon-o-academic-cap')
                ->iconColor('primary')
                ->collapsible()
                ->columnSpanFull()
                ->schema([
                    Grid::make(1)->schema([
                        Select::make('target_type')
                            ->label(__('scheduling::filament.schedule.fields.target_type'))
                            ->options([
                                'group' => __('scheduling::filament.schedule.targets.group'),
                                'student' => __('scheduling::filament.schedule.targets.student'),
                            ])
                            ->default('group')
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('group_id', null);
                                $set('student_profile_id', null);
                                $set('course_id', null);
                                $set('staff_profile_id', null);
                                $set('start_time', null);
                            })
                            ->required(),
                        Select::make('group_id')
                            ->label(__('scheduling::filament.schedule.fields.group'))
                            ->options(fn (): array => self::queries()->groupOptions(self::organizationId()))
                            ->visible(fn (Get $get): bool => $get('target_type') === 'group')
                            ->required(fn (Get $get): bool => $get('target_type') === 'group')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('course_id', null);
                                $set('staff_profile_id', null);
                            }),
                        Select::make('course_id')
                            ->label(__('scheduling::filament.schedule.fields.course'))
                            ->options(fn (Get $get): array => self::queries()->courseOptions(
                                self::organizationId(),
                                $get('target_type') === 'group' && is_string($get('group_id'))
                                    ? $get('group_id')
                                    : null,
                                is_string($get('target_type')) ? $get('target_type') : null,
                            ))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('staff_profile_id', null);
                                $set('student_profile_id', null);
                                $set('start_time', null);
                            })
                            ->required(),
                        Select::make('student_profile_id')
                            ->label(__('scheduling::filament.schedule.fields.student'))
                            ->options(fn (Get $get): array => self::queries()->studentOptions(
                                self::organizationId(),
                                is_string($get('course_id')) ? $get('course_id') : null,
                            ))
                            ->visible(fn (Get $get): bool => $get('target_type') === 'student')
                            ->required(fn (Get $get): bool => $get('target_type') === 'student')
                            ->searchable()
                            ->preload(),
                        Select::make('staff_profile_id')
                            ->label(__('scheduling::filament.schedule.fields.teacher'))
                            ->options(fn (Get $get): array => self::queries()->teacherOptions(
                                self::organizationId(),
                                $get('target_type') === 'group' && is_string($get('group_id')) ? $get('group_id') : null,
                                is_string($get('course_id')) ? $get('course_id') : null,
                            ))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn (Set $set): mixed => $set('start_time', null))
                            ->required(),
                    ]),
                ]),
            Section::make(__('scheduling::filament.schedule.sections.recurrence'))
                ->description(__('scheduling::filament.schedule.sections.recurrence_description'))
                ->icon('heroicon-o-calendar-days')
                ->iconColor('success')
                ->collapsible()
                ->columnSpanFull()
                ->schema([
                    CheckboxList::make('weekdays')
                        ->label(__('scheduling::filament.schedule.fields.weekdays'))
                        ->options(self::weekdayOptions())
                        ->columns(4)
                        ->live()
                        ->afterStateUpdated(fn (Set $set): mixed => $set('start_time', null))
                        ->required()
                        ->columnSpanFull(),
                    Grid::make(1)->schema([
                        TextInput::make('interval_weeks')
                            ->label(__('scheduling::filament.schedule.fields.interval_weeks'))
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(12)
                            ->default(1)
                            ->live()
                            ->afterStateUpdated(fn (Set $set): mixed => $set('start_time', null))
                            ->required(),
                        Select::make('start_time')
                            ->label(__('scheduling::filament.schedule.fields.start_time'))
                            ->options(fn (Get $get, ?Schedule $record): array => self::availableStartTimeOptions($get, $record))
                            ->placeholder(__('scheduling::filament.schedule.availability.choose_available_time'))
                            ->helperText(__('scheduling::filament.schedule.availability.booked_times_hidden'))
                            ->searchable()
                            ->native(false)
                            ->live()
                            ->required(),
                        Select::make('duration_minutes')
                            ->label(__('scheduling::filament.schedule.fields.duration'))
                            ->options(fn (Get $get): array => collect(self::durationChoices(
                                is_string($get('target_type')) ? $get('target_type') : null,
                            ))
                                ->mapWithKeys(static fn (mixed $minutes): array => [
                                    (int) $minutes => __('scheduling::filament.schedule.minutes', ['minutes' => $minutes]),
                                ])->all())
                            ->default(fn (Get $get): int => self::defaultDuration(
                                is_string($get('target_type')) ? $get('target_type') : null,
                            ))
                            ->live()
                            ->afterStateUpdated(fn (Set $set): mixed => $set('start_time', null))
                            ->required(),
                    ]),
                    Grid::make(1)->schema([
                        Select::make('timezone')
                            ->label(__('scheduling::filament.schedule.fields.timezone'))
                            ->options(array_combine(timezone_identifiers_list(), timezone_identifiers_list()))
                            ->default(fn (): string => (string) (auth()->user()?->getAttribute('timezone') ?? config('app.timezone')))
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(fn (Set $set): mixed => $set('start_time', null))
                            ->required(),
                        DatePicker::make('starts_on')
                            ->label(__('scheduling::filament.schedule.fields.starts_on'))
                            ->default(now()->toDateString())
                            ->live()
                            ->afterStateUpdated(fn (Set $set): mixed => $set('start_time', null))
                            ->required(),
                        DatePicker::make('ends_on')
                            ->label(__('scheduling::filament.schedule.fields.ends_on'))
                            ->live()
                            ->afterStateUpdated(fn (Set $set): mixed => $set('start_time', null))
                            ->afterOrEqual('starts_on'),
                    ]),
                    Placeholder::make('availability_overview')
                        ->label(__('scheduling::filament.schedule.availability.title'))
                        ->content(fn (Get $get, ?Schedule $record): HtmlString => self::availabilitySummary($get, $record))
                        ->columnSpanFull(),
                ]),
            Section::make(__('scheduling::filament.schedule.sections.governance'))
                ->description(__('scheduling::filament.schedule.sections.governance_description'))
                ->icon('heroicon-o-shield-check')
                ->iconColor('warning')
                ->collapsible()
                ->columnSpanFull()
                ->schema([
                    Textarea::make('reason')
                        ->label(__('scheduling::filament.schedule.fields.reason'))
                        ->helperText(__('scheduling::filament.schedule.fields.reason_help'))
                        ->required()
                        ->maxLength(1000)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('target')
                    ->label(__('scheduling::filament.schedule.fields.target'))
                    ->state(fn (Schedule $record): string => $record->group_id !== null
                        ? self::queries()->groupLabel((string) $record->organization_id, (string) $record->group_id)
                        : self::queries()->studentLabel((string) $record->organization_id, $record->student_profile_id))
                    ->searchable(false),
                TextColumn::make('course_id')
                    ->label(__('scheduling::filament.schedule.fields.course'))
                    ->formatStateUsing(fn (mixed $state, Schedule $record): string => self::queries()->courseLabel(
                        (string) $record->organization_id,
                        (string) $state,
                    )),
                TextColumn::make('staff_profile_id')
                    ->label(__('scheduling::filament.schedule.fields.teacher'))
                    ->formatStateUsing(fn (mixed $state, Schedule $record): string => self::queries()->teacherLabel(
                        (string) $record->organization_id,
                        (string) $state,
                    )),
                TextColumn::make('recurrence')
                    ->label(__('scheduling::filament.schedule.fields.recurrence'))
                    ->state(fn (Schedule $record): string => self::recurrenceLabel($record)),
                TextColumn::make('start_time')
                    ->label(__('scheduling::filament.schedule.fields.start_time')),
                TextColumn::make('duration_minutes')
                    ->label(__('scheduling::filament.schedule.fields.duration'))
                    ->suffix(' '.__('scheduling::filament.schedule.minute_short')),
                TextColumn::make('is_active')
                    ->label(__('scheduling::filament.schedule.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state
                        ? __('scheduling::filament.schedule.status.active')
                        : __('scheduling::filament.schedule.status.inactive'))
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                TextColumn::make('materialized_until')
                    ->label(__('scheduling::filament.schedule.fields.materialized_until'))
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('is_active')
                    ->label(__('scheduling::filament.schedule.fields.status'))
                    ->options([
                        1 => __('scheduling::filament.schedule.status.active'),
                        0 => __('scheduling::filament.schedule.status.inactive'),
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->visible(fn (Schedule $record): bool => $record->is_active),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /** @return array<string, mixed> */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSchedules::route('/'),
            'create' => Pages\CreateSchedule::route('/create'),
            'view' => Pages\ViewSchedule::route('/{record}'),
            'edit' => Pages\EditSchedule::route('/{record}/edit'),
        ];
    }

    /** @return array<int, string> */
    public static function weekdayOptions(): array
    {
        return collect(range(0, 6))->mapWithKeys(static fn (int $day): array => [
            $day => __('scheduling::filament.schedule.weekdays.'.$day),
        ])->all();
    }

    /** @return array<string, string> */
    private static function availableStartTimeOptions(Get $get, ?Schedule $record): array
    {
        $overview = self::availabilityOverview($get, $record);

        return collect($overview['available_start_times'])
            ->mapWithKeys(static fn (string $time): array => [$time => $time])
            ->all();
    }

    private static function availabilitySummary(Get $get, ?Schedule $record): HtmlString
    {
        $overview = self::availabilityOverview($get, $record);

        return self::renderAvailabilitySummary(
            $overview,
            $get('target_type') === 'student',
            is_string($get('staff_profile_id')) && (array) $get('weekdays') !== [],
        );
    }

    /** @param array<string, mixed> $data */
    private static function renderAvailabilitySummary(array $data, bool $individual, bool $ready): HtmlString
    {
        if (!$ready) {
            return new HtmlString(e(__('scheduling::filament.schedule.availability.select_details')));
        }

        $message = __('scheduling::filament.schedule.availability.overview', [
            'available' => count($data['available_start_times']),
            'booked' => count($data['booked_sessions']),
            'planned' => $data['total_occurrences'],
        ]);
        if ($individual && !$data['has_declared_availability']) {
            $message = __('scheduling::filament.schedule.availability.no_declared').' '.$message;
        }

        return new HtmlString(e($message));
    }

    /**
     * @return array{
     *   available_start_times: list<string>,
     *   booked_sessions: list<array{start: CarbonImmutable, end: CarbonImmutable}>,
     *   planned_occurrences: list<array{start: CarbonImmutable, end: CarbonImmutable, available: bool}>,
     *   total_occurrences: int,
     *   has_declared_availability: bool
     * }
     */
    private static function availabilityOverview(Get $get, ?Schedule $record): array
    {
        $targetType = is_string($get('target_type')) ? $get('target_type') : 'group';

        return app(TeacherAvailabilityPlanner::class)->overview(
            organizationId: self::organizationId(),
            staffProfileId: is_string($get('staff_profile_id')) ? $get('staff_profile_id') : null,
            weekdays: (array) $get('weekdays'),
            intervalWeeks: max(1, (int) $get('interval_weeks')),
            durationMinutes: (int) $get('duration_minutes'),
            timezone: is_string($get('timezone')) && $get('timezone') !== ''
                ? $get('timezone')
                : (string) config('app.timezone'),
            startsOn: self::nullableString($get('starts_on')),
            endsOn: self::nullableString($get('ends_on')),
            selectedStartTime: is_string($get('start_time')) ? $get('start_time') : null,
            requireDeclaredAvailability: false,
            ignoreScheduleId: $record === null ? null : (string) $record->getKey(),
        );
    }

    /** @return list<int> */
    private static function durationChoices(?string $targetType): array
    {
        return array_values(array_map(
            'intval',
            (array) config($targetType === 'student'
                ? 'scheduling.individual_session_durations'
                : 'scheduling.session_durations'),
        ));
    }

    private static function defaultDuration(?string $targetType): int
    {
        return (int) config($targetType === 'student'
            ? 'scheduling.default_individual_duration_minutes'
            : 'scheduling.default_duration_minutes');
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    private static function recurrenceLabel(Schedule $schedule): string
    {
        $rule = WeeklyRecurrence::fromRRule((string) $schedule->rrule);
        $days = implode('، ', array_map(
            static fn (int $day): string => __('scheduling::filament.schedule.weekdays.'.$day),
            $rule->weekdays,
        ));

        return trans_choice('scheduling::filament.schedule.recurrence_summary', $rule->intervalWeeks, [
            'interval' => $rule->intervalWeeks,
            'days' => $days,
        ]);
    }

    private static function queries(): SchedulingAdministrationQueryService
    {
        return app(SchedulingAdministrationQueryService::class);
    }

    private static function organizationId(): string
    {
        $id = auth()->user()?->getAttribute('organization_id');
        abort_unless(is_string($id) && $id !== '', 403);

        return $id;
    }
}
