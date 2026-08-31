<?php

declare(strict_types=1);

namespace Modules\Assignments\Presentation\Filament\Resources\AssignmentFilamentResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Modules\Assignments\Application\Actions\ArchiveAssignmentAction;
use Modules\Assignments\Application\Queries\AssignmentAdministrationQueryService;
use Modules\Assignments\Domain\Models\Assignment;
use Modules\Assignments\Presentation\Filament\Resources\AssignmentFilamentResource;
use Shared\Support\BusinessRuleViolation;

final class ViewAssignment extends ViewRecord
{
    protected static string $resource = AssignmentFilamentResource::class;

    /** @var array{recipients: int, pending: int, submitted: int, late: int, graded: int}|null */
    private ?array $metrics = null;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->label(__('assignments::filament.actions.edit'))
                ->visible(fn (): bool => auth()->user()?->can('update', $this->assignment()) ?? false),
            Action::make('archive')
                ->label(__('assignments::filament.actions.archive'))
                ->icon('heroicon-o-archive-box')
                ->color('danger')
                ->visible(fn (): bool => auth()->user()?->can('delete', $this->assignment()) ?? false)
                ->requiresConfirmation()
                ->schema([$this->reasonField()])
                ->action(function (array $data): void {
                    try {
                        app(ArchiveAssignmentAction::class)->execute(
                            $this->assignment(),
                            (string) auth()->id(),
                            (string) $data['reason'],
                        );
                    } catch (BusinessRuleViolation $violation) {
                        Notification::make()->title($violation->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()->title(__('assignments::messages.archived'))->success()->send();
                    $this->redirect(AssignmentFilamentResource::getUrl());
                }),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('assignments::filament.hub.overview'))
                ->icon('heroicon-o-clipboard-document-check')
                ->schema([
                    TextEntry::make('title')
                        ->label(__('assignments::attributes.title'))
                        ->state(fn (): string => $this->localized($this->assignment()->title)),
                    TextEntry::make('operational_status')
                        ->label(__('assignments::attributes.status'))
                        ->state(fn (): string => $this->assignment()->operationalStatus()->label())
                        ->color(fn (): string => $this->assignment()->operationalStatus()->color())
                        ->badge(),
                    TextEntry::make('course')
                        ->label(__('assignments::attributes.course'))
                        ->state(fn (): string => $this->queries()->courseLabel(
                            $this->organizationId(),
                            (string) $this->assignment()->course_id,
                        )),
                    TextEntry::make('group')
                        ->label(__('assignments::attributes.group'))
                        ->state(fn (): string => $this->queries()->groupLabel(
                            $this->organizationId(),
                            $this->assignment()->group_id === null ? null : (string) $this->assignment()->group_id,
                        )),
                    TextEntry::make('teacher')
                        ->label(__('assignments::attributes.teacher'))
                        ->state(fn (): string => $this->queries()->teacherLabel(
                            $this->organizationId(),
                            (string) $this->assignment()->staff_profile_id,
                        )),
                    TextEntry::make('assigned_at')
                        ->label(__('assignments::attributes.assigned_at'))
                        ->dateTime('d/m/Y H:i'),
                    TextEntry::make('due_at')
                        ->label(__('assignments::attributes.due_at'))
                        ->dateTime('d/m/Y H:i'),
                    TextEntry::make('max_score')->label(__('assignments::attributes.max_score')),
                    TextEntry::make('allows_late')
                        ->label(__('assignments::attributes.allows_late'))
                        ->state(fn (): string => $this->assignment()->allows_late
                            ? __('assignments::messages.yes')
                            : __('assignments::messages.no'))
                        ->badge()
                        ->color(fn (): string => $this->assignment()->allows_late ? 'success' : 'gray'),
                    TextEntry::make('late_penalty_percent')
                        ->label(__('assignments::attributes.late_penalty_percent'))
                        ->suffix('%'),
                    TextEntry::make('instructions')
                        ->label(__('assignments::attributes.instructions'))
                        ->state(fn (): string => $this->localized($this->assignment()->instructions))
                        ->placeholder(__('assignments::messages.not_available'))
                        ->columnSpanFull(),
                ])->columns(3),
            Section::make(__('assignments::filament.hub.metrics'))
                ->schema([
                    $this->metricEntry('recipients'),
                    $this->metricEntry('pending'),
                    $this->metricEntry('submitted'),
                    $this->metricEntry('late'),
                    $this->metricEntry('graded'),
                ])->columns(5),
            Tabs::make(__('assignments::filament.hub.history'))
                ->persistTabInQueryString('assignment-hub')
                ->tabs([
                    Tab::make(__('assignments::filament.hub.submission_snapshot'))
                        ->icon('heroicon-o-users')
                        ->schema([
                            RepeatableEntry::make('submission_snapshot')
                                ->label(__('assignments::filament.hub.submission_snapshot'))
                                ->hiddenLabel()
                                ->placeholder(__('assignments::filament.submissions.empty'))
                                ->getStateUsing(fn (): array => $this->queries()->submissions(
                                    $this->organizationId(),
                                    (string) $this->assignment()->getKey(),
                                ))
                                ->schema([
                                    TextEntry::make('student')->label(__('assignments::filament.submissions.student')),
                                    TextEntry::make('status')->label(__('assignments::attributes.status'))->badge(),
                                    TextEntry::make('submitted_at')
                                        ->label(__('assignments::filament.submissions.submitted_at'))
                                        ->dateTime('d/m/Y H:i')
                                        ->placeholder(__('assignments::messages.not_available')),
                                    TextEntry::make('score')
                                        ->label(__('assignments::attributes.score'))
                                        ->placeholder(__('assignments::messages.not_available')),
                                ])->columns(4),
                        ]),
                    Tab::make(__('assignments::filament.hub.audit'))
                        ->icon('heroicon-o-clock')
                        ->schema([
                            RepeatableEntry::make('audit_trail')
                                ->label(__('assignments::filament.hub.audit'))
                                ->hiddenLabel()
                                ->placeholder(__('assignments::filament.hub.no_audit'))
                                ->getStateUsing(fn (): array => $this->queries()->auditTrail(
                                    $this->organizationId(),
                                    (string) $this->assignment()->getKey(),
                                ))
                                ->schema([
                                    TextEntry::make('action')->label(__('assignments::filament.hub.action')),
                                    TextEntry::make('actor')->label(__('assignments::filament.hub.actor')),
                                    TextEntry::make('reason')->label(__('assignments::attributes.reason')),
                                    TextEntry::make('created_at')
                                        ->label(__('assignments::filament.hub.changed_at'))
                                        ->dateTime('d/m/Y H:i'),
                                ])->columns(4),
                        ]),
                ])->columnSpanFull(),
        ]);
    }

    private function metricEntry(string $metric): TextEntry
    {
        return TextEntry::make('metric_'.$metric)
            ->label(__('assignments::filament.metrics.'.$metric))
            ->state(fn (): int => $this->metrics()[$metric]);
    }

    /** @return array{recipients: int, pending: int, submitted: int, late: int, graded: int} */
    private function metrics(): array
    {
        return $this->metrics ??= $this->queries()->metrics($this->assignment());
    }

    private function reasonField(): Textarea
    {
        return Textarea::make('reason')
            ->label(__('assignments::attributes.reason'))
            ->required()
            ->maxLength((int) config('assignments.reason_max_length', 1000));
    }

    private function assignment(): Assignment
    {
        abort_unless($this->record instanceof Assignment, 404);

        return $this->record;
    }

    private function organizationId(): string
    {
        return (string) $this->assignment()->organization_id;
    }

    private function queries(): AssignmentAdministrationQueryService
    {
        return app(AssignmentAdministrationQueryService::class);
    }

    private function localized(mixed $state): string
    {
        if (!is_array($state)) {
            return (string) ($state ?? '');
        }

        return (string) ($state[app()->getLocale()] ?? $state['ar'] ?? $state['en'] ?? reset($state));
    }
}
