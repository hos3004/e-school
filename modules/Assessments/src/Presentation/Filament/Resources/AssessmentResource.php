<?php

declare(strict_types=1);

namespace Modules\Assessments\Presentation\Filament\Resources;

use Filament\Forms\Components\DateTimePicker;
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
use Modules\Assessments\Domain\Enums\AssessmentType;
use Modules\Assessments\Domain\Models\Assessment;

/**
 * مورد إدارة الاختبارات في لوحة الإدارة.
 */
final class AssessmentResource extends Resource
{
    protected static ?string $model = Assessment::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?int $navigationSort = 51;

    public static function getNavigationGroup(): ?string
    {
        return __('assessments::navigation.group');
    }

    public static function getModelLabel(): string
    {
        return __('assessments::navigation.assessment.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('assessments::navigation.assessment.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('assessments::fields.basics'))
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('type')
                            ->label(__('assessments::fields.type'))
                            ->options(collect(AssessmentType::cases())
                                ->mapWithKeys(fn (AssessmentType $t): array => [$t->value => $t->label()])
                                ->all())
                            ->required(),
                        TextInput::make('course_id')
                            ->label(__('assessments::fields.course'))
                            ->maxLength(26),
                        TextInput::make('total_score')
                            ->label(__('assessments::fields.total_score'))
                            ->numeric()
                            ->minValue(1)
                            ->required(),
                        TextInput::make('passing_score')
                            ->label(__('assessments::fields.passing_score'))
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                        TextInput::make('duration_minutes')
                            ->label(__('assessments::fields.duration'))
                            ->numeric()
                            ->minValue(1),
                        TextInput::make('max_attempts')
                            ->label(__('assessments::fields.max_attempts'))
                            ->numeric()
                            ->minValue(1)
                            ->required(),
                    ]),
                ]),
            Section::make(__('assessments::fields.availability'))
                ->schema([
                    Grid::make(2)->schema([
                        DateTimePicker::make('available_from')
                            ->label(__('assessments::fields.available_from'))
                            ->required(),
                        DateTimePicker::make('available_to')
                            ->label(__('assessments::fields.available_to'))
                            ->required(),
                    ]),
                ]),
            Section::make(__('assessments::fields.content'))
                ->schema([
                    Textarea::make('title.ar')
                        ->label(__('assessments::fields.title_ar'))
                        ->required()
                        ->maxLength(255),
                    Textarea::make('title.en')
                        ->label(__('assessments::fields.title_en'))
                        ->maxLength(255),
                    Textarea::make('instructions.ar')
                        ->label(__('assessments::fields.instructions_ar'))
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label(__('assessments::fields.id'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('title')
                    ->label(__('assessments::fields.title'))
                    ->formatStateUsing(fn ($state): string => is_array($state)
                        ? ($state[app()->getLocale()] ?? reset($state))
                        : (string) $state)
                    ->limit(40)
                    ->searchable(),
                TextColumn::make('type')
                    ->label(__('assessments::fields.type'))
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof AssessmentType
                        ? $state->label()
                        : (string) $state)
                    ->color(fn ($state): string => $state instanceof AssessmentType
                        ? $state->color()
                        : 'gray'),
                TextColumn::make('total_score')
                    ->label(__('assessments::fields.total_score'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('passing_score')
                    ->label(__('assessments::fields.passing_score'))
                    ->numeric(),
                TextColumn::make('max_attempts')
                    ->label(__('assessments::fields.max_attempts'))
                    ->numeric(),
                TextColumn::make('available_from')
                    ->label(__('assessments::fields.available_from'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('available_to')
                    ->label(__('assessments::fields.available_to'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('assessments::fields.type'))
                    ->options(collect(AssessmentType::cases())
                        ->mapWithKeys(fn (AssessmentType $t): array => [$t->value => $t->label()])
                        ->all()),
            ])
            ->defaultSort('available_from');
    }
}
