<?php

declare(strict_types=1);

namespace Modules\Groups\Presentation\Filament\Resources\GroupResource\Pages;

use App\Application\Actions\AssignStudentToGroupAction;
use App\Application\Queries\ProfileAdministrationQueryService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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
use Illuminate\Support\Facades\Route;
use Modules\Groups\Application\Actions\ActivateGroupAction;
use Modules\Groups\Application\Actions\ArchiveGroupAction;
use Modules\Groups\Application\Actions\AssignTeacherAction;
use Modules\Groups\Application\Actions\AttachProgramAction;
use Modules\Groups\Application\Actions\CompleteGroupAction;
use Modules\Groups\Domain\Enums\GroupStatus;
use Modules\Groups\Domain\Enums\GroupTeacherRole;
use Modules\Groups\Domain\Models\Group;
use Modules\Groups\Presentation\Filament\Resources\GroupResource;
use Shared\Support\BusinessRuleViolation;

final class ViewGroup extends ViewRecord
{
    protected static string $resource = GroupResource::class;

    /** @var array<string, mixed>|null */
    private ?array $hubData = null;

    protected function getHeaderActions(): array
    {
        return [
            $this->placeStudentAction(),
            $this->assignTeacherAction(),
            $this->attachProgramAction(),
            $this->scheduleSessionsAction(),
            $this->transitionAction('activate', GroupStatus::Planning, ActivateGroupAction::class),
            $this->transitionAction('complete', GroupStatus::Active, CompleteGroupAction::class),
            $this->archiveAction(),
            EditAction::make()
                ->visible(fn (): bool => auth()->user()?->can('update', $this->group()) ?? false),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('groups::filament.hub.overview'))
                ->icon('heroicon-o-user-group')
                ->schema([
                    TextEntry::make('code')->label(__('groups::attributes.code'))->copyable(),
                    TextEntry::make('name')
                        ->label(__('groups::attributes.name'))
                        ->formatStateUsing(fn (mixed $state): string => self::localized($state)),
                    TextEntry::make('status')
                        ->label(__('groups::attributes.status'))
                        ->badge()
                        ->formatStateUsing(fn (?GroupStatus $state): ?string => $state?->label()),
                    TextEntry::make('capacity')->label(__('groups::attributes.capacity')),
                    TextEntry::make('active_members')
                        ->label(__('groups::filament.active_members_count'))
                        ->state(fn (Group $record): int => $record->memberships()->active()->count()),
                    TextEntry::make('available_places')
                        ->label(__('groups::filament.hub.available_places'))
                        ->state(fn (Group $record): int => max(0, (int) $record->capacity - $record->memberships()->active()->count())),
                    TextEntry::make('timezone')->label(__('groups::attributes.timezone')),
                    TextEntry::make('starts_on')->label(__('groups::attributes.starts_on'))->date(),
                    TextEntry::make('ends_on')->label(__('groups::attributes.ends_on'))->date(),
                ])->columns(3),

            Tabs::make(__('groups::filament.hub.title'))
                ->persistTabInQueryString('group-hub')
                ->tabs([
                    $this->tab('programs', 'heroicon-o-academic-cap', [
                        TextEntry::make('program')->label(__('groups::attributes.program_id')),
                        TextEntry::make('code')->label(__('groups::attributes.code'))->copyable(),
                    ]),
                    $this->tab('teachers', 'heroicon-o-user-circle', [
                        TextEntry::make('teacher')->label(__('groups::filament.hub.fields.teacher')),
                        TextEntry::make('course')->label(__('groups::attributes.course_id')),
                        TextEntry::make('role')->label(__('groups::attributes.role'))->badge(),
                        TextEntry::make('assigned_from')->label(__('groups::attributes.assigned_from'))->date(),
                        TextEntry::make('assigned_to')->label(__('groups::attributes.assigned_to'))->date(),
                    ]),
                    $this->tab('students', 'heroicon-o-users', [
                        TextEntry::make('student')->label(__('groups::filament.hub.fields.student')),
                        TextEntry::make('student_code')->label(__('groups::filament.hub.fields.student_code'))->copyable(),
                        TextEntry::make('status')->label(__('groups::attributes.status'))->badge(),
                        TextEntry::make('joined_at')->label(__('groups::attributes.joined_at'))->dateTime(),
                        TextEntry::make('left_at')->label(__('groups::attributes.left_at'))->dateTime(),
                    ]),
                    $this->tab('sessions', 'heroicon-o-calendar-days', [
                        TextEntry::make('title')->label(__('groups::filament.hub.fields.session')),
                        TextEntry::make('course')->label(__('groups::attributes.course_id')),
                        TextEntry::make('teacher')->label(__('groups::filament.hub.fields.teacher')),
                        TextEntry::make('status')->label(__('groups::attributes.status'))->badge(),
                        TextEntry::make('scheduled_start')->label(__('groups::filament.hub.fields.scheduled_start'))->dateTime(),
                        TextEntry::make('scheduled_end')->label(__('groups::filament.hub.fields.scheduled_end'))->dateTime(),
                    ]),
                ])->columnSpanFull(),
        ]);
    }

    /** @param array<int, TextEntry> $entries */
    private function tab(string $section, string $icon, array $entries): Tab
    {
        return Tab::make(__('groups::filament.hub.'.$section))
            ->icon($icon)
            ->schema([
                RepeatableEntry::make($section.'_hub')
                    ->hiddenLabel()
                    ->placeholder(__('groups::filament.hub.empty'))
                    ->getStateUsing(fn (): array => $this->hub($section))
                    ->schema($entries)
                    ->columns(3),
            ]);
    }

    private function placeStudentAction(): Action
    {
        return Action::make('place_student')
            ->label(__('groups::filament.actions.place_student'))
            ->icon('heroicon-o-user-plus')
            ->color('primary')
            ->visible(fn (): bool => $this->group()->status === GroupStatus::Active
                && auth()->user()->can('enrollStudent', $this->group()) === true
                && auth()->user()->can('enrollment.create') === true)
            ->schema([
                Select::make('student_profile_id')
                    ->label(__('groups::filament.hub.fields.student'))
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => app(ProfileAdministrationQueryService::class)
                        ->studentOptions($this->organizationId(), $search))
                    ->getOptionLabelUsing(fn (mixed $value): ?string => is_string($value)
                        ? app(ProfileAdministrationQueryService::class)->studentOptionLabel($this->organizationId(), $value)
                        : null)
                    ->required(),
                Select::make('program_id')
                    ->label(__('groups::attributes.program_id'))
                    ->options(fn (): array => app(ProfileAdministrationQueryService::class)->programOptions($this->organizationId()))
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function (Set $set): void {
                        $set('course_id', null);
                    })
                    ->required(),
                Select::make('course_id')
                    ->label(__('groups::attributes.course_id'))
                    ->options(fn (Get $get): array => app(ProfileAdministrationQueryService::class)->courseOptions(
                        $this->organizationId(),
                        is_string($get('program_id')) ? $get('program_id') : null,
                    ))
                    ->searchable()
                    ->preload()
                    ->required(),
                $this->reasonField(),
            ])
            ->action(function (array $data): void {
                try {
                    app(AssignStudentToGroupAction::class)->execute(
                        actorOrganizationId: $this->organizationId(),
                        studentProfileId: (string) $data['student_profile_id'],
                        programId: (string) $data['program_id'],
                        groupId: (string) $this->group()->getKey(),
                        courseId: (string) $data['course_id'],
                        actorId: (string) auth()->id(),
                        correlationId: request()->header('X-Correlation-Id'),
                        reason: (string) $data['reason'],
                    );
                } catch (BusinessRuleViolation $violation) {
                    Notification::make()->title($violation->getMessage())->danger()->send();

                    return;
                }

                $this->hubData = null;
                Notification::make()->title(__('groups::filament.actions.student_placed'))->success()->send();
            });
    }

    private function assignTeacherAction(): Action
    {
        return Action::make('assign_teacher')
            ->label(__('groups::filament.actions.assign_teacher'))
            ->icon('heroicon-o-user-plus')
            ->visible(fn (): bool => $this->group()->status->acceptsMembers()
                && auth()->user()->can('assignTeacher', $this->group()) === true)
            ->schema([
                Select::make('staff_profile_id')
                    ->label(__('groups::attributes.staff_profile_id'))
                    ->options(fn (): array => app(ProfileAdministrationQueryService::class)->teacherOptions($this->organizationId()))
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('course_id')
                    ->label(__('groups::attributes.course_id'))
                    ->options(fn (): array => app(ProfileAdministrationQueryService::class)->allCourseOptions($this->organizationId()))
                    ->searchable()
                    ->preload(),
                Select::make('role')
                    ->label(__('groups::attributes.role'))
                    ->options(collect(GroupTeacherRole::cases())->mapWithKeys(
                        static fn (GroupTeacherRole $role): array => [$role->value => $role->label()],
                    )->all())
                    ->default(GroupTeacherRole::Lead->value)
                    ->required(),
                DatePicker::make('assigned_from')
                    ->label(__('groups::attributes.assigned_from'))
                    ->default(now()->toDateString())
                    ->required(),
                DatePicker::make('assigned_to')
                    ->label(__('groups::attributes.assigned_to'))
                    ->afterOrEqual('assigned_from'),
                $this->reasonField(),
            ])
            ->action(function (array $data): void {
                try {
                    app(AssignTeacherAction::class)->execute(
                        $this->group(),
                        $data,
                        (string) auth()->id(),
                        (string) $data['reason'],
                    );
                } catch (BusinessRuleViolation $violation) {
                    Notification::make()->title($violation->getMessage())->danger()->send();

                    return;
                }

                $this->hubData = null;
                Notification::make()->title(__('groups::filament.actions.teacher_assigned'))->success()->send();
            });
    }

    private function attachProgramAction(): Action
    {
        return Action::make('attach_program')
            ->label(__('groups::filament.actions.attach_program'))
            ->icon('heroicon-o-link')
            ->visible(fn (): bool => $this->group()->status->acceptsMembers()
                && auth()->user()->can('attachProgram', $this->group()) === true)
            ->schema([
                Select::make('program_id')
                    ->label(__('groups::attributes.program_id'))
                    ->options(fn (): array => app(ProfileAdministrationQueryService::class)->programOptions($this->organizationId()))
                    ->searchable()
                    ->preload()
                    ->required(),
                $this->reasonField(),
            ])
            ->action(function (array $data): void {
                try {
                    app(AttachProgramAction::class)->execute(
                        $this->group(),
                        (string) $data['program_id'],
                        (string) auth()->id(),
                        (string) $data['reason'],
                    );
                } catch (BusinessRuleViolation $violation) {
                    Notification::make()->title($violation->getMessage())->danger()->send();

                    return;
                }

                $this->hubData = null;
                Notification::make()->title(__('groups::filament.actions.program_attached'))->success()->send();
            });
    }

    /** @param class-string<ActivateGroupAction|CompleteGroupAction> $actionClass */
    private function transitionAction(string $name, GroupStatus $from, string $actionClass): Action
    {
        return Action::make($name)
            ->label(__('groups::filament.actions.'.$name))
            ->icon($name === 'activate' ? 'heroicon-o-play' : 'heroicon-o-check-circle')
            ->color($name === 'activate' ? 'success' : 'primary')
            ->visible(fn (): bool => $this->group()->status === $from
                && auth()->user()?->can($name, $this->group()) === true)
            ->requiresConfirmation()
            ->schema([$this->reasonField()])
            ->action(function (array $data) use ($actionClass): void {
                app($actionClass)->execute(
                    $this->group(),
                    (string) auth()->id(),
                    (string) $data['reason'],
                );
                $this->record->refresh();
                Notification::make()->title(__('groups::filament.actions.'.$this->group()->status->value.'_success'))->success()->send();
            });
    }

    /**
     * الانتقال إلى إنشاء جدول بالمجموعة مُسبقة الاختيار.
     *
     * كان المنسّق يُسند المعلم ويضع الطلاب ثم يقف: لا شيء في صفحة المجموعة
     * يقود إلى تحديد المواعيد، فيغادرها ويفتح الجداول ويعيد اختيار المجموعة
     * نفسها من قائمة طويلة.
     *
     * الوجهة باسم المسار لا باستيراد `ScheduleResource`، والصلاحية بالاسم لا
     * بنموذج `Schedule`: موديول Groups لا يستورد طبقة عرض موديول Scheduling
     * ولا نماذجه (البندان 1 و2). وغياب المسار يُخفي الزر بدل أن يرمي
     * RouteNotFoundException ويُسقط الصفحة.
     */
    private function scheduleSessionsAction(): Action
    {
        $route = 'filament.admin.resources.schedules.create';

        return Action::make('schedule_sessions')
            ->label(__('groups::filament.actions.schedule_sessions'))
            ->icon('heroicon-o-calendar-days')
            ->color('primary')
            ->visible(fn (): bool => Route::has($route)
                && $this->group()->status === GroupStatus::Active
                && (auth()->user()?->can('schedule.manage') ?? false))
            ->url(fn (): string => route($route, ['group' => (string) $this->group()->getKey()]));
    }

    private function archiveAction(): Action
    {
        return Action::make('archive')
            ->label(__('groups::filament.actions.archive'))
            ->icon('heroicon-o-archive-box')
            ->color('danger')
            ->visible(fn (): bool => auth()->user()?->can('delete', $this->group()) ?? false)
            ->requiresConfirmation()
            ->schema([$this->reasonField()])
            ->action(function (array $data): void {
                app(ArchiveGroupAction::class)->execute(
                    $this->group(),
                    (string) $data['reason'],
                    (string) auth()->id(),
                );
                $this->redirect(GroupResource::getUrl());
            });
    }

    private function reasonField(): Textarea
    {
        return Textarea::make('reason')
            ->label(__('groups::attributes.reason'))
            ->maxLength(1000)
            ->required();
    }

    /** @return list<array<string, mixed>> */
    private function hub(string $section): array
    {
        $this->hubData ??= app(ProfileAdministrationQueryService::class)->groupHub(
            $this->organizationId(),
            (string) $this->group()->getKey(),
        );

        $data = $this->hubData[$section] ?? [];

        return is_array($data) ? array_values($data) : [];
    }

    private function group(): Group
    {
        abort_unless($this->record instanceof Group, 404);

        return $this->record;
    }

    private function organizationId(): string
    {
        return (string) $this->group()->organization_id;
    }

    private static function localized(mixed $value): string
    {
        if (!is_array($value)) {
            return (string) ($value ?? '');
        }

        $localized = $value[app()->getLocale()] ?? $value['ar'] ?? $value['en'] ?? reset($value);

        return is_scalar($localized) ? (string) $localized : '';
    }
}
