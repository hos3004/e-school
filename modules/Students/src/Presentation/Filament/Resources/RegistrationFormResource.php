<?php

declare(strict_types=1);

namespace Modules\Students\Presentation\Filament\Resources;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Students\Domain\Enums\RegistrationQuestionType;
use Modules\Students\Domain\Models\RegistrationForm;
use Modules\Students\Presentation\Filament\Resources\RegistrationFormResource\Pages;

/** منشئ نماذج التسجيل العامة وأسئلتها المرتبة. */
final class RegistrationFormResource extends Resource
{
    protected static ?string $model = RegistrationForm::class;

    protected static ?string $slug = 'registration-forms';

    protected static ?int $navigationSort = 20;

    public static function getNavigationGroup(): string
    {
        return __('students::filament.navigation_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('students::registration_forms.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('students::registration_forms.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('students::registration_forms.plural_model_label');
    }

    /** @return Builder<RegistrationForm> */
    public static function getEloquentQuery(): Builder
    {
        $organizationId = self::organizationId();
        /** @var Builder<RegistrationForm> $query */
        $query = parent::getEloquentQuery();

        return $organizationId === ''
            ? $query->whereRaw('1 = 0')
            : $query->forOrganization($organizationId)->withCount('questions');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('students::registration_forms.sections.identity'))
                ->schema([
                    TextInput::make('title.ar')
                        ->label(__('students::registration_forms.fields.title_ar'))
                        ->required()
                        ->maxLength(255),
                    TextInput::make('title.en')
                        ->label(__('students::registration_forms.fields.title_en'))
                        ->maxLength(255),
                    TextInput::make('title.fr')
                        ->label(__('students::registration_forms.fields.title_fr'))
                        ->maxLength(255),
                    TextInput::make('slug')
                        ->label(__('students::registration_forms.fields.slug'))
                        ->helperText(__('students::registration_forms.fields.slug_help'))
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                        ->maxLength(120),
                    Textarea::make('description.ar')
                        ->label(__('students::registration_forms.fields.description_ar'))
                        ->rows(3)
                        ->maxLength(3000),
                    Textarea::make('description.en')
                        ->label(__('students::registration_forms.fields.description_en'))
                        ->rows(3)
                        ->maxLength(3000),
                    Textarea::make('description.fr')
                        ->label(__('students::registration_forms.fields.description_fr'))
                        ->rows(3)
                        ->maxLength(3000),
                    Toggle::make('is_active')
                        ->label(__('students::registration_forms.fields.is_active'))
                        ->helperText(__('students::registration_forms.fields.is_active_help'))
                        ->default(false),
                ])
                ->columns(2),

            Section::make(__('students::registration_forms.sections.questions'))
                ->description(__('students::registration_forms.sections.questions_help'))
                ->schema([
                    Repeater::make('questions')
                        ->hiddenLabel()
                        ->relationship()
                        ->orderColumn('sort_order')
                        ->schema(self::questionSchema())
                        ->columns(2)
                        ->itemLabel(function (array $state): ?string {
                            $label = data_get($state, 'question.ar');

                            return is_string($label) && $label !== '' ? $label : null;
                        })
                        ->addActionLabel(__('students::registration_forms.actions.add_question'))
                        ->collapsible()
                        ->cloneable()
                        ->itemNumbers()
                        ->defaultItems(0)
                        ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => [
                            ...$data,
                            'organization_id' => self::organizationId(),
                        ])
                        ->mutateRelationshipDataBeforeSaveUsing(fn (array $data): array => [
                            ...$data,
                            'organization_id' => self::organizationId(),
                        ]),
                ]),

            Textarea::make('change_reason')
                ->label(__('students::registration_forms.fields.change_reason'))
                ->helperText(__('students::registration_forms.fields.change_reason_help'))
                ->required()
                ->maxLength(2000)
                ->columnSpanFull(),
        ]);
    }

    /** @return list<TextInput|Select|Repeater|Toggle> */
    private static function questionSchema(): array
    {
        return [
            TextInput::make('question.ar')
                ->label(__('students::registration_questions.fields.question_ar'))
                ->required()
                ->live(onBlur: true)
                ->maxLength(1000),
            TextInput::make('question.en')
                ->label(__('students::registration_questions.fields.question_en'))
                ->maxLength(1000),
            TextInput::make('question.fr')
                ->label(__('students::registration_questions.fields.question_fr'))
                ->maxLength(1000),
            Select::make('type')
                ->label(__('students::registration_questions.fields.type'))
                ->options(RegistrationQuestionType::options())
                ->default(RegistrationQuestionType::Text->value)
                ->live()
                ->required(),
            Repeater::make('options')
                ->label(__('students::registration_questions.fields.options'))
                ->simple(TextInput::make('option')->required()->maxLength(500))
                ->minItems(2)
                ->maxItems(20)
                ->columnSpanFull()
                ->visible(fn (callable $get): bool => in_array($get('type'), [
                    RegistrationQuestionType::Select->value,
                    RegistrationQuestionType::Radio->value,
                    RegistrationQuestionType::Checkbox->value,
                ], true)),
            Toggle::make('is_required')
                ->label(__('students::registration_questions.fields.is_required'))
                ->default(false),
            Toggle::make('is_active')
                ->label(__('students::registration_questions.fields.is_active'))
                ->default(true),
            Toggle::make('is_filterable')
                ->label(__('students::registration_questions.fields.is_filterable'))
                ->helperText(__('students::registration_questions.fields.is_filterable_help'))
                ->default(false)
                ->disabled(fn (callable $get): bool => !in_array($get('type'), [
                    RegistrationQuestionType::Select->value,
                    RegistrationQuestionType::Radio->value,
                    RegistrationQuestionType::Number->value,
                ], true))
                ->dehydrateStateUsing(fn (mixed $state, callable $get): bool => (bool) $state
                    && in_array($get('type'), [
                        RegistrationQuestionType::Select->value,
                        RegistrationQuestionType::Radio->value,
                        RegistrationQuestionType::Number->value,
                    ], true)),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('students::registration_forms.fields.title'))
                    ->formatStateUsing(fn (mixed $state, RegistrationForm $record): string => $record->localizedTitle()),
                TextColumn::make('slug')
                    ->label(__('students::registration_forms.fields.slug'))
                    ->copyable(),
                TextColumn::make('questions_count')
                    ->label(__('students::registration_forms.fields.questions_count'))
                    ->badge(),
                IconColumn::make('is_active')
                    ->label(__('students::registration_forms.fields.is_active'))
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label(__('students::registration_forms.fields.updated_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label(__('students::registration_forms.filters.is_active')),
            ])
            ->recordActions([
                Action::make('open_public_form')
                    ->label(__('students::registration_forms.actions.open_public_form'))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (RegistrationForm $record): string => route('register.student.form', ['formSlug' => $record->slug]))
                    ->openUrlInNewTab()
                    ->visible(fn (RegistrationForm $record): bool => $record->is_active),
                EditAction::make(),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRegistrationForms::route('/'),
            'create' => Pages\CreateRegistrationForm::route('/create'),
            'edit' => Pages\EditRegistrationForm::route('/{record}/edit'),
        ];
    }

    public static function organizationId(): string
    {
        return (string) data_get(auth()->user(), 'organization_id');
    }
}
