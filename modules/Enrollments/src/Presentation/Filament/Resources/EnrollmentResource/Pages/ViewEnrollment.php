<?php

declare(strict_types=1);

namespace Modules\Enrollments\Presentation\Filament\Resources\EnrollmentResource\Pages;

use App\Application\Actions\AssignStudentToGroupAction;
use App\Application\Queries\ProfileAdministrationQueryService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
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
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Modules\Enrollments\Application\Actions\ChangeEnrollmentLevelAction;
use Modules\Enrollments\Application\Actions\FreezeEnrollmentAction;
use Modules\Enrollments\Application\Actions\PauseEnrollmentAction;
use Modules\Enrollments\Application\Actions\ReactivateEnrollmentAction;
use Modules\Enrollments\Application\Actions\RequestReactivationAction;
use Modules\Enrollments\Application\Actions\TransitionEnrollmentStatusAction;
use Modules\Enrollments\Application\Queries\EnrollmentOperationsQueryService;
use Modules\Enrollments\Domain\Enums\EnrollmentStatus;
use Modules\Enrollments\Domain\Models\Enrollment;
use Modules\Enrollments\Presentation\Filament\Resources\EnrollmentResource;
use Shared\Support\BusinessRuleViolation;

final class ViewEnrollment extends ViewRecord
{
    protected static string $resource = EnrollmentResource::class;

    /** @var array<string, list<array<string, mixed>>>|null */
    private ?array $hubData = null;

    protected function getHeaderActions(): array
    {
        return [
            $this->placeAction(),
            $this->changeLevelAction(),
            $this->standardTransitionAction('review', EnrollmentStatus::UnderReview, 'enrollment.create'),
            $this->standardTransitionAction('approve', EnrollmentStatus::Approved, 'enrollment.create'),
            $this->standardTransitionAction('reject', EnrollmentStatus::Rejected, 'enrollment.create'),
            $this->pauseAction(),
            $this->standardTransitionAction('resume', EnrollmentStatus::Active, 'enrollment.pause', [EnrollmentStatus::Paused]),
            $this->freezeAction(),
            $this->requestReactivationAction(),
            $this->standardTransitionAction('begin_assessment', EnrollmentStatus::UnderAssessment, 'enrollment.reactivate'),
            $this->reactivateAction(),
            $this->standardTransitionAction('complete', EnrollmentStatus::Completed, 'enrollment.create'),
            $this->standardTransitionAction('withdraw', EnrollmentStatus::Withdrawn, 'enrollment.create'),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('enrollments::filament.hub.overview'))
                ->icon('heroicon-o-academic-cap')
                ->schema([
                    TextEntry::make('status')
                        ->label(__('enrollments::filament.enrollment.status'))
                        ->badge()
                        ->formatStateUsing(fn (?EnrollmentStatus $state): ?string => $state?->label()),
                    IconEntry::make('course_access')
                        ->label(__('enrollments::filament.hub.course_access'))
                        ->state(fn (Enrollment $record): bool => $record->status->grantsCourseAccess())
                        ->boolean(),
                    TextEntry::make('expected_return_date')
                        ->label(__('enrollments::filament.enrollment.expected_return'))
                        ->date()
                        ->placeholder(__('enrollments::filament.common.not_available')),
                    TextEntry::make('freeze_type')
                        ->label(__('enrollments::filament.enrollment.freeze_type'))
                        ->formatStateUsing(fn (?string $state): string => $state === null
                            ? __('enrollments::filament.common.not_available')
                            : __('enrollments::filament.freeze_types.'.$state)),
                    TextEntry::make('frozen_reason')
                        ->label(__('enrollments::filament.enrollment.frozen_reason'))
                        ->placeholder(__('enrollments::filament.common.not_available'))
                        ->columnSpan(2),
                ])->columns(3),

            Tabs::make(__('enrollments::filament.hub.title'))
                ->persistTabInQueryString('enrollment-hub')
                ->tabs([
                    $this->tab('student', 'heroicon-o-user', [
                        TextEntry::make('name')->label(__('enrollments::filament.enrollment.student')),
                        TextEntry::make('student_profile_id')->label(__('enrollments::filament.hub.student_profile_id'))->copyable(),
                    ]),
                    $this->tab('academic', 'heroicon-o-book-open', [
                        TextEntry::make('program')->label(__('enrollments::filament.enrollment.program')),
                        TextEntry::make('level')->label(__('enrollments::filament.enrollment.current_level')),
                        TextEntry::make('applied_at')->label(__('enrollments::filament.enrollment.applied_at'))->dateTime(),
                        TextEntry::make('activated_at')->label(__('enrollments::filament.enrollment.activated_at'))->dateTime(),
                        TextEntry::make('completed_at')->label(__('enrollments::filament.enrollment.completed_at'))->dateTime(),
                    ]),
                    $this->tab('groups', 'heroicon-o-user-group', [
                        TextEntry::make('group')->label(__('enrollments::filament.hub.group')),
                        TextEntry::make('group_status')->label(__('enrollments::filament.hub.group_status'))->badge(),
                        TextEntry::make('membership_status')->label(__('enrollments::filament.hub.membership_status'))->badge(),
                        TextEntry::make('joined_at')->label(__('enrollments::filament.hub.joined_at'))->dateTime(),
                        TextEntry::make('left_at')->label(__('enrollments::filament.hub.left_at'))->dateTime(),
                    ]),
                    $this->tab('history', 'heroicon-o-clock', [
                        TextEntry::make('from_status')->label(__('enrollments::filament.hub.from_status'))->badge(),
                        TextEntry::make('to_status')->label(__('enrollments::filament.hub.to_status'))->badge(),
                        TextEntry::make('reason')->label(__('enrollments::filament.enrollment.reason'))->columnSpan(2),
                        TextEntry::make('actor')->label(__('enrollments::filament.hub.actor')),
                        TextEntry::make('changed_at')->label(__('enrollments::filament.hub.changed_at'))->dateTime(),
                    ]),
                ])->columnSpanFull(),
        ]);
    }

    /** @param array<int, TextEntry> $entries */
    private function tab(string $section, string $icon, array $entries): Tab
    {
        return Tab::make(__('enrollments::filament.hub.'.$section))
            ->icon($icon)
            ->schema([
                RepeatableEntry::make($section.'_hub')
                    ->hiddenLabel()
                    ->placeholder(__('enrollments::filament.hub.empty'))
                    ->getStateUsing(fn (): array => $this->hub($section))
                    ->schema($entries)
                    ->columns(3),
            ]);
    }

    private function placeAction(): Action
    {
        return Action::make('place')
            ->label(__('enrollments::filament.actions.place'))
            ->icon('heroicon-o-user-plus')
            ->color('primary')
            ->visible(fn (): bool => $this->enrollment()->status === EnrollmentStatus::Approved
                && (auth()->user()?->can('enrollment.create') ?? false))
            ->schema([
                Select::make('course_id')
                    ->label(__('enrollments::filament.hub.course'))
                    ->options(fn (): array => app(ProfileAdministrationQueryService::class)->courseOptions(
                        $this->organizationId(),
                        (string) $this->enrollment()->program_id,
                    ))
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(fn (Set $set): mixed => $set('group_id', null))
                    ->required(),
                Select::make('group_id')
                    ->label(__('enrollments::filament.hub.group'))
                    ->options(fn (Get $get): array => app(ProfileAdministrationQueryService::class)->placementGroupOptions(
                        $this->organizationId(),
                        (string) $this->enrollment()->program_id,
                        is_string($get('course_id')) ? $get('course_id') : null,
                    ))
                    ->searchable()
                    ->preload()
                    ->required(),
                $this->reasonField(),
            ])
            ->action(function (array $data): void {
                $this->executeSafely(function () use ($data): void {
                    app(AssignStudentToGroupAction::class)->execute(
                        actorOrganizationId: $this->organizationId(),
                        studentProfileId: (string) $this->enrollment()->student_profile_id,
                        programId: (string) $this->enrollment()->program_id,
                        groupId: (string) $data['group_id'],
                        courseId: (string) $data['course_id'],
                        actorId: (string) auth()->id(),
                        correlationId: request()->header('X-Correlation-Id'),
                        reason: (string) $data['reason'],
                    );
                }, 'placed');
            });
    }

    private function changeLevelAction(): Action
    {
        return Action::make('change_level')
            ->label(__('enrollments::filament.actions.change_level'))
            ->icon('heroicon-o-arrow-trending-up')
            ->visible(fn (): bool => !$this->enrollment()->status->isTerminal()
                && (auth()->user()?->can('enrollment.create') ?? false))
            ->schema([
                Select::make('level_id')
                    ->label(__('enrollments::filament.enrollment.current_level'))
                    ->options(fn (): array => app(EnrollmentOperationsQueryService::class)->levelOptions(
                        $this->organizationId(),
                        (string) $this->enrollment()->program_id,
                    ))
                    ->searchable()
                    ->preload()
                    ->required(),
                $this->reasonField(),
            ])
            ->action(function (array $data): void {
                $this->executeSafely(function () use ($data): void {
                    app(ChangeEnrollmentLevelAction::class)->execute(
                        $this->enrollment(),
                        (string) $data['level_id'],
                        (string) $data['reason'],
                        (string) auth()->id(),
                    );
                }, 'level_changed');
            });
    }

    /** @param list<EnrollmentStatus> $from */
    private function standardTransitionAction(
        string $name,
        EnrollmentStatus $target,
        string $ability,
        array $from = [],
    ): Action {
        return Action::make($name)
            ->label(__('enrollments::filament.actions.'.$name))
            ->icon('heroicon-o-arrow-path')
            ->visible(fn (): bool => ($from === []
                ? $this->enrollment()->status->canTransitionTo($target)
                : in_array($this->enrollment()->status, $from, true))
                && (auth()->user()?->can($ability) ?? false))
            ->requiresConfirmation()
            ->schema([$this->reasonField()])
            ->action(function (array $data) use ($target): void {
                $this->executeSafely(function () use ($data, $target): void {
                    app(TransitionEnrollmentStatusAction::class)->execute(
                        $this->enrollment(),
                        $target,
                        (string) $data['reason'],
                        (string) auth()->id(),
                    );
                }, 'status_changed');
            });
    }

    private function pauseAction(): Action
    {
        return Action::make('pause')
            ->label(__('enrollments::filament.actions.pause'))
            ->icon('heroicon-o-pause')
            ->color('primary')
            ->visible(fn (): bool => $this->enrollment()->status === EnrollmentStatus::Active
                && (auth()->user()?->can('pause', $this->enrollment()) ?? false))
            ->schema([
                DatePicker::make('expected_return_date')
                    ->label(__('enrollments::filament.enrollment.expected_return'))
                    ->minDate(now()->toDateString())
                    ->required(),
                $this->reasonField(),
            ])
            ->action(function (array $data): void {
                $this->executeSafely(function () use ($data): void {
                    app(PauseEnrollmentAction::class)->execute(
                        $this->enrollment(),
                        (string) $data['expected_return_date'],
                        (string) $data['reason'],
                        (string) auth()->id(),
                    );
                }, 'paused');
            });
    }

    private function freezeAction(): Action
    {
        return Action::make('freeze')
            ->label(__('enrollments::filament.actions.freeze'))
            ->icon('heroicon-o-lock-closed')
            ->color('danger')
            ->visible(fn (): bool => $this->enrollment()->status->canTransitionTo(EnrollmentStatus::Frozen)
                && (auth()->user()?->can('freeze', $this->enrollment()) ?? false))
            ->requiresConfirmation()
            ->schema([
                Select::make('freeze_type')
                    ->label(__('enrollments::filament.enrollment.freeze_type'))
                    ->options(collect((array) config('enrollments.freeze_types'))->mapWithKeys(
                        static fn (mixed $type): array => [(string) $type => __('enrollments::filament.freeze_types.'.(string) $type)],
                    )->all())
                    ->required(),
                $this->reasonField(),
            ])
            ->action(function (array $data): void {
                $this->executeSafely(function () use ($data): void {
                    app(FreezeEnrollmentAction::class)->execute(
                        $this->enrollment(),
                        (string) $data['reason'],
                        (string) $data['freeze_type'],
                        (string) auth()->id(),
                    );
                }, 'frozen');
            });
    }

    private function requestReactivationAction(): Action
    {
        return Action::make('request_reactivation')
            ->label(__('enrollments::filament.actions.request_reactivation'))
            ->icon('heroicon-o-paper-airplane')
            ->visible(fn (): bool => $this->enrollment()->status === EnrollmentStatus::Frozen
                && (auth()->user()?->can('requestReactivation', $this->enrollment()) ?? false))
            ->schema([$this->reasonField()])
            ->action(function (array $data): void {
                $this->executeSafely(function () use ($data): void {
                    app(RequestReactivationAction::class)->execute(
                        $this->enrollment(),
                        (string) $data['reason'],
                        (string) auth()->id(),
                    );
                }, 'reactivation_requested');
            });
    }

    private function reactivateAction(): Action
    {
        return Action::make('reactivate')
            ->label(__('enrollments::filament.actions.reactivate'))
            ->icon('heroicon-o-lock-open')
            ->color('primary')
            ->visible(fn (): bool => $this->enrollment()->status === EnrollmentStatus::UnderAssessment
                && (auth()->user()?->can('reactivate', $this->enrollment()) ?? false))
            ->requiresConfirmation()
            ->schema([$this->reasonField()])
            ->action(function (array $data): void {
                $this->executeSafely(function () use ($data): void {
                    app(ReactivateEnrollmentAction::class)->execute(
                        $this->enrollment(),
                        (string) $data['reason'],
                        (string) auth()->id(),
                    );
                }, 'reactivated');
            });
    }

    private function reasonField(): Textarea
    {
        return Textarea::make('reason')
            ->label(__('enrollments::filament.enrollment.reason'))
            ->required()
            ->maxLength((int) config('enrollments.reason_max_length'));
    }

    private function executeSafely(callable $callback, string $notification): void
    {
        try {
            $callback();
        } catch (BusinessRuleViolation $violation) {
            Notification::make()->title($violation->getMessage())->danger()->send();

            return;
        }

        $this->record->refresh();
        $this->hubData = null;
        Notification::make()
            ->title(__('enrollments::filament.notifications.'.$notification))
            ->success()
            ->send();
    }

    /** @return list<array<string, mixed>> */
    private function hub(string $section): array
    {
        $this->hubData ??= app(EnrollmentOperationsQueryService::class)->hub(
            $this->organizationId(),
            (string) $this->enrollment()->getKey(),
        );

        return $this->hubData[$section] ?? [];
    }

    private function enrollment(): Enrollment
    {
        abort_unless($this->record instanceof Enrollment, 404);

        return $this->record;
    }

    private function organizationId(): string
    {
        return (string) $this->enrollment()->organization_id;
    }
}
