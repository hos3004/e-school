<?php

declare(strict_types=1);

namespace Modules\Reporting\Presentation\Filament\Resources;

use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Reporting\Domain\Models\StudentDashboard;
use Modules\Students\Domain\Contracts\StudentDirectoryQueries;
use Shared\Concerns\ScopesFilamentToOrganization;

/**
 * مورد لوحات الطلاب في لوحة الإدارة — قراءة وتصحيح موثّق فقط.
 */
final class StudentDashboardResource extends Resource
{
    use ScopesFilamentToOrganization;

    protected static ?string $model = StudentDashboard::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?int $navigationSort = 90;

    // الخانة معلنة هنا لا في الصنف الأب: `$navigationParentItem` في Filament
    // مشتركة بين كل الموارد، فبلا إعادة إعلانها يدهس آخرُ إسناد ما قبله.
    // القيمة نفسها تُضبط مركزيًا في App\Filament\AdminNavigation.
    protected static ?string $navigationParentItem = null;

    public static function getNavigationGroup(): string
    {
        return __('reporting::navigation.group');
    }

    public static function getModelLabel(): string
    {
        return __('reporting::navigation.student.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('reporting::navigation.student.plural');
    }

    public static function canCreate(): bool
    {
        return false; // اللوحات تُبنى بالأحداث — لا إنشاء يدوي.
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('reporting::sections.counters'))
                ->schema([
                    Grid::make(3)->schema([
                        TextInput::make('sessions_total')
                            ->label(__('reporting::fields.sessions_total'))
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                        TextInput::make('sessions_attended')
                            ->label(__('reporting::fields.sessions_attended'))
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                        TextInput::make('sessions_missed')
                            ->label(__('reporting::fields.sessions_missed'))
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                        TextInput::make('violations_count')
                            ->label(__('reporting::fields.violations_count'))
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                        TextInput::make('freezes_count')
                            ->label(__('reporting::fields.freezes_count'))
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                    ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label(__('reporting::fields.id'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('enrollment_id')
                    ->label(__('reporting::fields.enrollment'))
                    ->copyable()
                    ->limit(12)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('student_profile_id')
                    ->label(__('reporting::fields.student'))
                    // كان ULID خامًا — يُعرض اسم الطالب عبر عقد Students المعلن.
                    ->formatStateUsing(static fn ($state): string => self::studentNames()[(string) $state]
                        ?? (string) $state)
                    ->toggleable(),
                TextColumn::make('sessions_total')
                    ->label(__('reporting::fields.sessions_total'))
                    ->sortable()
                    ->alignEnd(),
                TextColumn::make('sessions_attended')
                    ->label(__('reporting::fields.sessions_attended'))
                    ->sortable()
                    ->alignEnd(),
                TextColumn::make('sessions_missed')
                    ->label(__('reporting::fields.sessions_missed'))
                    ->sortable()
                    ->alignEnd(),
                TextColumn::make('attendance_rate_bp')
                    ->label(__('reporting::fields.attendance_rate'))
                    ->formatStateUsing(fn ($state): string => number_format(((int) $state) / 100, 1).'%')
                    ->badge()
                    ->color(fn ($state): string => ((int) $state) >= (int) config('reporting.thresholds.at_risk_max_rate_bp', 7000)
                        ? 'success'
                        : 'danger')
                    ->sortable(),
                TextColumn::make('violations_count')
                    ->label(__('reporting::fields.violations_count'))
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(),
                TextColumn::make('freezes_count')
                    ->label(__('reporting::fields.freezes_count'))
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(),
                TextColumn::make('last_session_at')
                    ->label(__('reporting::fields.last_session_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('student_profile_id')
                    ->label(__('reporting::fields.student'))
                    ->options(fn (): array => self::studentNames())
                    ->searchable(),

                Filter::make('at_risk')
                    ->label(__('reporting::filters.at_risk'))
                    ->query(static fn (Builder $query): Builder => $query->where(
                        'attendance_rate_bp',
                        '<',
                        (int) config('reporting.thresholds.at_risk_max_rate_bp', 7000),
                    )),

                TernaryFilter::make('has_violations')
                    ->label(__('reporting::filters.has_violations'))
                    ->queries(
                        true: fn ($query) => $query->where('violations_count', '>', 0),
                        false: fn ($query) => $query->where('violations_count', 0),
                    ),
            ])
            ->defaultSort('attendance_rate_bp');
    }

    public static function getPages(): array
    {
        return [
            'index' => StudentDashboardResource\Pages\ListStudentDashboards::route('/'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function studentNames(): array
    {
        $organizationId = data_get(auth()->user(), 'organization_id');

        if (!is_string($organizationId) || $organizationId === '') {
            return [];
        }

        return app(StudentDirectoryQueries::class)->namesForProfiles(
            $organizationId,
            StudentDashboard::query()
                ->forOrganization($organizationId)
                ->pluck('student_profile_id')
                ->all(),
        );
    }
}
