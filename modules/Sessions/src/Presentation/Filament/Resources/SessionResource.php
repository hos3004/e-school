<?php

declare(strict_types=1);

namespace Modules\Sessions\Presentation\Filament\Resources;

use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Sessions\Application\Actions\AssignSubstituteTeacherAction;
use Modules\Sessions\Application\Queries\SessionOperationsQueryService;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Domain\Services\SubstituteCandidateFinder;
use Modules\Staff\Domain\Contracts\StaffQueries;
use Shared\Concerns\ScopesFilamentToOrganization;

/**
 * مورد إدارة الحصص في لوحة الإدارة.
 */
final class SessionResource extends Resource
{
    use ScopesFilamentToOrganization;

    protected static ?string $model = Session::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?int $navigationSort = 41;

    public static function getNavigationGroup(): string
    {
        return __('sessions::navigation.group');
    }

    public static function getModelLabel(): string
    {
        return __('sessions::navigation.session.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('sessions::navigation.session.plural');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label(__('sessions::fields.id'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('title')
                    ->label(__('sessions::fields.title'))
                    ->formatStateUsing(fn ($state): string => is_array($state)
                        ? ($state[app()->getLocale()] ?? reset($state))
                        : (string) $state)
                    ->limit(40),
                TextColumn::make('status')
                    ->label(__('sessions::fields.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof SessionStatus
                        ? $state->label()
                        : (string) $state)
                    ->color(fn ($state): string => $state instanceof SessionStatus
                        ? $state->color()
                        : 'gray'),
                TextColumn::make('course_id')
                    ->label(__('sessions::fields.course'))
                    ->formatStateUsing(fn (mixed $state, Session $record): string => self::queries()->courseLabel(
                        (string) $record->organization_id,
                        (string) $state,
                    ))
                    ->toggleable(),
                TextColumn::make('group_id')
                    ->label(__('sessions::fields.group'))
                    ->formatStateUsing(fn (mixed $state, Session $record): string => self::queries()->groupLabel(
                        (string) $record->organization_id,
                        $state === null ? null : (string) $state,
                    ))
                    ->toggleable(),
                TextColumn::make('staff_profile_id')
                    ->label(__('sessions::fields.staff_profile'))
                    /*
                     * كان يعرض ULID خامًا — رقم لا يقرأه مشرف ولا يميّز به
                     * معلمًا عن آخر. الاسم يأتي عبر عقد Staff دفعة واحدة
                     * للصفحة، لا باستعلام لكل صف.
                     */
                    ->formatStateUsing(static fn ($state): string => self::teacherNames()[(string) $state]
                        ?? (string) $state)
                    ->toggleable(),
                TextColumn::make('active_participants_count')
                    ->label(__('sessions::fields.participants_count'))
                    ->counts(['participants' => static fn (Builder $query): Builder => $query->whereNull('revoked_at')])
                    ->numeric(),
                TextColumn::make('scheduled_start')
                    ->label(__('sessions::fields.scheduled_start'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('scheduled_end')
                    ->label(__('sessions::fields.scheduled_end'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('actual_start')
                    ->label(__('sessions::fields.actual_start'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('sessions::fields.status'))
                    ->multiple()
                    ->options(collect(SessionStatus::cases())
                        ->mapWithKeys(fn (SessionStatus $s): array => [$s->value => $s->label()])
                        ->all()),

                SelectFilter::make('staff_profile_id')
                    ->label(__('sessions::fields.staff_profile'))
                    ->options(fn (): array => self::teacherNames())
                    ->searchable(),

                SelectFilter::make('session_type')
                    ->label(__('sessions::fields.session_type'))
                    ->options(fn (): array => self::sessionTypeOptions()),

                /*
                 * الجدول التشغيلي بلا نافذة زمنية بلا معنى: المشرف يسأل عن
                 * أسبوع أو شهر، لا عن كل تاريخ الحصص.
                 */
                Filter::make('scheduled_between')
                    ->label(__('sessions::filters.scheduled_between'))
                    ->schema([
                        DatePicker::make('from')->label(__('sessions::filters.from')),
                        DatePicker::make('until')->label(__('sessions::filters.until')),
                    ])
                    ->query(static function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'] ?? null,
                                static fn (Builder $q, string $from): Builder => $q
                                    ->whereDate('scheduled_start', '>=', $from),
                            )
                            ->when(
                                $data['until'] ?? null,
                                static fn (Builder $q, string $until): Builder => $q
                                    ->whereDate('scheduled_start', '<=', $until),
                            );
                    })
                    ->indicateUsing(static function (array $data): array {
                        $indicators = [];

                        if (($data['from'] ?? null) !== null) {
                            $indicators[] = __('sessions::filters.from').': '.$data['from'];
                        }

                        if (($data['until'] ?? null) !== null) {
                            $indicators[] = __('sessions::filters.until').': '.$data['until'];
                        }

                        return $indicators;
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                self::assignSubstituteAction(),
            ])
            ->defaultSort('scheduled_start');
    }

    /**
     * أسماء معلمي المؤسسة — تُحلّ مرة واحدة لكل طلب.
     *
     * @return array<string, string>
     */
    private static function teacherNames(): array
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $organizationId = data_get(auth()->user(), 'organization_id');

        if (!is_string($organizationId) || $organizationId === '') {
            return $cache = [];
        }

        /** @var StaffQueries $staff */
        $staff = app(StaffQueries::class);

        return $cache = $staff->namesForProfiles(
            $organizationId,
            $staff->activeTeacherIdsForOrganization($organizationId),
        );
    }

    /**
     * @return array<string, string>
     */
    private static function sessionTypeOptions(): array
    {
        $types = config('academic.session_types');
        $options = [];

        // المفاتيح هي الأنواع؛ القيم إعدادات سعة لا تُعرض هنا.
        foreach (array_keys(is_array($types) ? $types : []) as $type) {
            if (is_string($type)) {
                $options[$type] = __('sessions::session_types.'.$type);
            }
        }

        return $options;
    }

    public static function assignSubstituteAction(): Action
    {
        return Action::make('assign_substitute')
            ->label(__('sessions::actions.assign_substitute'))
            ->icon('heroicon-m-user-plus')
            ->color('primary')
            ->authorize('assignSubstitute')
            ->modalHeading(__('sessions::actions.assign_substitute_heading'))
            ->modalDescription(__('sessions::actions.assign_substitute_description'))
            ->visible(fn (Session $record): bool => in_array($record->status, [
                SessionStatus::Draft,
                SessionStatus::Scheduled,
                SessionStatus::Confirmed,
            ], true))
            ->form([
                Select::make('substitute_teacher_id')
                    ->label(__('sessions::fields.substitute_teacher'))
                    ->options(function (Session $record): array {
                        $finder = app(SubstituteCandidateFinder::class);
                        $candidates = $finder->candidatesFor((string) $record->getKey(), true);
                        $options = [];
                        foreach ($candidates as $c) {
                            $status = [];
                            $status[] = $c['is_qualified'] ? __('sessions::actions.candidate_qualified') : __('sessions::actions.candidate_unqualified');
                            if ($c['is_available']) {
                                $status[] = __('sessions::actions.candidate_available');
                            } else {
                                if ($c['is_on_leave']) {
                                    $status[] = __('sessions::actions.candidate_on_leave');
                                }
                                if ($c['conflicting_sessions'] > 0) {
                                    $status[] = __('sessions::actions.candidate_conflict', ['count' => $c['conflicting_sessions']]);
                                }
                            }
                            $options[$c['staff_profile_id']] = "{$c['name']} ({$c['staff_code']}) — ".implode(' | ', $status);
                        }

                        return $options;
                    })
                    ->required()
                    ->live(),
                Textarea::make('reason')
                    ->label(__('sessions::fields.reason'))
                    ->required(),
                Textarea::make('override_reason')
                    ->label(__('sessions::fields.override_reason'))
                    ->placeholder(__('sessions::fields.override_reason_hint'))
                    ->visible(function (callable $get, Session $record): bool {
                        $subId = $get('substitute_teacher_id');
                        if (!$subId) {
                            return false;
                        }
                        $eval = app(SubstituteCandidateFinder::class)->evaluate((string) $record->getKey(), (string) $subId);

                        return !$eval['qualified'] || !$eval['available'];
                    })
                    ->required(function (callable $get, Session $record): bool {
                        $subId = $get('substitute_teacher_id');
                        if (!$subId) {
                            return false;
                        }
                        $eval = app(SubstituteCandidateFinder::class)->evaluate((string) $record->getKey(), (string) $subId);

                        return !$eval['qualified'] || !$eval['available'];
                    }),
            ])
            ->action(function (Session $record, array $data): void {
                $subId = (string) $data['substitute_teacher_id'];
                $eval = app(SubstituteCandidateFinder::class)->evaluate((string) $record->getKey(), $subId);
                $isOverride = !$eval['qualified'] || !$eval['available'];

                app(AssignSubstituteTeacherAction::class)->execute(
                    sessionId: (string) $record->getKey(),
                    substituteTeacherId: $subId,
                    assignedBy: (string) auth()->id(),
                    reason: (string) $data['reason'],
                    allowOverride: $isOverride,
                    options: ['override_reason' => $data['override_reason'] ?? null],
                );

                Notification::make()
                    ->title(__('sessions::messages.substitute_assigned'))
                    ->success()
                    ->send();
            });
    }

    private static function queries(): SessionOperationsQueryService
    {
        return app(SessionOperationsQueryService::class);
    }

    public static function getPages(): array
    {
        return [
            'index' => SessionResource\Pages\ListSessions::route('/'),
            'calendar' => SessionResource\Pages\CalendarSessions::route('/calendar'),
            'view' => SessionResource\Pages\ViewSession::route('/{record}'),
        ];
    }
}
