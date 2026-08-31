<?php

declare(strict_types=1);

namespace Modules\Students\Presentation\Filament\Resources;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Students\Domain\Enums\RegistrationQuestionType;
use Modules\Students\Domain\Models\RegistrationForm;
use Modules\Students\Domain\Models\RegistrationQuestion;
use Modules\Students\Presentation\Filament\Resources\RegistrationQuestionResource\Pages;

/**
 * إدارة أسئلة تقييم طلبات التسجيل؛ تظهر الأسئلة المفعّلة في نموذج التسجيل العام.
 */
final class RegistrationQuestionResource extends Resource
{
    protected static ?string $model = RegistrationQuestion::class;

    protected static ?string $slug = 'registration-questions';

    protected static ?int $navigationSort = 20;

    public static function getNavigationGroup(): string
    {
        return __('students::filament.navigation_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('students::registration_questions.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('students::registration_questions.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('students::registration_questions.plural_model_label');
    }

    /**
     * @return Builder<RegistrationQuestion>
     */
    public static function getEloquentQuery(): Builder
    {
        $organizationId = (string) data_get(auth()->user(), 'organization_id');
        /** @var Builder<RegistrationQuestion> $query */
        $query = parent::getEloquentQuery();

        return $organizationId === ''
            ? $query->whereRaw('1 = 0')
            : $query->forOrganization($organizationId);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('registration_form_id')
                ->label(__('students::registration_questions.fields.registration_form'))
                ->options(fn (): array => RegistrationForm::query()
                    ->forOrganization((string) data_get(auth()->user(), 'organization_id'))
                    ->get()
                    ->mapWithKeys(static fn (RegistrationForm $form): array => [
                        (string) $form->getKey() => $form->localizedTitle(),
                    ])->all())
                ->required()
                ->searchable(),
            TextInput::make('question.ar')
                ->label(__('students::registration_questions.fields.question_ar'))
                ->required()
                ->maxLength(1000),
            TextInput::make('question.en')
                ->label(__('students::registration_questions.fields.question_en'))
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
                ->columns(1)
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
            /*
             * الفلترة بإجابة سؤال تكشف بيانات شخصية مجمّعة، فلا تُفتح تلقائيًا.
             * والنص الحر مستبعد دائمًا — قيد في قاعدة البيانات يفرض ذلك حتى لو
             * أُرسلت القيمة من خارج هذه الشاشة.
             */
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
            TextInput::make('sort_order')
                ->label(__('students::registration_questions.fields.sort_order'))
                ->numeric()
                ->minValue(0)
                ->maxValue(9999)
                ->default(0),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('registrationForm.title')
                    ->label(__('students::registration_questions.fields.registration_form'))
                    ->formatStateUsing(fn (mixed $state, RegistrationQuestion $record): string => $record->registrationForm?->localizedTitle() ?? ''),
                TextColumn::make('question')
                    ->label(__('students::registration_questions.fields.question_ar'))
                    ->formatStateUsing(fn ($state): string => self::localizedQuestionLabel($state))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where('question->ar', 'ilike', "%{$search}%")->orWhere('question->en', 'ilike', "%{$search}%")),
                TextColumn::make('type')
                    ->label(__('students::registration_questions.fields.type'))
                    ->badge()
                    ->formatStateUsing(fn (RegistrationQuestionType $state): string => $state->label()),
                IconColumn::make('is_required')
                    ->label(__('students::registration_questions.fields.is_required'))
                    ->boolean(),
                IconColumn::make('is_active')
                    ->label(__('students::registration_questions.fields.is_active'))
                    ->boolean(),
                IconColumn::make('is_filterable')
                    ->label(__('students::registration_questions.fields.is_filterable'))
                    ->boolean(),
                TextColumn::make('sort_order')
                    ->label(__('students::registration_questions.fields.sort_order'))
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('students::registration_questions.filters.is_active')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->successNotification(fn (): Notification => Notification::make()->title(__('students::registration_questions.messages.deleted'))->success()),
            ])
            ->defaultSort('sort_order');
    }

    /** عمود jsonb قد يصل كسلسلة خام أو مصفوفة أو null. */
    private static function localizedQuestionLabel(mixed $state): string
    {
        $labels = is_array($state)
            ? $state
            : (is_string($state) ? (json_decode($state, true) ?: ['ar' => $state]) : []);

        return (string) ($labels['ar'] ?? $labels['en'] ?? '');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRegistrationQuestions::route('/'),
            'create' => Pages\CreateRegistrationQuestion::route('/create'),
            'edit' => Pages\EditRegistrationQuestion::route('/{record}/edit'),
        ];
    }
}
