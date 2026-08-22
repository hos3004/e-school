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
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\AcademicReports\Domain\Enums\MonthlyReportStatus;
use Modules\AcademicReports\Domain\Models\MonthlyReport;

/**
 * مورد إدارة التقارير الشهرية في لوحة الإدارة.
 */
final class MonthlyReportResource extends Resource
{
    protected static ?string $model = MonthlyReport::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static \UnitEnum|string|null $navigationGroup = 'التعلّم';

    protected static ?int $navigationSort = 52;

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
                        TextInput::make('student_profile_id')
                            ->label(__('academicreports::fields.student_profile'))
                            ->required()
                            ->maxLength(26),
                        TextInput::make('enrollment_id')
                            ->label(__('academicreports::fields.enrollment'))
                            ->required()
                            ->maxLength(26),
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
                    Select::make('status')
                        ->label(__('academicreports::fields.status'))
                        ->options(collect(MonthlyReportStatus::cases())
                            ->mapWithKeys(fn (MonthlyReportStatus $s): array => [$s->value => $s->label()])
                            ->all())
                        ->required(),
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
                    ->searchable()
                    ->copyable(),
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
        return parent::getEloquentQuery()->when(
            auth()->user()?->organization_id !== null,
            fn (Builder $query): Builder => $query->forOrganization((string) auth()->user()?->organization_id),
        );
    }
}
