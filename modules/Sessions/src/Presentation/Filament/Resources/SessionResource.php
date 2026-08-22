<?php

declare(strict_types=1);

namespace Modules\Sessions\Presentation\Filament\Resources;

use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Sessions\Application\Actions\AssignSubstituteTeacherAction;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Domain\Services\SubstituteCandidateFinder;

/**
 * مورد إدارة الحصص في لوحة الإدارة.
 */
final class SessionResource extends Resource
{
    protected static ?string $model = Session::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?int $navigationSort = 41;

    public static function getNavigationGroup(): ?string
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

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('sessions::fields.scheduling'))
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('course_id')
                            ->label(__('sessions::fields.course'))
                            ->required(),
                        Select::make('staff_profile_id')
                            ->label(__('sessions::fields.staff_profile'))
                            ->required(),
                        DateTimePicker::make('scheduled_start')
                            ->label(__('sessions::fields.scheduled_start'))
                            ->required(),
                        DateTimePicker::make('scheduled_end')
                            ->label(__('sessions::fields.scheduled_end'))
                            ->required(),
                        TextInput::make('session_type')
                            ->label(__('sessions::fields.session_type'))
                            ->required()
                            ->maxLength(50),
                        Select::make('status')
                            ->label(__('sessions::fields.status'))
                            ->options(collect(SessionStatus::cases())
                                ->mapWithKeys(fn (SessionStatus $s): array => [$s->value => $s->label()])
                                ->all())
                            ->required(),
                    ]),
                ]),
            Section::make(__('sessions::fields.details'))
                ->schema([
                    Textarea::make('title')
                        ->label(__('sessions::fields.title'))
                        ->required()
                        ->columnSpanFull(),
                    Textarea::make('notes')
                        ->label(__('sessions::fields.notes'))
                        ->columnSpanFull(),
                ]),
        ]);
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
                TextColumn::make('staff_profile_id')
                    ->label(__('sessions::fields.staff_profile'))
                    ->copyable(),
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
                    ->options(collect(SessionStatus::cases())
                        ->mapWithKeys(fn (SessionStatus $s): array => [$s->value => $s->label()])
                        ->all()),
            ])
            ->recordActions([
                self::assignSubstituteAction(),
            ])
            ->defaultSort('scheduled_start');
    }

    private static function assignSubstituteAction(): Action
    {
        return Action::make('assign_substitute')
            ->label(__('sessions::actions.assign_substitute'))
            ->icon('heroicon-m-user-plus')
            ->color('warning')
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
                    ->placeholder('سبب التجاوز الإداري الإلزامي عند اختيار معلم غير مؤهل أو غير متاح')
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

    public static function getPages(): array
    {
        return [
            'index' => SessionResource\Pages\ListSessions::route('/'),
            'view' => SessionResource\Pages\ViewSession::route('/{record}'),
        ];
    }
}

