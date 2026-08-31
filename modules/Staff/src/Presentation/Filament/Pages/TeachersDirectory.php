<?php

declare(strict_types=1);

namespace Modules\Staff\Presentation\Filament\Pages;

use App\Application\Queries\ProfileAdministrationQueryService;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Groups\Domain\Contracts\GroupAdministrationQueries;
use Modules\Identity\Domain\Contracts\AvatarQueries;
use Modules\Identity\Domain\Contracts\UserQueryService;
use Modules\Staff\Domain\Contracts\TeacherDirectoryQueries;
use Modules\Staff\Domain\Contracts\TeacherQualificationQueries;
use Modules\Staff\Domain\Enums\EmploymentType;
use Modules\Staff\Domain\Models\StaffProfile;
use Modules\Staff\Domain\ValueObjects\TeacherDirectoryData;
use Modules\Staff\Presentation\Filament\Resources\StaffProfileResource;

/**
 * دليل المعلمين التشغيلي — واجهة متخصصة فوق StaffProfile نفسه.
 *
 * لا جدول teachers ولا نسخة بيانات: المعلم هو ملف موظف نشط بحسب
 * التعريف القانوني في النظام. الضغط على الصف يفتح مركز عمليات
 * المعلم (ViewStaffProfile) وكل عمليات التعديل تبقى هناك.
 */
final class TeachersDirectory extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?int $navigationSort = 2;

    protected string $view = 'staff::filament.teachers-directory';

    /** @var array<string, TeacherDirectoryData>|null */
    private ?array $pageDirectory = null;

    public static function getNavigationGroup(): string
    {
        return __('staff::filament.navigation_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('staff::filament.teachers.label');
    }

    public function getTitle(): string
    {
        return __('staff::filament.teachers.title');
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('staff.view.any');
    }

    /**
     * استعلام الأساس على جدول الموديول نفسه مع عزل المؤسسة.
     *
     * @return Builder<StaffProfile>
     */
    public function getTableQuery(): Builder
    {
        $organizationId = data_get(auth()->user(), 'organization_id');
        $query = StaffProfile::query();

        if (!is_string($organizationId) || $organizationId === '') {
            return $query->whereRaw('1 = 0');
        }

        return $query->forOrganization($organizationId);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                ImageColumn::make('avatar')
                    ->label(__('staff::filament.teachers.fields.avatar'))
                    ->circular()
                    ->grow(false)
                    ->extraImgAttributes(['loading' => 'lazy'])
                    ->state(function (StaffProfile $record): ?string {
                        $data = $this->dataFor($record);

                        if ($data === null || $data->avatarPath === null) {
                            return null;
                        }

                        return app(AvatarQueries::class)->resolve($data->avatarPath, $record->gender?->value)->url;
                    })
                    ->defaultImageUrl(fn (StaffProfile $record): string => app(AvatarQueries::class)
                        ->defaultUrl($record->gender?->value))
                    ->alt(function (StaffProfile $record): string {
                        $data = $this->dataFor($record);
                        $name = $data !== null ? $data->name : (string) $record->staff_code;

                        return __('identity::avatars.alt', ['name' => $name]);
                    }),
                TextColumn::make('name')
                    ->label(__('staff::filament.teachers.fields.name'))
                    ->state(function (StaffProfile $record): string {
                        $data = $this->dataFor($record);

                        return (string) ($data !== null ? $data->name : $record->staff_code);
                    })
                    ->sortable(false),
                TextColumn::make('staff_code')
                    ->label(__('staff::filament.profile.fields.staff_code'))
                    ->copyable(),
                TextColumn::make('account_status')
                    ->label(__('staff::filament.teachers.fields.account_status'))
                    ->badge()
                    ->state(function (StaffProfile $record): string {
                        $data = $this->dataFor($record);

                        return $data !== null ? $data->accountStatus : 'unknown';
                    })
                    ->formatStateUsing(static fn (string $state): string => __('identity::status.'.$state))
                    ->color(static fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'suspended' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('employment_type')
                    ->label(__('staff::filament.profile.fields.employment_type'))
                    ->badge()
                    ->formatStateUsing(fn (?EmploymentType $state): ?string => $state?->label())
                    ->color(fn (?EmploymentType $state): string => $state?->color() ?? 'gray'),
                TextColumn::make('qualified_courses_count')
                    ->label(__('staff::filament.teachers.fields.qualified_courses'))
                    ->state(fn (StaffProfile $record): int => $this->intMetric($record, 'qualifiedCoursesCount'))
                    ->numeric()
                    ->alignCenter(),
                TextColumn::make('active_groups_count')
                    ->label(__('staff::filament.teachers.fields.active_groups'))
                    ->state(fn (StaffProfile $record): int => $this->intMetric($record, 'activeGroups'))
                    ->numeric()
                    ->alignCenter(),
                TextColumn::make('upcoming_sessions_count')
                    ->label(__('staff::filament.teachers.fields.upcoming_sessions'))
                    ->state(fn (StaffProfile $record): int => $this->intMetric($record, 'upcomingSessions'))
                    ->numeric()
                    ->alignCenter()
                    ->toggleable(),
                TextColumn::make('completed_this_month_count')
                    ->label(__('staff::filament.teachers.fields.completed_this_month'))
                    ->state(fn (StaffProfile $record): int => $this->intMetric($record, 'completedThisMonth'))
                    ->numeric()
                    ->alignCenter(),
                TextColumn::make('cancelled_this_month_count')
                    ->label(__('staff::filament.teachers.fields.cancelled_this_month'))
                    ->state(fn (StaffProfile $record): int => $this->intMetric($record, 'cancelledThisMonth'))
                    ->numeric()
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('availability')
                    ->label(__('staff::filament.teachers.fields.availability'))
                    ->boolean()
                    ->state(fn (StaffProfile $record): bool => (bool) $this->dataFor($record)?->hasApprovedAvailability)
                    ->trueColor('success')
                    ->falseColor('gray'),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('staff::filament.profile.filters.active'))
                    ->queries(
                        true: fn ($query) => $query->whereNull('terminated_at'),
                        false: fn ($query) => $query->whereNotNull('terminated_at'),
                    ),
                SelectFilter::make('employment_type')
                    ->label(__('staff::filament.profile.fields.employment_type'))
                    ->options(collect(EmploymentType::cases())->mapWithKeys(
                        fn (EmploymentType $type): array => [$type->value => $type->label()],
                    )->all()),
                SelectFilter::make('course_id')
                    ->label(__('staff::filament.teachers.filters.qualified_course'))
                    ->options(fn (): array => self::courseOptions())
                    ->query(function (Builder $query, array $data): Builder {
                        $courseId = $data['value'] ?? null;

                        return is_string($courseId) && $courseId !== ''
                            ? $query->whereIn(
                                'id',
                                app(TeacherQualificationQueries::class)->qualifiedTeacherIdsForCourse($courseId),
                            )
                            : $query;
                    }),
                SelectFilter::make('group_id')
                    ->label(__('staff::filament.teachers.filters.group'))
                    ->options(fn (): array => self::groupOptions())
                    ->query(function (Builder $query, array $data): Builder {
                        $groupId = $data['value'] ?? null;

                        return is_string($groupId) && $groupId !== ''
                            ? $query->whereIn('id', collect(
                                app(GroupAdministrationQueries::class)->assignmentsForGroup(
                                    self::organizationId(),
                                    $groupId,
                                ),
                            )->pluck('staffProfileId')->unique()->values()->all())
                            : $query;
                    }),
                TernaryFilter::make('availability')
                    ->label(__('staff::filament.teachers.fields.availability'))
                    ->queries(
                        true: fn (Builder $query) => $query->whereIn(
                            'id',
                            app(TeacherDirectoryQueries::class)->withActiveAvailability(self::allProfileIds()),
                        ),
                        false: fn (Builder $query) => $query->whereNotIn(
                            'id',
                            app(TeacherDirectoryQueries::class)->withActiveAvailability(self::allProfileIds()),
                        ),
                    ),
            ])
            ->recordActions([
                Action::make('open_teacher_hub')
                    ->label(__('staff::filament.teachers.open_hub'))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (StaffProfile $record): string => StaffProfileResource::getUrl('view', ['record' => $record])),
                Action::make('edit_teacher')
                    ->label(__('staff::filament.teachers.edit'))
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn (): bool => (bool) auth()->user()?->can('staff.contract.update'))
                    ->url(fn (StaffProfile $record): string => StaffProfileResource::getUrl('edit', ['record' => $record])),
            ])
            ->bulkActions([]);
    }

    /**
     * البحث العام يعبر حدود الموديولات عبر عقد Identity المعلن فقط:
     * الاسم والبريد والهاتف تُبحث في users، والكود الوظيفي في جدول الموديول.
     *
     * @param Builder<StaffProfile> $query
     * @return Builder<StaffProfile>
     */
    protected function applySearchToTableQuery(Builder $query): Builder
    {
        $search = trim((string) $this->getTableSearch());

        if ($search === '') {
            return $query;
        }

        $userIds = app(UserQueryService::class)->searchUserIdsForOrganization(self::organizationId(), $search);

        return $query->where(static function (Builder $inner) use ($userIds, $search): void {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
            $inner->whereIn('user_id', $userIds)->orWhere('staff_code', 'ilike', $like);
        });
    }

    /**
     * دليل الصفحة الحالية يُحسب مرة واحدة دفعة واحدة — لا N+1.
     *
     * @return array<string, TeacherDirectoryData>
     */
    private function directory(): array
    {
        if ($this->pageDirectory !== null) {
            return $this->pageDirectory;
        }

        $records = $this->getTableRecords();

        $recordsCollection = ($records instanceof Paginator || $records instanceof CursorPaginator)
            && method_exists($records, 'getCollection')
            ? $records->getCollection()
            : $records;

        $ids = $recordsCollection instanceof Collection
            ? $recordsCollection->pluck('id')->map(static fn (mixed $id): string => (string) $id)->all()
            : [];

        return $this->pageDirectory = app(TeacherDirectoryQueries::class)->directoryFor(
            self::organizationId(),
            $ids,
        );
    }

    private function dataFor(StaffProfile $record): ?TeacherDirectoryData
    {
        return $this->directory()[(string) $record->id] ?? null;
    }

    private function intMetric(StaffProfile $record, string $property): int
    {
        $data = $this->dataFor($record);

        return $data !== null ? max(0, (int) $data->{$property}) : 0;
    }

    /**
     * كل ملفات المؤسسة — للفلتر القائم على التوافر.
     *
     * @return list<string>
     */
    private function allProfileIds(): array
    {
        return $this->getTableQuery()
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();
    }

    private static function organizationId(): string
    {
        $organizationId = data_get(auth()->user(), 'organization_id');

        return is_string($organizationId) ? $organizationId : '';
    }

    /**
     * خيارات الكورسات عبر طبقة التركيب إن توفرت.
     *
     * @return array<string, string>
     */
    private static function courseOptions(): array
    {
        try {
            /** @var array<string, string> $options */
            $options = app(ProfileAdministrationQueryService::class)->allCourseOptions(self::organizationId());

            return $options;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * خيارات المجموعات النشطة من عقد Groups المعلن.
     *
     * @return array<string, string>
     */
    private static function groupOptions(): array
    {
        $locale = app()->getLocale();
        $options = [];

        foreach (app(GroupAdministrationQueries::class)->activeGroupsForScheduling(self::organizationId()) as $group) {
            $names = $group->name;
            $name = (string) ($names[$locale] ?? $names['ar'] ?? $names['en'] ?? '');
            $options[$group->id] = trim((string) $group->code.' — '.$name, ' —');
        }

        return $options;
    }
}
