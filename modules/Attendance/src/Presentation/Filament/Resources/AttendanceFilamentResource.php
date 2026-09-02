<?php

declare(strict_types=1);

namespace Modules\Attendance\Presentation\Filament\Resources;

use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Attendance\Application\Actions\ConfirmAttendanceAction;
use Modules\Attendance\Application\Actions\OverrideAttendanceAction;
use Modules\Attendance\Application\Queries\AttendanceOperationsQueryService;
use Modules\Attendance\Domain\Enums\AttendanceStatus;
use Modules\Attendance\Domain\Models\Attendance;
use Modules\Attendance\Presentation\Filament\Resources\AttendanceResource\Pages;
use Shared\Filament\RecordOriginGuide;
use Shared\Support\BusinessRuleViolation;

/** شاشة تشغيلية لقيود الحضور؛ الرصد نفسه يأتي من الفصل ولا يُنشأ يدويًا. */
final class AttendanceFilamentResource extends Resource
{
    protected static ?string $model = Attendance::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?int $navigationSort = 42;

    // الخانة معلنة هنا لا في الصنف الأب: `$navigationParentItem` في Filament
    // مشتركة بين كل الموارد، فبلا إعادة إعلانها يدهس آخرُ إسناد ما قبله.
    // القيمة نفسها تُضبط مركزيًا في App\Filament\AdminNavigation.
    protected static ?string $navigationParentItem = null;

    public static function getNavigationGroup(): string
    {
        return __('attendance::filament.navigation_group');
    }

    public static function getModelLabel(): string
    {
        return __('attendance::filament.attendance.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('attendance::filament.attendance.plural');
    }

    public static function canAccess(): bool
    {
        return (bool) (auth()->user()?->can('viewAny', Attendance::class) ?? false);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $organizationId = self::organizationId();
        $participantIds = $organizationId === ''
            ? []
            : self::operations()->participantIdsForOrganization($organizationId);

        return parent::getEloquentQuery()->whereIn('session_participant_id', $participantIds);
    }

    public static function table(Table $table): Table
    {
        return RecordOriginGuide::for(
            $table,
            'attendance::origin',
            'heroicon-o-calendar-days',
            'filament.admin.resources.sessions.index',
        )
            ->columns([
                TextColumn::make('student_label')
                    ->label(__('attendance::fields.student'))
                    ->state(fn (Attendance $record): string => self::contextValue($record, 'student'))
                    ->wrap(),
                TextColumn::make('student_code_label')
                    ->label(__('attendance::fields.student_code'))
                    ->state(fn (Attendance $record): string => self::contextValue($record, 'student_code'))
                    ->badge()
                    ->toggleable(),
                TextColumn::make('session_label')
                    ->label(__('attendance::fields.session'))
                    ->state(fn (Attendance $record): string => self::contextValue($record, 'session'))
                    ->wrap(),
                TextColumn::make('course_label')
                    ->label(__('attendance::fields.course'))
                    ->state(fn (Attendance $record): string => self::contextValue($record, 'course'))
                    ->toggleable(),
                TextColumn::make('group_label')
                    ->label(__('attendance::fields.group'))
                    ->state(fn (Attendance $record): string => self::contextValue($record, 'group'))
                    ->toggleable(),
                TextColumn::make('teacher_label')
                    ->label(__('attendance::fields.teacher'))
                    ->state(fn (Attendance $record): string => self::contextValue($record, 'teacher'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label(__('attendance::fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (?AttendanceStatus $state): string => $state?->label() ?? '—')
                    ->color(fn (?AttendanceStatus $state): string => self::statusColor($state)),
                TextColumn::make('derived_status')
                    ->label(__('attendance::fields.derived_status'))
                    ->badge()
                    ->formatStateUsing(fn (?AttendanceStatus $state): string => $state?->label() ?? '—')
                    ->color('gray')
                    ->toggleable(),
                TextColumn::make('attended_minutes')
                    ->label(__('attendance::fields.attended_minutes'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('confirmed_at')
                    ->label(__('attendance::fields.confirmed_at'))
                    ->dateTime()
                    ->sortable()
                    ->placeholder(__('attendance::messages.pending_confirmation')),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('attendance::fields.status'))
                    ->options(self::statusOptions()),
                TernaryFilter::make('confirmed_at')
                    ->label(__('attendance::filters.confirmed'))
                    ->nullable(),
            ])
            ->recordActions([
                ViewAction::make(),
                self::confirmAction(),
                self::overrideAction(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function confirmAction(): Action
    {
        return Action::make('confirm')
            ->label(__('attendance::filament.actions.confirm'))
            ->icon('heroicon-m-check-badge')
            ->color('primary')
            ->authorize('confirm')
            ->visible(fn (Attendance $record): bool => !$record->isConfirmed())
            ->requiresConfirmation()
            ->modalDescription(__('attendance::filament.actions.confirm_description'))
            ->schema([
                Textarea::make('reason')
                    ->label(__('attendance::fields.reason'))
                    ->required()
                    ->minLength((int) config('attendance.confirm.reason_min_chars', 5))
                    ->maxLength((int) config('attendance.confirm.reason_max_chars', 1000)),
            ])
            ->action(function (Attendance $record, array $data): void {
                self::runSafely(function () use ($record, $data): void {
                    app(ConfirmAttendanceAction::class)->execute(
                        attendance: $record,
                        confirmedBy: (string) auth()->id(),
                        reason: (string) $data['reason'],
                        organizationId: self::organizationId(),
                    );
                }, __('attendance::filament.messages.confirmed'));
            });
    }

    public static function overrideAction(): Action
    {
        return Action::make('override')
            ->label(__('attendance::filament.actions.override'))
            ->icon('heroicon-m-pencil-square')
            ->color('primary')
            ->authorize('override')
            ->schema([
                Select::make('status')
                    ->label(__('attendance::fields.new_status'))
                    ->options(self::statusOptions())
                    ->required(),
                Textarea::make('reason')
                    ->label(__('attendance::fields.reason'))
                    ->required()
                    ->minLength((int) config('attendance.override.reason_min_chars', 5))
                    ->maxLength((int) config('attendance.override.reason_max_chars', 1000))
                    ->helperText(__('attendance::filament.actions.reason_helper')),
            ])
            ->action(function (array $data, Attendance $record): void {
                self::runSafely(function () use ($data, $record): void {
                    app(OverrideAttendanceAction::class)->execute(
                        attendance: $record,
                        newStatus: AttendanceStatus::from((string) $data['status']),
                        reason: (string) $data['reason'],
                        actorId: (string) auth()->id(),
                        organizationId: self::organizationId(),
                    );
                }, __('attendance::filament.messages.overridden'));
            });
    }

    /** @return array<string, string> */
    public static function statusOptions(): array
    {
        return collect(AttendanceStatus::cases())
            ->mapWithKeys(fn (AttendanceStatus $case): array => [$case->value => $case->label()])
            ->all();
    }

    private static function contextValue(Attendance $attendance, string $key): string
    {
        return (string) (self::operations()->participantContext(
            self::organizationId(),
            (string) $attendance->session_participant_id,
        )[$key] ?? __('attendance::messages.not_available'));
    }

    private static function statusColor(?AttendanceStatus $status): string
    {
        return match ($status) {
            AttendanceStatus::Present => 'success',
            AttendanceStatus::Late, AttendanceStatus::Partial, AttendanceStatus::LeftEarly => 'warning',
            AttendanceStatus::Excused, AttendanceStatus::TechnicalIssue => 'info',
            AttendanceStatus::Absent, AttendanceStatus::NoShow => 'danger',
            default => 'gray',
        };
    }

    private static function organizationId(): string
    {
        return (string) data_get(auth()->user(), 'organization_id', '');
    }

    private static function operations(): AttendanceOperationsQueryService
    {
        return app(AttendanceOperationsQueryService::class);
    }

    private static function runSafely(callable $operation, string $message): void
    {
        try {
            $operation();
            Notification::make()->title($message)->success()->send();
        } catch (BusinessRuleViolation $violation) {
            Notification::make()->title($violation->getMessage())->danger()->send();
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAttendances::route('/'),
            'view' => Pages\ViewAttendance::route('/{record}'),
        ];
    }
}
