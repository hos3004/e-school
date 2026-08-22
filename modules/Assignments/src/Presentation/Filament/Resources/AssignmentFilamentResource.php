<?php

declare(strict_types=1);

namespace Modules\Assignments\Presentation\Filament\Resources;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Modules\Assignments\Domain\Models\Assignment;
use Modules\Assignments\Presentation\Filament\Resources\AssignmentFilamentResource\Pages;

/**
 * مورد الأنشطة في لوحة الإدارة — إنشاء وتعديل وحذف ناعم.
 * كل النصوص عبر ملفات الترجمة.
 */
final class AssignmentFilamentResource extends Resource
{
    protected static ?string $model = Assignment::class;

    protected static ?string $slug = 'assignments';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    public static function getNavigationLabel(): string
    {
        return __('assignments::filament.assignments.navigation_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('assignments::filament.navigation_group');
    }

    public static function getModelLabel(): string
    {
        return __('assignments::filament.assignments.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('assignments::filament.assignments.plural_model_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('course_id')
                ->label(__('assignments::attributes.course'))
                ->required()
                ->maxLength(26),

            Select::make('group_id')
                ->label(__('assignments::attributes.group'))
                ->nullable()
                ->maxLength(26),

            Select::make('staff_profile_id')
                ->label(__('assignments::attributes.teacher'))
                ->required()
                ->maxLength(26),

            TextInput::make('title.ar')
                ->label(__('assignments::attributes.title_ar'))
                ->required()
                ->maxLength(255),

            TextInput::make('title.en')
                ->label(__('assignments::attributes.title_en'))
                ->nullable()
                ->maxLength(255),

            Textarea::make('instructions')
                ->label(__('assignments::attributes.instructions'))
                ->columnSpanFull(),

            DateTimePicker::make('assigned_at')
                ->label(__('assignments::attributes.assigned_at'))
                ->required(),

            DateTimePicker::make('due_at')
                ->label(__('assignments::attributes.due_at'))
                ->required(),

            TextInput::make('max_score')
                ->label(__('assignments::attributes.max_score'))
                ->numeric()
                ->minValue(1)
                ->maxValue(1000)
                ->required(),

            Toggle::make('allows_late')
                ->label(__('assignments::attributes.allows_late'))
                ->default(true),

            TextInput::make('late_penalty_percent')
                ->label(__('assignments::attributes.late_penalty_percent'))
                ->numeric()
                ->minValue(0)
                ->maxValue(100)
                ->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('assignments::attributes.title'))
                    ->formatStateUsing(fn ($state): string => is_array($state)
                        ? (string) ($state[app()->getLocale()] ?? reset($state))
                        : (string) $state)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('course_id')
                    ->label(__('assignments::attributes.course'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('due_at')
                    ->label(__('assignments::attributes.due_at'))
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('max_score')
                    ->label(__('assignments::attributes.max_score'))
                    ->sortable()
                    ->alignEnd(),

                IconColumn::make('allows_late')
                    ->label(__('assignments::attributes.allows_late'))
                    ->boolean()
                    ->toggleable(),

                TextColumn::make('submissions_count')
                    ->label(__('assignments::filament.submissions_count'))
                    ->counts('submissions')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('allows_late')
                    ->label(__('assignments::attributes.allows_late')),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssignments::route('/'),
            'create' => Pages\CreateAssignment::route('/create'),
            'edit' => Pages\EditAssignment::route('/{record}/edit'),
            'view' => Pages\ViewAssignment::route('/{record}'),
        ];
    }
}
