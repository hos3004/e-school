<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Presentation\Filament\Resources;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\AcademicReports\Domain\Models\SessionReport;
use Modules\Staff\Domain\Contracts\StaffQueries;

/**
 * مورد تقارير الحصص في لوحة الإدارة.
 *
 * الملاحظة الإشرافية السرية تظهر هنا فقط — لا تُرجع عبر الـ API.
 */
final class SessionReportResource extends Resource
{
    protected static ?string $model = SessionReport::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?int $navigationSort = 51;

    /**
     * `session_reports` لا يحمل `organization_id`، ولا يجوز لهذا الموديول أن
     * يعلن علاقة إلى نموذج موديول آخر. العزل يتم بمجموعة ملفات موظفي
     * المؤسسة المقروءة من **عقد Staff المعلن** — بلا معرفة بجداوله.
     *
     * @return Builder<SessionReport>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<SessionReport> $query */
        $query = parent::getEloquentQuery();

        $profileIds = self::organizationStaffProfileIds();

        return $profileIds === []
            ? $query->whereRaw('1 = 0')
            : $query->whereIn('staff_profile_id', $profileIds);
    }

    /**
     * @return list<string>
     */
    private static function organizationStaffProfileIds(): array
    {
        $organizationId = data_get(auth()->user(), 'organization_id');

        if (!is_string($organizationId) || $organizationId === '') {
            return [];
        }

        return app(StaffQueries::class)->profileIdsForOrganization($organizationId);
    }

    /**
     * @return array<string, string>
     */
    private static function teacherNames(): array
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $organizationId = data_get(auth()->user(), 'organization_id');

        if (!is_string($organizationId) || $organizationId === '') {
            return $cache = [];
        }

        return $cache = app(StaffQueries::class)->namesForProfiles(
            $organizationId,
            self::organizationStaffProfileIds(),
        );
    }

    public static function getNavigationGroup(): ?string
    {
        return __('academicreports::navigation.group');
    }

    public static function getModelLabel(): string
    {
        return __('academicreports::navigation.session_report.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('academicreports::navigation.session_report.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('academicreports::fields.references'))
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('session_id')
                            ->label(__('academicreports::fields.session'))
                            ->required()
                            ->maxLength(26),
                        TextInput::make('staff_profile_id')
                            ->label(__('academicreports::fields.staff_profile'))
                            ->required()
                            ->maxLength(26),
                        DateTimePicker::make('submitted_at')
                            ->label(__('academicreports::fields.submitted_at')),
                        Toggle::make('is_late')
                            ->label(__('academicreports::fields.is_late')),
                    ]),
                ]),
            Section::make(__('academicreports::fields.content'))
                ->schema([
                    Textarea::make('topics_covered')
                        ->label(__('academicreports::fields.topics_covered'))
                        ->columnSpanFull(),
                    Textarea::make('homework_assigned')
                        ->label(__('academicreports::fields.homework_assigned'))
                        ->columnSpanFull(),
                    Textarea::make('general_notes')
                        ->label(__('academicreports::fields.general_notes'))
                        ->columnSpanFull(),
                    Textarea::make('supervisor_private_note')
                        ->label(__('academicreports::fields.supervisor_private_note'))
                        ->columnSpanFull(),
                    Textarea::make('next_session_plan')
                        ->label(__('academicreports::fields.next_session_plan'))
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
                TextColumn::make('session_id')
                    ->label(__('academicreports::fields.session'))
                    ->searchable()
                    ->copyable()
                    ->limit(12),
                TextColumn::make('staff_profile_id')
                    ->label(__('academicreports::fields.staff_profile'))
                    // كان ULID خامًا مقصوصًا إلى 12 حرفًا — لا يدل على معلم.
                    ->formatStateUsing(static fn ($state): string => self::teacherNames()[(string) $state]
                        ?? (string) $state)
                    ->searchable(),
                IconColumn::make('is_late')
                    ->label(__('academicreports::fields.is_late'))
                    ->boolean(),
                TextColumn::make('submitted_at')
                    ->label(__('academicreports::fields.submitted_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_late')
                    ->label(__('academicreports::fields.is_late')),

                SelectFilter::make('staff_profile_id')
                    ->label(__('academicreports::fields.staff_profile'))
                    ->options(fn (): array => self::teacherNames())
                    ->searchable(),

                Filter::make('submitted_between')
                    ->label(__('academicreports::filters.submitted_between'))
                    ->schema([
                        DatePicker::make('from')->label(__('academicreports::filters.from')),
                        DatePicker::make('until')->label(__('academicreports::filters.until')),
                    ])
                    ->query(static function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'] ?? null,
                                static fn (Builder $q, string $from): Builder => $q
                                    ->whereDate('submitted_at', '>=', $from),
                            )
                            ->when(
                                $data['until'] ?? null,
                                static fn (Builder $q, string $until): Builder => $q
                                    ->whereDate('submitted_at', '<=', $until),
                            );
                    }),

                Filter::make('missing')
                    ->label(__('academicreports::filters.not_submitted'))
                    ->query(static fn (Builder $query): Builder => $query->whereNull('submitted_at')),
            ])
            ->defaultSort('submitted_at', direction: 'desc');
    }
}
