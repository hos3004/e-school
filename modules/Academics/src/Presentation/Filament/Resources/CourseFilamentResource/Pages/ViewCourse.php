<?php

declare(strict_types=1);

namespace Modules\Academics\Presentation\Filament\Resources\CourseFilamentResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Modules\Academics\Application\Actions\ArchiveCourseAction;
use Modules\Academics\Application\Actions\UpdateCourseAction;
use Modules\Academics\Application\Queries\AcademicAdministrationQueryService;
use Modules\Academics\Domain\Enums\SessionMode;
use Modules\Academics\Domain\Enums\TargetGender;
use Modules\Academics\Domain\Models\Course;
use Modules\Academics\Presentation\Filament\Resources\CourseFilamentResource;
use Shared\Support\LocalizedJsonColumn;

final class ViewCourse extends ViewRecord
{
    protected static string $resource = CourseFilamentResource::class;

    /** @var array<string, mixed>|null */
    private ?array $hubData = null;

    protected function getHeaderActions(): array
    {
        return [
            $this->toggleActiveAction(),
            $this->archiveAction(),
            EditAction::make()->visible(fn (): bool => auth()->user()?->can('update', $this->course()) ?? false),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('academics::filament.course.hub.overview'))->icon('heroicon-o-book-open')->schema([
                TextEntry::make('code')->label(__('academics::filament.course.fields.code'))->copyable(),
                TextEntry::make('name')->label(__('academics::filament.course.fields.name'))->formatStateUsing(static fn ($state): string => LocalizedJsonColumn::display($state)),
                TextEntry::make('program')->label(__('academics::filament.course.fields.program'))->state(fn (): string => (string) data_get($this->hub(), 'academic_path.program')),
                TextEntry::make('level')->label(__('academics::filament.course.fields.level'))->state(fn (): string => (string) data_get($this->hub(), 'academic_path.level')),
                TextEntry::make('session_mode')->label(__('academics::filament.course.fields.session_mode'))->badge()->formatStateUsing(static fn (SessionMode $state): string => $state->label()),
                TextEntry::make('target_gender')->label(__('academics::filament.course.fields.target_gender'))->badge()->formatStateUsing(static fn (?TargetGender $state): string => $state?->label() ?? __('academics::filament.course.fields.inherits_program')),
                IconEntry::make('is_active')->label(__('academics::filament.course.fields.is_active'))->boolean(),
                TextEntry::make('age_range')->label(__('academics::filament.course.fields.age_range'))->state(fn (): string => $this->ageRange()),
                TextEntry::make('default_duration_minutes')->label(__('academics::filament.course.fields.default_duration_minutes')),
                TextEntry::make('sessions_per_week')->label(__('academics::filament.course.fields.sessions_per_week')),
                TextEntry::make('total_sessions')->label(__('academics::filament.course.fields.total_sessions')),
            ])->columns(3),
            Tabs::make(__('academics::filament.course.hub.title'))->persistTabInQueryString('course-hub')->tabs([
                Tab::make(__('academics::filament.course.hub.description'))->schema([
                    TextEntry::make('description')->hiddenLabel()->formatStateUsing(static fn ($state): string => LocalizedJsonColumn::display($state))->placeholder(__('academics::filament.hub.empty')),
                ]),
                Tab::make(__('academics::filament.course.hub.rules'))->icon('heroicon-o-clipboard-document-check')->schema([
                    KeyValueEntry::make('completion_rules')->label(__('academics::filament.course.fields.completion_rules'))->placeholder(__('academics::filament.hub.empty')),
                    KeyValueEntry::make('prerequisites')->label(__('academics::filament.course.fields.prerequisites'))->placeholder(__('academics::filament.hub.empty')),
                ])->columns(2),
                Tab::make(__('academics::filament.course.hub.categories'))->icon('heroicon-o-tag')->schema([
                    RepeatableEntry::make('categories_hub')->hiddenLabel()->placeholder(__('academics::filament.hub.empty'))
                        ->getStateUsing(fn (): array => array_values((array) data_get($this->hub(), 'categories', [])))
                        ->schema([
                            TextEntry::make('code')->label(__('academics::filament.category.fields.code'))->copyable(),
                            TextEntry::make('name')->label(__('academics::filament.category.fields.name')),
                        ])->columns(2),
                ]),
            ])->columnSpanFull(),
        ]);
    }

    private function toggleActiveAction(): Action
    {
        return Action::make('toggle_active')
            ->label(fn (): string => $this->course()->is_active ? __('academics::filament.actions.deactivate') : __('academics::filament.actions.activate'))
            ->icon(fn (): string => $this->course()->is_active ? 'heroicon-o-pause' : 'heroicon-o-play')
            ->color('primary')
            ->visible(fn (): bool => auth()->user()?->can('update', $this->course()) ?? false)->requiresConfirmation()
            ->schema([$this->reasonField()])
            ->action(function (array $data): void {
                app(UpdateCourseAction::class)->execute($this->course(), ['is_active' => !$this->course()->is_active], (string) auth()->id(), (string) $data['reason']);
                $this->record->refresh();
                Notification::make()->title(__('academics::filament.actions.status_updated'))->success()->send();
            });
    }

    private function archiveAction(): Action
    {
        return Action::make('archive')
            ->label(__('academics::filament.actions.archive'))->icon('heroicon-o-archive-box')->color('danger')
            ->visible(fn (): bool => auth()->user()?->can('delete', $this->course()) ?? false)->requiresConfirmation()
            ->schema([$this->reasonField()])
            ->action(function (array $data): void {
                app(ArchiveCourseAction::class)->execute($this->course(), (string) $data['reason'], (string) auth()->id());
                $this->redirect(CourseFilamentResource::getUrl());
            });
    }

    private function reasonField(): Textarea
    {
        return Textarea::make('reason')->label(__('academics::filament.fields.reason'))->required()
            ->minLength((int) config('academics.reason.minimum_length'))
            ->maxLength((int) config('academics.reason.maximum_length'));
    }

    /** @return array<string, mixed> */
    private function hub(): array
    {
        return $this->hubData ??= app(AcademicAdministrationQueryService::class)->courseHub(
            (string) $this->course()->organization_id,
            (string) $this->course()->getKey(),
        );
    }

    private function ageRange(): string
    {
        $from = $this->course()->age_from;
        $to = $this->course()->age_to;
        if ($from === null && $to === null) {
            return __('academics::filament.course.fields.any_age');
        }

        return sprintf('%s – %s', $from ?? '…', $to ?? '…');
    }

    private function course(): Course
    {
        abort_unless($this->record instanceof Course, 404);

        return $this->record;
    }
}
