<?php

declare(strict_types=1);

namespace Modules\Assessments\Presentation\Filament\Resources;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
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
use Modules\Assessments\Domain\Models\AssessmentAttempt;
use Shared\Concerns\ScopesFilamentToOrganizationVia;

/**
 * مورد محاولات الاختبار في لوحة الإدارة — للعرض والتصحيح اليدوي.
 */
final class AssessmentAttemptResource extends Resource
{
    use ScopesFilamentToOrganizationVia;

    /**
     * الجدول لا يحمل `organization_id`؛ ينتمي عبر أبيه.
     */
    protected static function organizationRelation(): string
    {
        return 'assessment';
    }

    protected static ?string $model = AssessmentAttempt::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?int $navigationSort = 51;

    // الخانة معلنة هنا لا في الصنف الأب: `$navigationParentItem` في Filament
    // مشتركة بين كل الموارد، فبلا إعادة إعلانها يدهس آخرُ إسناد ما قبله.
    // القيمة نفسها تُضبط مركزيًا في App\Filament\AdminNavigation.
    protected static ?string $navigationParentItem = null;

    public static function getNavigationGroup(): string
    {
        return __('assessments::navigation.group');
    }

    public static function getModelLabel(): string
    {
        return __('assessments::navigation.attempt.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('assessments::navigation.attempt.plural');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('features.assessments', false)
            && parent::shouldRegisterNavigation();
    }

    public static function canAccess(): bool
    {
        return (bool) config('features.assessments', false)
            && (auth()->user()?->can('viewAny', AssessmentAttempt::class) ?? false);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('assessments::fields.attempt_info'))
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('assessment_id')
                            ->label(__('assessments::fields.assessment'))
                            ->disabled()
                            ->maxLength(26),
                        TextInput::make('student_profile_id')
                            ->label(__('assessments::fields.student_profile'))
                            ->disabled()
                            ->maxLength(26),
                        TextInput::make('attempt_number')
                            ->label(__('assessments::fields.attempt_number'))
                            ->numeric()
                            ->disabled(),
                        DateTimePicker::make('started_at')
                            ->label(__('assessments::fields.started_at'))
                            ->disabled(),
                        DateTimePicker::make('submitted_at')
                            ->label(__('assessments::fields.submitted_at'))
                            ->disabled(),
                    ]),
                ]),
            Section::make(__('assessments::fields.grading'))
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('score')
                            ->label(__('assessments::fields.score'))
                            ->numeric()
                            ->minValue(0),
                        Toggle::make('passed')
                            ->label(__('assessments::fields.passed')),
                        Select::make('graded_by')
                            ->label(__('assessments::fields.graded_by'))
                            ->disabled(),
                        DateTimePicker::make('graded_at')
                            ->label(__('assessments::fields.graded_at'))
                            ->disabled(),
                    ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label(__('assessments::fields.id'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('assessment_id')
                    ->label(__('assessments::fields.assessment'))
                    ->copyable()
                    ->limit(12),
                TextColumn::make('student_profile_id')
                    ->label(__('assessments::fields.student_profile'))
                    ->copyable()
                    ->limit(12),
                TextColumn::make('attempt_number')
                    ->label(__('assessments::fields.attempt_number'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('started_at')
                    ->label(__('assessments::fields.started_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('submitted_at')
                    ->label(__('assessments::fields.submitted_at'))
                    ->dateTime()
                    ->sortable()
                    ->placeholder(__('assessments::fields.not_submitted')),
                IconColumn::make('passed')
                    ->label(__('assessments::fields.passed'))
                    ->boolean()
                    ->placeholder('—'),
                TextColumn::make('score')
                    ->label(__('assessments::fields.score'))
                    ->numeric()
                    ->sortable()
                    ->placeholder('—'),
            ])
            ->filters([
                TernaryFilter::make('passed')
                    ->label(__('assessments::fields.passed')),
                TernaryFilter::make('submitted_at')
                    ->label(__('assessments::fields.submitted')),
            ])
            ->defaultSort('started_at', direction: 'desc');
    }

    /**
     * @return array<string, mixed>
     */
    public static function getPages(): array
    {
        return [
            'index' => AssessmentAttemptResource\Pages\ListAssessmentAttempts::route('/'),
        ];
    }
}
