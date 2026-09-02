<?php

declare(strict_types=1);

namespace Modules\Sessions\Presentation\Filament\Resources\SessionResource\Pages;

use App\Application\Actions\SessionClassroomOperations;
use App\Application\Queries\SessionOperationsCoordinator;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Modules\Sessions\Application\Actions\CancelSessionAction;
use Modules\Sessions\Application\Actions\CompleteSessionAction;
use Modules\Sessions\Application\Actions\ConfirmSessionAction;
use Modules\Sessions\Application\Actions\DecideTeacherApologyAction;
use Modules\Sessions\Application\Actions\EndSessionAction;
use Modules\Sessions\Application\Actions\ExcuseAbsenceAction;
use Modules\Sessions\Application\Actions\MarkNoShowAction;
use Modules\Sessions\Application\Actions\PostponeSessionAction;
use Modules\Sessions\Application\Actions\StartSessionAction;
use Modules\Sessions\Application\Queries\SessionOperationsQueryService;
use Modules\Sessions\Domain\Enums\ApologyStatus;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Domain\Models\TeacherApology;
use Modules\Sessions\Presentation\Filament\Resources\SessionResource;
use Shared\Support\BusinessRuleViolation;

final class ViewSession extends ViewRecord
{
    protected static string $resource = SessionResource::class;

    /** @var array<string, list<array<string, mixed>>>|null */
    private ?array $hubData = null;

    protected function getHeaderActions(): array
    {
        return [
            $this->transitionAction('confirm', SessionStatus::Confirmed, ConfirmSessionAction::class, 'confirm'),
            $this->transitionAction('start', SessionStatus::InProgress, StartSessionAction::class, 'start'),
            $this->transitionAction('end', SessionStatus::AwaitingReview, EndSessionAction::class, 'end'),
            $this->transitionAction('complete', SessionStatus::Completed, CompleteSessionAction::class, 'complete'),
            $this->transitionAction('mark_no_show', SessionStatus::NoShow, MarkNoShowAction::class, 'markNoShow', 'danger'),
            $this->transitionAction('excuse', SessionStatus::Excused, ExcuseAbsenceAction::class, 'excuse'),
            $this->rescheduleAction(),
            $this->provisionClassroomAction(),
            $this->checkClassroomHealthAction(),
            $this->decideApologyAction(),
            SessionResource::assignSubstituteAction(),
            $this->cancelAction(),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('sessions::hub.overview'))
                ->icon('heroicon-o-calendar-days')
                ->schema([
                    TextEntry::make('title')
                        ->label(__('sessions::fields.title'))
                        ->formatStateUsing(fn (mixed $state): string => $this->localized($state)),
                    TextEntry::make('status')
                        ->label(__('sessions::fields.status'))
                        ->badge()
                        ->formatStateUsing(fn (mixed $state): string => $state instanceof SessionStatus ? $state->label() : (string) $state)
                        ->color(fn (mixed $state): string => $state instanceof SessionStatus ? $state->color() : 'gray'),
                    TextEntry::make('program')
                        ->label(__('sessions::fields.program'))
                        ->state(fn (): string => $this->queries()->programLabelForCourse(
                            $this->organizationId(),
                            (string) $this->session()->course_id,
                        )),
                    TextEntry::make('course_id')
                        ->label(__('sessions::fields.course'))
                        ->formatStateUsing(fn (mixed $state): string => $this->queries()->courseLabel($this->organizationId(), (string) $state)),
                    TextEntry::make('group_id')
                        ->label(__('sessions::fields.group'))
                        ->formatStateUsing(fn (mixed $state): string => $this->queries()->groupLabel(
                            $this->organizationId(),
                            $state === null ? null : (string) $state,
                        )),
                    TextEntry::make('session_type')
                        ->label(__('sessions::fields.session_type'))
                        ->formatStateUsing(fn (mixed $state): string => __('sessions::session_types.'.(string) $state)),
                    TextEntry::make('scheduled_start')->label(__('sessions::fields.scheduled_start'))->dateTime(),
                    TextEntry::make('scheduled_end')->label(__('sessions::fields.scheduled_end'))->dateTime(),
                    TextEntry::make('timezone')
                        ->label(__('sessions::fields.timezone'))
                        ->state(fn (): string => $this->queries()->groupTimezone(
                            $this->organizationId(),
                            $this->session()->group_id === null ? null : (string) $this->session()->group_id,
                        )),
                    TextEntry::make('actual_start')->label(__('sessions::fields.actual_start'))->dateTime(),
                    TextEntry::make('actual_end')->label(__('sessions::fields.actual_end'))->dateTime(),
                    TextEntry::make('notes')->label(__('sessions::fields.notes'))->columnSpanFull(),
                ])->columns(3),

            Section::make(__('sessions::fields.teachers'))
                ->icon('heroicon-o-user-circle')
                ->schema([
                    TextEntry::make('original_teacher_id')
                        ->label(__('sessions::fields.original_teacher'))
                        ->formatStateUsing(fn (mixed $state): string => $this->queries()->teacherLabel($this->organizationId(), $state === null ? null : (string) $state)),
                    TextEntry::make('staff_profile_id')
                        ->label(__('sessions::fields.actual_teacher'))
                        ->formatStateUsing(fn (mixed $state): string => $this->queries()->teacherLabel($this->organizationId(), (string) $state)),
                    TextEntry::make('coverage')
                        ->label(__('sessions::fields.coverage_status'))
                        ->state(fn (): string => $this->session()->isCoveredBySubstitute()
                            ? __('sessions::fields.covered_by_substitute')
                            : __('sessions::fields.original_teacher_assigned'))
                        ->badge(),
                ])->columns(3),

            Tabs::make(__('sessions::hub.title'))
                ->persistTabInQueryString('session-hub')
                ->tabs([
                    $this->participantsTab(),
                    $this->classroomTab(),
                    $this->recordingsTab(),
                    $this->notificationsTab(),
                    $this->historyTab(),
                    $this->substitutionsTab(),
                    $this->apologiesTab(),
                    $this->auditTab(),
                ])->columnSpanFull(),
        ]);
    }

    private function classroomTab(): Tab
    {
        return Tab::make(__('sessions::hub.classroom'))
            ->icon('heroicon-o-video-camera')
            ->badge(fn (): int => count($this->hub('classroom_events')))
            ->schema([
                RepeatableEntry::make('classroom_hub')
                    ->hiddenLabel()
                    ->placeholder(__('sessions::hub.classroom_not_provisioned'))
                    ->getStateUsing(fn (): array => $this->hub('classroom'))
                    ->schema([
                        TextEntry::make('provider')->label(__('sessions::fields.classroom_provider')),
                        TextEntry::make('status')->label(__('sessions::fields.classroom_status'))->badge(),
                        TextEntry::make('health_status')->label(__('sessions::fields.classroom_health'))->badge(),
                        TextEntry::make('provision_attempts')->label(__('sessions::fields.provision_attempts')),
                        TextEntry::make('created_remote_at')->label(__('sessions::fields.created_remote_at'))->dateTime(),
                        TextEntry::make('started_at')->label(__('sessions::fields.classroom_started_at'))->dateTime(),
                        TextEntry::make('ended_at')->label(__('sessions::fields.classroom_ended_at'))->dateTime(),
                        TextEntry::make('max_concurrent_participants')->label(__('sessions::fields.max_concurrent_participants')),
                        TextEntry::make('last_error')->label(__('sessions::fields.last_error'))->columnSpanFull(),
                        TextEntry::make('last_error_at')->label(__('sessions::fields.last_error_at'))->dateTime(),
                    ])->columns(4),
                RepeatableEntry::make('classroom_events_hub')
                    ->label(__('sessions::hub.classroom_events'))
                    ->placeholder(__('sessions::hub.empty'))
                    ->getStateUsing(fn (): array => $this->hub('classroom_events'))
                    ->schema([
                        TextEntry::make('type')->label(__('sessions::fields.event_type')),
                        TextEntry::make('participant')->label(__('sessions::fields.participant')),
                        TextEntry::make('occurred_at')->label(__('sessions::fields.occurred_at'))->dateTime(),
                    ])->columns(3),
            ]);
    }

    private function participantsTab(): Tab
    {
        return Tab::make(__('sessions::hub.participants'))
            ->icon('heroicon-o-users')
            ->badge(fn (): int => count($this->hub('participants')))
            ->schema([
                RepeatableEntry::make('participants_hub')
                    ->hiddenLabel()
                    ->placeholder(__('sessions::hub.empty'))
                    ->getStateUsing(fn (): array => $this->hub('participants'))
                    ->schema([
                        TextEntry::make('student')->label(__('sessions::fields.student_profile')),
                        TextEntry::make('invitation_status')->label(__('sessions::fields.invitation_status'))->badge(),
                        TextEntry::make('invited_at')->label(__('sessions::fields.invited_at'))->dateTime(),
                        TextEntry::make('first_joined_at')->label(__('sessions::fields.first_joined_at'))->dateTime(),
                        TextEntry::make('last_left_at')->label(__('sessions::fields.last_left_at'))->dateTime(),
                        TextEntry::make('attended_minutes')->label(__('sessions::fields.attended_minutes')),
                        TextEntry::make('attendance_status')->label(__('sessions::fields.attendance_status'))->badge(),
                        TextEntry::make('derived_attendance_status')->label(__('sessions::fields.derived_attendance_status'))->badge(),
                        TextEntry::make('attendance_confirmed_at')->label(__('sessions::fields.attendance_confirmed_at'))->dateTime(),
                        TextEntry::make('attendance_override_reason')->label(__('sessions::fields.attendance_override_reason')),
                        TextEntry::make('revoked_at')->label(__('sessions::fields.revoked_at'))->dateTime(),
                        TextEntry::make('revocation_reason')->label(__('sessions::fields.revocation_reason')),
                    ])->columns(4),
            ]);
    }

    private function recordingsTab(): Tab
    {
        return Tab::make(__('sessions::hub.recordings'))
            ->icon('heroicon-o-film')
            ->badge(fn (): int => count($this->hub('recordings')))
            ->schema([
                RepeatableEntry::make('recordings_hub')
                    ->hiddenLabel()
                    ->placeholder(__('sessions::hub.empty'))
                    ->getStateUsing(fn (): array => $this->hub('recordings'))
                    ->schema([
                        TextEntry::make('provider')->label(__('sessions::fields.recording_provider')),
                        TextEntry::make('status')->label(__('sessions::fields.recording_status'))->badge(),
                        TextEntry::make('duration_minutes')->label(__('sessions::fields.recording_duration_minutes')),
                        TextEntry::make('active_grants')->label(__('sessions::fields.recording_active_grants'))->badge(),
                        TextEntry::make('views')->label(__('sessions::fields.recording_views'))->badge(),
                        TextEntry::make('downloads')->label(__('sessions::fields.recording_downloads'))->badge(),
                        TextEntry::make('available_from')->label(__('sessions::fields.recording_available_from'))->dateTime(),
                        TextEntry::make('expires_at')->label(__('sessions::fields.recording_expires_at'))->dateTime(),
                        TextEntry::make('archived_at')->label(__('sessions::fields.recording_archived_at'))->dateTime(),
                    ])->columns(3),
            ]);
    }

    private function notificationsTab(): Tab
    {
        return Tab::make(__('sessions::hub.notifications'))
            ->icon('heroicon-o-bell-alert')
            ->badge(fn (): int => count($this->hub('notifications')))
            ->schema([
                RepeatableEntry::make('notifications_hub')
                    ->hiddenLabel()
                    ->placeholder(__('sessions::hub.empty'))
                    ->getStateUsing(fn (): array => $this->hub('notifications'))
                    ->schema([
                        TextEntry::make('recipient')->label(__('sessions::fields.notification_recipient')),
                        TextEntry::make('category')->label(__('sessions::fields.notification_category'))->badge(),
                        TextEntry::make('channel')->label(__('sessions::fields.notification_channel'))->badge(),
                        TextEntry::make('status')->label(__('sessions::fields.notification_status'))->badge(),
                        TextEntry::make('attempts')->label(__('sessions::fields.notification_attempts')),
                        TextEntry::make('scheduled_for')->label(__('sessions::fields.notification_scheduled_for'))->dateTime(),
                        TextEntry::make('sent_at')->label(__('sessions::fields.notification_sent_at'))->dateTime(),
                        TextEntry::make('read_at')->label(__('sessions::fields.notification_read_at'))->dateTime(),
                        TextEntry::make('last_error')->label(__('sessions::fields.notification_last_error'))->columnSpanFull(),
                    ])->columns(4),
            ]);
    }

    private function historyTab(): Tab
    {
        return Tab::make(__('sessions::hub.status_history'))
            ->icon('heroicon-o-clock')
            ->schema([
                RepeatableEntry::make('history_hub')
                    ->hiddenLabel()
                    ->placeholder(__('sessions::hub.empty'))
                    ->getStateUsing(fn (): array => $this->hub('history'))
                    ->schema([
                        TextEntry::make('from_status')->label(__('sessions::fields.from_status')),
                        TextEntry::make('to_status')->label(__('sessions::fields.to_status')),
                        TextEntry::make('reason')->label(__('sessions::fields.reason')),
                        TextEntry::make('changed_at')->label(__('sessions::fields.changed_at'))->dateTime(),
                    ])->columns(4),
            ]);
    }

    private function substitutionsTab(): Tab
    {
        return Tab::make(__('sessions::hub.substitutions'))
            ->icon('heroicon-o-arrow-path-rounded-square')
            ->schema([
                RepeatableEntry::make('substitutions_hub')
                    ->hiddenLabel()
                    ->placeholder(__('sessions::hub.empty'))
                    ->getStateUsing(fn (): array => $this->hub('substitutions'))
                    ->schema([
                        TextEntry::make('original_teacher')->label(__('sessions::fields.original_teacher')),
                        TextEntry::make('substitute_teacher')->label(__('sessions::fields.substitute_teacher')),
                        TextEntry::make('reason')->label(__('sessions::fields.reason')),
                        IconEntry::make('is_override')->label(__('sessions::fields.is_override'))->boolean(),
                        TextEntry::make('override_reason')->label(__('sessions::fields.override_reason')),
                        TextEntry::make('assigned_at')->label(__('sessions::fields.assigned_at'))->dateTime(),
                    ])->columns(3),
            ]);
    }

    private function apologiesTab(): Tab
    {
        return Tab::make(__('sessions::hub.apologies'))
            ->icon('heroicon-o-exclamation-triangle')
            ->schema([
                RepeatableEntry::make('apologies_hub')
                    ->hiddenLabel()
                    ->placeholder(__('sessions::hub.empty'))
                    ->getStateUsing(fn (): array => $this->hub('apologies'))
                    ->schema([
                        TextEntry::make('teacher')->label(__('sessions::fields.staff_profile')),
                        TextEntry::make('status')->label(__('sessions::fields.status'))->badge(),
                        TextEntry::make('reason')->label(__('sessions::fields.reason')),
                        TextEntry::make('submitted_at')->label(__('sessions::fields.submitted_at'))->dateTime(),
                        IconEntry::make('is_late_notice')->label(__('sessions::fields.is_late_notice'))->boolean(),
                        TextEntry::make('decision_reason')->label(__('sessions::fields.decision_reason')),
                    ])->columns(3),
            ]);
    }

    private function auditTab(): Tab
    {
        return Tab::make(__('sessions::hub.audit'))
            ->icon('heroicon-o-shield-check')
            ->schema([
                RepeatableEntry::make('audit_hub')
                    ->hiddenLabel()
                    ->placeholder(__('sessions::hub.empty'))
                    ->getStateUsing(fn (): array => $this->hub('audit'))
                    ->schema([
                        TextEntry::make('action')->label(__('sessions::fields.action')),
                        TextEntry::make('reason')->label(__('sessions::fields.reason')),
                        TextEntry::make('actor')->label(__('sessions::fields.actor')),
                        TextEntry::make('created_at')->label(__('sessions::fields.changed_at'))->dateTime(),
                    ])->columns(4),
            ]);
    }

    /**
     * @param class-string<ConfirmSessionAction|StartSessionAction|EndSessionAction|CompleteSessionAction|MarkNoShowAction|ExcuseAbsenceAction> $actionClass
     */
    private function transitionAction(
        string $name,
        SessionStatus $target,
        string $actionClass,
        string $ability,
        string $color = 'primary',
    ): Action {
        return Action::make($name)
            ->label(__('sessions::actions.'.$name))
            ->color($color)
            ->authorize($ability)
            ->visible(fn (): bool => $this->session()->status->canTransitionTo($target))
            ->requiresConfirmation()
            ->schema([$this->reasonField()])
            ->action(function (array $data) use ($actionClass): void {
                $this->runSafely(function () use ($actionClass, $data): void {
                    app($actionClass)->execute(
                        $this->session(),
                        (string) auth()->id(),
                        (string) $data['reason'],
                    );
                }, __('sessions::messages.status_updated'));
            });
    }

    private function rescheduleAction(): Action
    {
        return Action::make('reschedule')
            ->label(__('sessions::actions.reschedule'))
            ->icon('heroicon-m-calendar-days')
            ->authorize('postpone')
            ->visible(fn (): bool => $this->session()->status->canTransitionTo(SessionStatus::Postponed))
            ->schema([
                DateTimePicker::make('makeup_start')->label(__('sessions::fields.makeup_start'))->required(),
                DateTimePicker::make('makeup_end')->label(__('sessions::fields.makeup_end'))->required(),
                $this->reasonField(),
            ])
            ->action(function (array $data): void {
                $this->runSafely(function () use ($data): void {
                    app(PostponeSessionAction::class)->execute(
                        $this->session(),
                        (string) $data['makeup_start'],
                        (string) $data['makeup_end'],
                        (string) $data['reason'],
                        (string) auth()->id(),
                    );
                }, __('sessions::messages.rescheduled'));
            });
    }

    private function cancelAction(): Action
    {
        return Action::make('cancel')
            ->label(__('sessions::actions.cancel'))
            ->icon('heroicon-m-x-circle')
            ->color('danger')
            ->authorize('cancel')
            ->visible(fn (): bool => $this->session()->status->canTransitionTo(SessionStatus::CancelledBySchool))
            ->requiresConfirmation()
            ->schema([$this->reasonField()])
            ->action(function (array $data): void {
                $this->runSafely(function () use ($data): void {
                    app(CancelSessionAction::class)->execute(
                        $this->session(),
                        SessionStatus::CancelledBySchool,
                        (string) $data['reason'],
                        (string) auth()->id(),
                    );
                }, __('sessions::messages.cancelled'));
            });
    }

    private function decideApologyAction(): Action
    {
        return Action::make('decide_apology')
            ->label(__('sessions::actions.decide_apology'))
            ->icon('heroicon-o-clipboard-document-check')
            ->authorize('assignSubstitute')
            ->visible(fn (): bool => TeacherApology::query()
                ->forOrganization($this->organizationId())
                ->where('session_id', (string) $this->session()->getKey())
                ->where('status', ApologyStatus::Submitted)
                ->exists())
            ->schema([
                Select::make('apology_id')
                    ->label(__('sessions::fields.apology'))
                    ->options(fn (): array => TeacherApology::query()
                        ->forOrganization($this->organizationId())
                        ->where('session_id', (string) $this->session()->getKey())
                        ->where('status', ApologyStatus::Submitted)
                        ->pluck('reason', 'id')
                        ->mapWithKeys(static fn (mixed $reason, mixed $id): array => [(string) $id => (string) $reason])
                        ->all())
                    ->required(),
                Select::make('decision')
                    ->label(__('sessions::fields.decision'))
                    ->options([
                        ApologyStatus::Approved->value => __('sessions::actions.approve_apology'),
                        ApologyStatus::Rejected->value => __('sessions::actions.reject_apology'),
                    ])
                    ->required(),
                $this->reasonField(),
            ])
            ->action(function (array $data): void {
                $this->runSafely(function () use ($data): void {
                    $action = app(DecideTeacherApologyAction::class);
                    if ((string) $data['decision'] === ApologyStatus::Approved->value) {
                        $action->approve(
                            (string) $data['apology_id'],
                            (string) auth()->id(),
                            (string) $data['reason'],
                            expectedOrganizationId: $this->organizationId(),
                            expectedSessionId: (string) $this->session()->getKey(),
                        );
                    } else {
                        $action->reject(
                            (string) $data['apology_id'],
                            (string) auth()->id(),
                            (string) $data['reason'],
                            expectedOrganizationId: $this->organizationId(),
                            expectedSessionId: (string) $this->session()->getKey(),
                        );
                    }
                }, __('sessions::messages.apology_decided'));
            });
    }

    private function provisionClassroomAction(): Action
    {
        return Action::make('provision_classroom')
            ->label(fn (): string => $this->classroomStatus() === 'failed'
                ? __('sessions::actions.retry_classroom')
                : __('sessions::actions.provision_classroom'))
            ->icon('heroicon-o-video-camera')
            ->color($this->classroomStatus() === 'failed' ? 'danger' : 'primary')
            ->authorize(fn (): bool => (bool) (auth()->user()?->can('classroom.moderate') ?? false))
            ->visible(fn (): bool => in_array($this->classroomStatus(), [null, 'pending', 'failed'], true))
            ->requiresConfirmation()
            ->schema([$this->reasonField()])
            ->action(function (array $data): void {
                $this->runSafely(function () use ($data): void {
                    app(SessionClassroomOperations::class)->provision(
                        $this->session(),
                        (string) auth()->id(),
                        (string) $data['reason'],
                    );
                }, __('sessions::messages.classroom_provisioned'));
            });
    }

    private function checkClassroomHealthAction(): Action
    {
        return Action::make('check_classroom_health')
            ->label(__('sessions::actions.check_classroom_health'))
            ->icon('heroicon-o-signal')
            ->authorize(fn (): bool => (bool) (auth()->user()?->can('classroom.moderate') ?? false))
            ->visible(fn (): bool => in_array($this->classroomStatus(), ['provisioned', 'running'], true))
            ->schema([$this->reasonField()])
            ->action(function (array $data): void {
                $this->runSafely(function () use ($data): void {
                    app(SessionClassroomOperations::class)->checkHealth(
                        $this->session(),
                        (string) auth()->id(),
                        (string) $data['reason'],
                    );
                }, __('sessions::messages.classroom_health_checked'));
            });
    }

    private function reasonField(): Textarea
    {
        return Textarea::make('reason')
            ->label(__('sessions::fields.reason'))
            ->required()
            ->minLength(3)
            ->maxLength(1000);
    }

    private function runSafely(callable $operation, string $successMessage): void
    {
        try {
            $operation();
        } catch (BusinessRuleViolation $violation) {
            Notification::make()->title($violation->getMessage())->danger()->send();

            return;
        }

        $this->record->refresh();
        $this->hubData = null;
        Notification::make()->title($successMessage)->success()->send();
    }

    private function classroomStatus(): ?string
    {
        $classroom = $this->hub('classroom')[0] ?? null;

        return is_array($classroom) && is_string($classroom['status_value'] ?? null)
            ? $classroom['status_value']
            : null;
    }

    /** @return list<array<string, mixed>> */
    private function hub(string $section): array
    {
        $this->hubData ??= app(SessionOperationsCoordinator::class)
            ->sessionHub($this->organizationId(), $this->session());

        return $this->hubData[$section] ?? [];
    }

    private function session(): Session
    {
        abort_unless($this->record instanceof Session, 404);

        return $this->record;
    }

    private function organizationId(): string
    {
        return (string) $this->session()->organization_id;
    }

    private function queries(): SessionOperationsQueryService
    {
        return app(SessionOperationsQueryService::class);
    }

    private function localized(mixed $value): string
    {
        if (!is_array($value)) {
            return (string) ($value ?? '');
        }

        return (string) ($value[app()->getLocale()] ?? $value['ar'] ?? $value['en'] ?? reset($value) ?: '');
    }
}
