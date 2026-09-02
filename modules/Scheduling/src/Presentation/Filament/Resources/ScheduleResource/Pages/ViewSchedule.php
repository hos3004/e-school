<?php

declare(strict_types=1);

namespace Modules\Scheduling\Presentation\Filament\Resources\ScheduleResource\Pages;

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
use Modules\Scheduling\Application\Actions\ActivateScheduleAction;
use Modules\Scheduling\Application\Actions\DeactivateScheduleAction;
use Modules\Scheduling\Application\Actions\MaterializeScheduleAction;
use Modules\Scheduling\Application\Queries\SchedulingAdministrationQueryService;
use Modules\Scheduling\Domain\Models\Schedule;
use Modules\Scheduling\Presentation\Filament\Resources\ScheduleResource;

final class ViewSchedule extends ViewRecord
{
    protected static string $resource = ScheduleResource::class;

    /** @var array<string, list<array<string, mixed>>>|null */
    private ?array $hubData = null;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('materialize')
                ->label(__('scheduling::filament.schedule.actions.materialize'))
                ->icon('heroicon-o-arrow-path')
                ->visible(fn (): bool => $this->schedule()->is_active
                    && auth()->user()?->can('update', $this->schedule()) === true)
                ->schema([$this->reasonField()])
                ->action(function (array $data): void {
                    $result = app(MaterializeScheduleAction::class)->execute(
                        $this->schedule(),
                        (string) auth()->id(),
                        (string) $data['reason'],
                    );
                    $this->hubData = null;
                    $this->record->refresh();
                    Notification::make()
                        ->title(__('scheduling::filament.schedule.actions.materialized', [
                            'count' => $result->created,
                            'warnings' => $result->outsideAvailabilityWarnings,
                        ]))->success()->send();
                }),
            Action::make('deactivate')
                ->label(__('scheduling::filament.schedule.actions.deactivate'))
                ->icon('heroicon-o-pause')
                ->color('danger')
                ->visible(fn (): bool => $this->schedule()->is_active
                    && auth()->user()?->can('deactivate', $this->schedule()) === true)
                ->requiresConfirmation()
                ->schema([$this->reasonField()])
                ->action(function (array $data): void {
                    app(DeactivateScheduleAction::class)->execute(
                        $this->schedule(),
                        (string) auth()->id(),
                        (string) $data['reason'],
                    );
                    $this->record->refresh();
                    $this->hubData = null;
                    Notification::make()->title(__('scheduling::filament.schedule.actions.deactivated'))->success()->send();
                }),
            Action::make('activate')
                ->label(__('scheduling::filament.schedule.actions.activate'))
                ->icon('heroicon-o-play')
                ->color('primary')
                ->visible(fn (): bool => !$this->schedule()->is_active
                    && auth()->user()?->can('activate', $this->schedule()) === true)
                ->requiresConfirmation()
                ->schema([$this->reasonField()])
                ->action(function (array $data): void {
                    app(ActivateScheduleAction::class)->execute(
                        $this->schedule(),
                        (string) auth()->id(),
                        (string) $data['reason'],
                    );
                    $this->record->refresh();
                    $this->hubData = null;
                    Notification::make()->title(__('scheduling::filament.schedule.actions.activated'))->success()->send();
                }),
            EditAction::make()->visible(fn (): bool => $this->schedule()->is_active),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('scheduling::filament.schedule.sections.overview'))
                ->schema([
                    TextEntry::make('target')
                        ->label(__('scheduling::filament.schedule.fields.target'))
                        ->state(fn (Schedule $record): string => $record->group_id !== null
                            ? $this->queries()->groupLabel($this->organizationId(), (string) $record->group_id)
                            : $this->queries()->studentLabel($this->organizationId(), $record->student_profile_id)),
                    TextEntry::make('course_id')
                        ->label(__('scheduling::filament.schedule.fields.course'))
                        ->formatStateUsing(fn (mixed $state): string => $this->queries()->courseLabel($this->organizationId(), (string) $state)),
                    TextEntry::make('staff_profile_id')
                        ->label(__('scheduling::filament.schedule.fields.teacher'))
                        ->formatStateUsing(fn (mixed $state): string => $this->queries()->teacherLabel($this->organizationId(), (string) $state)),
                    TextEntry::make('rrule')->label(__('scheduling::filament.schedule.fields.rrule'))->copyable(),
                    TextEntry::make('start_time')->label(__('scheduling::filament.schedule.fields.start_time')),
                    TextEntry::make('duration_minutes')->label(__('scheduling::filament.schedule.fields.duration')),
                    TextEntry::make('timezone')->label(__('scheduling::filament.schedule.fields.timezone')),
                    TextEntry::make('starts_on')->label(__('scheduling::filament.schedule.fields.starts_on'))->date(),
                    TextEntry::make('ends_on')->label(__('scheduling::filament.schedule.fields.ends_on'))->date(),
                    TextEntry::make('materialized_until')->label(__('scheduling::filament.schedule.fields.materialized_until'))->date(),
                    TextEntry::make('is_active')
                        ->label(__('scheduling::filament.schedule.fields.status'))
                        ->badge()
                        ->formatStateUsing(fn (bool $state): string => $state
                            ? __('scheduling::filament.schedule.status.active')
                            : __('scheduling::filament.schedule.status.inactive')),
                ])->columns(3),
            Tabs::make(__('scheduling::filament.schedule.hub.title'))
                ->persistTabInQueryString('schedule-hub')
                ->tabs([
                    Tab::make(__('scheduling::filament.schedule.hub.sessions'))
                        ->icon('heroicon-o-calendar-days')
                        ->schema([
                            RepeatableEntry::make('sessions_hub')
                                ->hiddenLabel()
                                ->placeholder(__('scheduling::filament.schedule.hub.empty'))
                                ->getStateUsing(fn (): array => $this->hub('sessions'))
                                ->schema([
                                    TextEntry::make('title')->label(__('scheduling::filament.schedule.hub.session')),
                                    TextEntry::make('status')->label(__('scheduling::filament.schedule.fields.status'))->badge(),
                                    TextEntry::make('scheduled_start')->label(__('scheduling::filament.schedule.hub.scheduled_start'))->dateTime(),
                                    TextEntry::make('scheduled_end')->label(__('scheduling::filament.schedule.hub.scheduled_end'))->dateTime(),
                                ])->columns(4),
                        ]),
                    Tab::make(__('scheduling::filament.schedule.hub.history'))
                        ->icon('heroicon-o-clock')
                        ->schema([
                            RepeatableEntry::make('history_hub')
                                ->hiddenLabel()
                                ->placeholder(__('scheduling::filament.schedule.hub.empty'))
                                ->getStateUsing(fn (): array => $this->hub('history'))
                                ->schema([
                                    TextEntry::make('action')->label(__('scheduling::filament.schedule.hub.action')),
                                    TextEntry::make('reason')->label(__('scheduling::filament.schedule.fields.reason')),
                                    TextEntry::make('actor_id')->label(__('scheduling::filament.schedule.hub.actor')),
                                    TextEntry::make('created_at')->label(__('scheduling::filament.schedule.hub.changed_at'))->dateTime(),
                                ])->columns(4),
                        ]),
                ])->columnSpanFull(),
        ]);
    }

    private function reasonField(): Textarea
    {
        return Textarea::make('reason')
            ->label(__('scheduling::filament.schedule.fields.reason'))
            ->required()
            ->maxLength(1000);
    }

    /** @return list<array<string, mixed>> */
    private function hub(string $section): array
    {
        $this->hubData ??= $this->queries()->scheduleHub($this->organizationId(), $this->schedule());

        return $this->hubData[$section] ?? [];
    }

    private function schedule(): Schedule
    {
        abort_unless($this->record instanceof Schedule, 404);

        return $this->record;
    }

    private function organizationId(): string
    {
        return (string) $this->schedule()->organization_id;
    }

    private function queries(): SchedulingAdministrationQueryService
    {
        return app(SchedulingAdministrationQueryService::class);
    }
}
