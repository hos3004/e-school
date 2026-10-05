<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Presentation\Filament\Resources;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\AcademicReports\Domain\Enums\MonthlyReportStatus;
use Modules\AcademicReports\Domain\Models\MonthlyReport;
use Modules\Academics\Domain\Contracts\AcademicCatalogQueries;
use Modules\Enrollments\Domain\Contracts\EnrollmentAdministrationQueries;
use Modules\Enrollments\Domain\Enums\EnrollmentStatus;
use Modules\Enrollments\Domain\ValueObjects\EnrollmentSummaryData;
use Modules\Students\Domain\Contracts\StudentDirectoryQueries;

/**
 * مورد إدارة التقارير الشهرية في لوحة الإدارة.
 */
final class MonthlyReportResource extends Resource
{
    protected static ?string $model = MonthlyReport::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?int $navigationSort = 52;

    // الخانة معلنة هنا لا في الصنف الأب: `$navigationParentItem` في Filament
    // مشتركة بين كل الموارد، فبلا إعادة إعلانها يدهس آخرُ إسناد ما قبله.
    // القيمة نفسها تُضبط مركزيًا في App\Filament\AdminNavigation.
    protected static ?string $navigationParentItem = null;

    public static function getNavigationGroup(): string
    {
        return __('academicreports::navigation.group');
    }

    public static function getModelLabel(): string
    {
        return __('academicreports::navigation.monthly_report.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('academicreports::navigation.monthly_report.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('academicreports::fields.period'))
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('student_profile_id')
                            ->label(__('academicreports::fields.student_profile'))
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => app(StudentDirectoryQueries::class)
                                ->searchNames(self::organizationId() ?? '', $search))
                            ->getOptionLabelUsing(fn (mixed $value): ?string => is_string($value)
                                ? (app(StudentDirectoryQueries::class)->namesForProfiles(self::organizationId() ?? '', [$value])[$value] ?? null)
                                : null)
                            ->live()
                            ->afterStateUpdated(fn (Set $set): mixed => $set('enrollment_id', null))
                            ->required(),
                        Select::make('enrollment_id')
                            ->label(__('academicreports::fields.enrollment'))
                            ->options(fn (Get $get): array => self::enrollmentOptions(
                                is_string($get('student_profile_id')) ? $get('student_profile_id') : null,
                            ))
                            ->searchable()
                            ->required(),
                        TextInput::make('period_year')
                            ->label(__('academicreports::fields.period_year'))
                            ->required()
                            ->integer()
                            ->minValue(2000)
                            ->maxValue(2100),
                        Select::make('period_month')
                            ->label(__('academicreports::fields.period_month'))
                            ->required()
                            ->options(array_combine(range(1, 12), array_map(
                                static fn (int $m): string => __('academicreports::months.'.$m),
                                range(1, 12),
                            ))),
                    ]),
                ]),
            Section::make(__('academicreports::fields.content'))
                ->schema([
                    KeyValue::make('metrics')
                        ->label(__('academicreports::fields.metrics'))
                        ->columnSpanFull(),
                    Textarea::make('supervisor_summary')
                        ->label(__('academicreports::fields.supervisor_summary'))
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label(__('academicreports::fields.id'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('student_profile_id')
                    ->label(__('academicreports::fields.student_profile'))
                    // كان ULID خامًا — يُعرض اسم الطالب عبر عقد Students المعلن.
                    ->formatStateUsing(static fn ($state): string => self::studentNames()[(string) $state]
                        ?? (string) $state)
                    ->searchable(),
                TextColumn::make('period_year')
                    ->label(__('academicreports::fields.period_year'))
                    ->sortable(),
                TextColumn::make('period_month')
                    ->label(__('academicreports::fields.period_month'))
                    ->formatStateUsing(fn ($state): string => __('academicreports::months.'.(int) $state))
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('academicreports::fields.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof MonthlyReportStatus
                        ? $state->label()
                        : (string) $state)
                    ->color(fn ($state): string => $state instanceof MonthlyReportStatus
                        ? $state->color()
                        : 'gray'),
                TextColumn::make('approved_at')
                    ->label(__('academicreports::fields.approved_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('sent_at')
                    ->label(__('academicreports::fields.sent_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('student_profile_id')
                    ->label(__('academicreports::fields.student_profile'))
                    ->options(fn (): array => self::studentNames())
                    ->searchable(),

                SelectFilter::make('period_year')
                    ->label(__('academicreports::fields.period_year'))
                    ->options(function (): array {
                        $organizationId = self::organizationId();

                        if ($organizationId === null) {
                            return [];
                        }

                        return MonthlyReport::query()
                            ->forOrganization($organizationId)
                            ->pluck('period_year')
                            ->unique()
                            ->sortDesc()
                            ->mapWithKeys(static fn ($year): array => [(string) $year => (string) $year])
                            ->all();
                    }),

                SelectFilter::make('period_month')
                    ->label(__('academicreports::fields.period_month'))
                    ->options(array_combine(range(1, 12), array_map(
                        static fn (int $m): string => __('academicreports::months.'.$m),
                        range(1, 12),
                    ))),

                SelectFilter::make('status')
                    ->label(__('academicreports::fields.status'))
                    ->options(collect(MonthlyReportStatus::cases())
                        ->mapWithKeys(fn (MonthlyReportStatus $s): array => [$s->value => $s->label()])
                        ->all()),
            ])
            ->defaultSort('created_at', direction: 'desc');
    }

    /**
     * نطاق المؤسسة دائمًا — لا يرى المشرف تقارير مؤسسة غيره.
     *
     * @return Builder<MonthlyReport>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<MonthlyReport> $query */
        $query = parent::getEloquentQuery();
        $organizationId = self::organizationId();

        return $organizationId === null
            ? $query->whereRaw('1 = 0')
            : $query->forOrganization($organizationId);
    }

    public static function getPages(): array
    {
        return [
            'index' => MonthlyReportResource\Pages\ListMonthlyReports::route('/'),
            'create' => MonthlyReportResource\Pages\CreateMonthlyReport::route('/create'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function studentNames(): array
    {
        $organizationId = self::organizationId();

        if ($organizationId === null) {
            return [];
        }

        return app(StudentDirectoryQueries::class)->namesForProfiles(
            $organizationId,
            MonthlyReport::query()->forOrganization($organizationId)->pluck('student_profile_id')->all(),
        );
    }

    private static function organizationId(): ?string
    {
        $organizationId = data_get(auth()->user(), 'organization_id');

        return is_string($organizationId) && $organizationId !== ''
            ? $organizationId
            : null;
    }

    /** @return array<string, string> */
    private static function enrollmentOptions(?string $studentProfileId): array
    {
        $organizationId = self::organizationId();

        if ($organizationId === null || $studentProfileId === null || $studentProfileId === '') {
            return [];
        }

        $enrollments = app(EnrollmentAdministrationQueries::class)->forStudent($organizationId, $studentProfileId);
        $programs = app(AcademicCatalogQueries::class)->programsByIds(
            $organizationId,
            collect($enrollments)->map(static fn (EnrollmentSummaryData $item): string => $item->programId)->all(),
        );
        $locale = app()->getLocale();

        return collect($enrollments)->mapWithKeys(static function (EnrollmentSummaryData $enrollment) use ($programs, $locale): array {
            $program = $programs[$enrollment->programId] ?? null;
            $programName = $program === null
                ? __('academicreports::fields.enrollment')
                : ($program->name[$locale]
                    ?? $program->name[(string) config('app.fallback_locale', 'en')]
                    ?? $program->code);
            $status = EnrollmentStatus::tryFrom($enrollment->status)?->label() ?? $enrollment->status;

            return [$enrollment->id => $programName.' · '.$status];
        })->all();
    }
}
