<?php

declare(strict_types=1);

namespace Modules\Academics\Presentation\Filament\Resources\ProgramFilamentResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Modules\Academics\Application\Actions\ArchiveProgramAction;
use Modules\Academics\Application\Actions\ArchiveProgramCategoryAction;
use Modules\Academics\Application\Actions\CreateLevelAction;
use Modules\Academics\Application\Actions\CreateProgramCategoryAction;
use Modules\Academics\Application\Actions\UpdateProgramAction;
use Modules\Academics\Application\Actions\UpdateProgramCategoryAction;
use Modules\Academics\Application\Queries\AcademicAdministrationQueryService;
use Modules\Academics\Domain\Enums\ProgramType;
use Modules\Academics\Domain\Models\Level;
use Modules\Academics\Domain\Models\Program;
use Modules\Academics\Domain\Models\ProgramCategory;
use Modules\Academics\Presentation\Filament\Resources\ProgramFilamentResource;
use Shared\Codes\EntityCodeGenerator;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\LocalizedJsonColumn;

final class ViewProgram extends ViewRecord
{
    protected static string $resource = ProgramFilamentResource::class;

    /** @var array<string, mixed>|null */
    private ?array $hubData = null;

    protected function getHeaderActions(): array
    {
        return [
            $this->createLevelAction(),
            $this->createCategoryAction(),
            $this->updateCategoryAction(),
            $this->archiveCategoryAction(),
            $this->toggleActiveAction(),
            $this->archiveAction(),
            EditAction::make()->visible(fn (): bool => auth()->user()?->can('update', $this->program()) ?? false),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('academics::filament.program.hub.overview'))
                ->icon('heroicon-o-academic-cap')
                ->schema([
                    TextEntry::make('code')->label(__('academics::filament.program.fields.code'))->copyable(),
                    TextEntry::make('name')->label(__('academics::filament.program.fields.name'))->formatStateUsing(static fn ($state): string => LocalizedJsonColumn::display($state)),
                    TextEntry::make('program_type')->label(__('academics::filament.program.fields.program_type'))->badge()->formatStateUsing(static fn (ProgramType $state): string => $state->label()),
                    IconEntry::make('is_active')->label(__('academics::filament.program.fields.is_active'))->boolean(),
                    TextEntry::make('target_gender')->label(__('academics::filament.program.fields.target_gender'))->badge()->formatStateUsing(static fn ($state): string => $state->label()),
                    TextEntry::make('age_range')->label(__('academics::filament.program.fields.age_range'))->state(fn (): string => $this->ageRange($this->program()->age_from, $this->program()->age_to)),
                    TextEntry::make('duration_weeks')->label(__('academics::filament.program.fields.duration_weeks')),
                    TextEntry::make('default_session_minutes')->label(__('academics::filament.program.fields.default_session_minutes')),
                    TextEntry::make('default_rate')->label(__('academics::filament.program.fields.default_rate'))->money((string) $this->program()->currency, divideBy: 100),
                    TextEntry::make('start_date')->label(__('academics::filament.program.fields.start_date'))->date(),
                    TextEntry::make('end_date')->label(__('academics::filament.program.fields.end_date'))->date(),
                    TextEntry::make('language')->label(__('academics::filament.program.fields.language')),
                ])->columns(3),

            Tabs::make(__('academics::filament.program.hub.title'))
                ->persistTabInQueryString('program-hub')
                ->tabs([
                    Tab::make(__('academics::filament.program.hub.levels'))->icon('heroicon-o-chart-bar')->schema([
                        RepeatableEntry::make('levels_hub')->hiddenLabel()->placeholder(__('academics::filament.hub.empty'))
                            ->getStateUsing(fn (): array => $this->hubList('levels'))
                            ->schema([
                                TextEntry::make('code')->label(__('academics::filament.level.fields.code'))->copyable(),
                                TextEntry::make('name')->label(__('academics::filament.level.fields.name')),
                                TextEntry::make('sort_order')->label(__('academics::filament.level.fields.sort_order')),
                                TextEntry::make('courses_count')->label(__('academics::filament.program.fields.courses_count')),
                                TextEntry::make('active_courses_count')->label(__('academics::filament.program.fields.active_courses_count')),
                            ])->columns(5),
                    ]),
                    Tab::make(__('academics::filament.program.hub.courses'))->icon('heroicon-o-book-open')->schema([
                        RepeatableEntry::make('courses_hub')->hiddenLabel()->placeholder(__('academics::filament.hub.empty'))
                            ->getStateUsing(fn (): array => $this->courses())
                            ->schema([
                                TextEntry::make('level')->label(__('academics::filament.course.fields.level')),
                                TextEntry::make('code')->label(__('academics::filament.course.fields.code'))->copyable(),
                                TextEntry::make('name')->label(__('academics::filament.course.fields.name')),
                                TextEntry::make('session_mode')->label(__('academics::filament.course.fields.session_mode'))->badge(),
                                IconEntry::make('is_active')->label(__('academics::filament.course.fields.is_active'))->boolean(),
                                TextEntry::make('total_sessions')->label(__('academics::filament.course.fields.total_sessions')),
                            ])->columns(3),
                    ]),
                    Tab::make(__('academics::filament.program.hub.eligibility'))->icon('heroicon-o-shield-check')->schema([
                        TextEntry::make('eligibility.countries')->label(__('academics::filament.program.fields.countries'))->state(fn (): array => (array) data_get($this->hub(), 'eligibility.countries', []))->listWithLineBreaks()->placeholder(__('academics::filament.hub.unrestricted')),
                        TextEntry::make('eligibility.regions')->label(__('academics::filament.program.fields.regions'))->state(fn (): array => (array) data_get($this->hub(), 'eligibility.regions', []))->listWithLineBreaks()->placeholder(__('academics::filament.hub.unrestricted')),
                        TextEntry::make('eligibility.age')->label(__('academics::filament.program.fields.age_range'))->state(fn (): string => $this->ageRange(data_get($this->hub(), 'eligibility.age_from'), data_get($this->hub(), 'eligibility.age_to'))),
                        TextEntry::make('eligibility.gender')->label(__('academics::filament.program.fields.target_gender'))->state(fn (): string => (string) (data_get($this->hub(), 'eligibility.gender') ?? __('academics::filament.hub.unrestricted'))),
                        IconEntry::make('eligibility.manual')->label(__('academics::filament.program.fields.manual_approval_required'))->state(fn (): bool => (bool) data_get($this->hub(), 'eligibility.manual_approval_required', false))->boolean(),
                        TextEntry::make('eligibility.teacher_rule')->label(__('academics::filament.program.fields.teacher_gender_rule'))->state(fn (): string => (string) data_get($this->hub(), 'eligibility.teacher_gender_rule', 'any'))->badge(),
                    ])->columns(3),
                    Tab::make(__('academics::filament.program.hub.categories'))->icon('heroicon-o-tag')->schema([
                        RepeatableEntry::make('categories_hub')->hiddenLabel()->placeholder(__('academics::filament.hub.empty'))
                            ->getStateUsing(fn (): array => $this->hubList('categories'))
                            ->schema([
                                TextEntry::make('code')->label(__('academics::filament.category.fields.code'))->copyable(),
                                TextEntry::make('name')->label(__('academics::filament.category.fields.name')),
                                TextEntry::make('scope')->label(__('academics::filament.category.fields.scope'))->badge(),
                                TextEntry::make('courses_count')->label(__('academics::filament.program.fields.courses_count')),
                                IconEntry::make('is_active')->label(__('academics::filament.category.fields.is_active'))->boolean(),
                            ])->columns(5),
                    ]),
                ])->columnSpanFull(),
        ]);
    }

    private function createLevelAction(): Action
    {
        return Action::make('create_level')
            ->label(__('academics::filament.actions.create_level'))->icon('heroicon-o-plus')
            ->visible(fn (): bool => auth()->user()?->can('create', Level::class) ?? false)
            ->schema([
                TextInput::make('code')->label(__('academics::filament.level.fields.code'))->required()->default(fn (EntityCodeGenerator $codes): string => $codes->next('level', $this->program()->getKey()))->maxLength(8),
                TextInput::make('name.ar')->label(__('academics::filament.level.fields.name_ar'))->required()->maxLength(255),
                TextInput::make('name.en')->label(__('academics::filament.level.fields.name_en'))->maxLength(255),
                TextInput::make('sort_order')->label(__('academics::filament.level.fields.sort_order'))->numeric()->minValue(0)->default(fn (): int => count($this->hubList('levels')) + 1),
                $this->reasonField(),
            ])->action(function (array $data): void {
                $data['program_id'] = (string) $this->program()->getKey();
                $data['organization_id'] = (string) $this->program()->organization_id;
                app(CreateLevelAction::class)->execute($data, (string) auth()->id(), (string) $data['reason']);
                $this->refreshHub(__('academics::filament.actions.level_created'));
            });
    }

    private function createCategoryAction(): Action
    {
        return Action::make('create_category')
            ->label(__('academics::filament.actions.create_category'))->icon('heroicon-o-tag')
            ->visible(fn (): bool => auth()->user()?->can('create', ProgramCategory::class) ?? false)
            ->schema($this->categorySchema())
            ->action(function (array $data): void {
                $data['organization_id'] = (string) $this->program()->organization_id;
                $data['program_id'] = (string) $this->program()->getKey();
                app(CreateProgramCategoryAction::class)->execute($data, (string) auth()->id(), (string) $data['reason']);
                $this->refreshHub(__('academics::filament.actions.category_created'));
            });
    }

    private function updateCategoryAction(): Action
    {
        return Action::make('update_category')
            ->label(__('academics::filament.actions.update_category'))->icon('heroicon-o-pencil-square')
            ->visible(fn (): bool => $this->hubList('categories') !== [])
            ->schema([
                Select::make('category_id')
                    ->label(__('academics::filament.category.label'))->options(fn (): array => $this->categoryOptions())
                    ->required()->searchable()->live()
                    ->afterStateUpdated(function (mixed $state, Set $set): void {
                        if (!is_string($state) || $state === '') {
                            return;
                        }
                        $category = ProgramCategory::query()
                            ->where('organization_id', (string) $this->program()->organization_id)
                            ->find($state);
                        if ($category === null) {
                            return;
                        }
                        $set('code', $category->code);
                        $set('name.ar', $category->name['ar'] ?? null);
                        $set('name.en', $category->name['en'] ?? null);
                        $set('is_active', $category->is_active);
                    }),
                TextInput::make('code')->label(__('academics::filament.category.fields.code'))->maxLength(32),
                TextInput::make('name.ar')->label(__('academics::filament.category.fields.name_ar'))->maxLength(255),
                TextInput::make('name.en')->label(__('academics::filament.category.fields.name_en'))->maxLength(255),
                Toggle::make('is_active')->label(__('academics::filament.category.fields.is_active')),
                $this->reasonField(),
            ])->action(function (array $data): void {
                $category = ProgramCategory::query()->where('organization_id', (string) $this->program()->organization_id)->findOrFail((string) $data['category_id']);
                app(UpdateProgramCategoryAction::class)->execute($category, $data, (string) auth()->id(), (string) $data['reason']);
                $this->refreshHub(__('academics::filament.actions.category_updated'));
            });
    }

    private function archiveCategoryAction(): Action
    {
        return Action::make('archive_category')
            ->label(__('academics::filament.actions.archive_category'))->icon('heroicon-o-archive-box')->color('danger')
            ->visible(fn (): bool => $this->hubList('categories') !== [])->requiresConfirmation()
            ->schema([
                Select::make('category_id')->label(__('academics::filament.category.label'))->options(fn (): array => $this->categoryOptions())->required()->searchable(),
                $this->reasonField(),
            ])->action(function (array $data): void {
                $category = ProgramCategory::query()->where('organization_id', (string) $this->program()->organization_id)->findOrFail((string) $data['category_id']);
                app(ArchiveProgramCategoryAction::class)->execute($category, (string) auth()->id(), (string) $data['reason']);
                $this->refreshHub(__('academics::filament.actions.category_archived'));
            });
    }

    private function toggleActiveAction(): Action
    {
        return Action::make('toggle_active')
            ->label(fn (): string => $this->program()->is_active ? __('academics::filament.actions.deactivate') : __('academics::filament.actions.activate'))
            ->icon(fn (): string => $this->program()->is_active ? 'heroicon-o-pause' : 'heroicon-o-play')
            ->color('primary')
            ->visible(fn (): bool => auth()->user()?->can('update', $this->program()) ?? false)->requiresConfirmation()
            ->schema([$this->reasonField()])
            ->action(function (array $data): void {
                app(UpdateProgramAction::class)->execute($this->program(), ['is_active' => !$this->program()->is_active], (string) auth()->id(), (string) $data['reason']);
                $this->record->refresh();
                Notification::make()->title(__('academics::filament.actions.status_updated'))->success()->send();
            });
    }

    private function archiveAction(): Action
    {
        return Action::make('archive')
            ->label(__('academics::filament.actions.archive'))->icon('heroicon-o-archive-box')->color('danger')
            ->visible(fn (): bool => auth()->user()?->can('delete', $this->program()) ?? false)->requiresConfirmation()
            ->schema([$this->reasonField()])
            ->action(function (array $data): void {
                try {
                    app(ArchiveProgramAction::class)->execute($this->program(), (string) $data['reason'], (string) auth()->id());
                } catch (BusinessRuleViolation $violation) {
                    Notification::make()->title($violation->getMessage())->danger()->send();

                    return;
                }
                $this->redirect(ProgramFilamentResource::getUrl());
            });
    }

    /** @return array<int, Field> */
    private function categorySchema(): array
    {
        return [
            TextInput::make('code')->label(__('academics::filament.category.fields.code'))->required()->maxLength(32),
            TextInput::make('name.ar')->label(__('academics::filament.category.fields.name_ar'))->required()->maxLength(255),
            TextInput::make('name.en')->label(__('academics::filament.category.fields.name_en'))->maxLength(255),
            Select::make('parent_id')->label(__('academics::filament.category.fields.parent'))->options(fn (): array => $this->categoryOptions())->searchable(),
            TextInput::make('sort_order')->label(__('academics::filament.category.fields.sort_order'))->numeric()->minValue(0)->default(0),
            Toggle::make('is_active')->label(__('academics::filament.category.fields.is_active'))->default(true),
            $this->reasonField(),
        ];
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
        return $this->hubData ??= app(AcademicAdministrationQueryService::class)->programHub(
            (string) $this->program()->organization_id,
            (string) $this->program()->getKey(),
        );
    }

    /** @return list<array<string, mixed>> */
    private function hubList(string $section): array
    {
        $data = $this->hub()[$section] ?? [];

        return is_array($data) ? array_values($data) : [];
    }

    /** @return list<array<string, mixed>> */
    private function courses(): array
    {
        $courses = [];
        foreach ($this->hubList('levels') as $level) {
            foreach ((array) ($level['courses'] ?? []) as $course) {
                if (is_array($course)) {
                    $courses[] = ['level' => (string) ($level['name'] ?? ''), ...$course];
                }
            }
        }

        return $courses;
    }

    /** @return array<string, string> */
    private function categoryOptions(): array
    {
        return collect($this->hubList('categories'))->mapWithKeys(static fn (array $category): array => [
            (string) $category['id'] => sprintf('%s — %s', $category['code'], $category['name']),
        ])->all();
    }

    private function refreshHub(string $message): void
    {
        $this->hubData = null;
        Notification::make()->title($message)->success()->send();
    }

    private function program(): Program
    {
        abort_unless($this->record instanceof Program, 404);

        return $this->record;
    }

    private function ageRange(mixed $from, mixed $to): string
    {
        if ($from === null && $to === null) {
            return __('academics::filament.hub.unrestricted');
        }

        return sprintf('%s – %s', $from ?? '…', $to ?? '…');
    }
}
