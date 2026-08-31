<?php

declare(strict_types=1);

namespace Modules\Scheduling\Presentation\Filament\Resources;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Scheduling\Application\Queries\SchedulingAdministrationQueryService;
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
                            ))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('staff_profile_id', null);
                                $set('student_profile_id', null);
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
                        ->required()
                        ->columnSpanFull(),
                    Grid::make(1)->schema([
                        TextInput::make('interval_weeks')
                            ->label(__('scheduling::filament.schedule.fields.interval_weeks'))
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(12)
                            ->default(1)
                            ->required(),
                        TimePicker::make('start_time')
                            ->label(__('scheduling::filament.schedule.fields.start_time'))
                            ->seconds(false)
                            ->required(),
                        Select::make('duration_minutes')
                            ->label(__('scheduling::filament.schedule.fields.duration'))
                            ->options(collect((array) config('scheduling.session_durations'))
                                ->mapWithKeys(static fn (mixed $minutes): array => [
                                    (int) $minutes => __('scheduling::filament.schedule.minutes', ['minutes' => $minutes]),
                                ])->all())
                            ->default((int) config('scheduling.default_duration_minutes'))
                            ->required(),
                    ]),
                    Grid::make(1)->schema([
                        Select::make('timezone')
                            ->label(__('scheduling::filament.schedule.fields.timezone'))
                            ->options(array_combine(timezone_identifiers_list(), timezone_identifiers_list()))
                            ->default(fn (): string => (string) (auth()->user()?->getAttribute('timezone') ?? config('app.timezone')))
                            ->searchable()
                            ->required(),
                        DatePicker::make('starts_on')
                            ->label(__('scheduling::filament.schedule.fields.starts_on'))
                            ->default(now()->toDateString())
                            ->required(),
                        DatePicker::make('ends_on')
                            ->label(__('scheduling::filament.schedule.fields.ends_on'))
                            ->afterOrEqual('starts_on'),
                    ]),
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
