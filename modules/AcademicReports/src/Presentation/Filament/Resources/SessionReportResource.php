<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Presentation\Filament\Resources;

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
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Modules\AcademicReports\Domain\Models\SessionReport;

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
                    ->searchable()
                    ->copyable()
                    ->limit(12),
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
            ])
            ->defaultSort('submitted_at', direction: 'desc');
    }
}
