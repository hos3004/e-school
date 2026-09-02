<?php

declare(strict_types=1);

namespace Modules\Discipline\Presentation\Filament\Resources;

use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Discipline\Application\Actions\CancelReactivationAction;
use Modules\Discipline\Application\Actions\DecideReactivationAction;
use Modules\Discipline\Domain\Enums\ReactivationStatus;
use Modules\Discipline\Domain\Models\ReactivationRequest;
use Modules\Discipline\Presentation\Filament\Resources\ReactivationRequestFilamentResource\Pages;
use Shared\Concerns\ScopesFilamentToOrganization;
use Shared\Filament\RecordOriginGuide;

/**
 * مورد طلبات إعادة التفعيل — مراجعة إدارية بلا نصوص مكتوبة مباشرة.
 */
final class ReactivationRequestFilamentResource extends Resource
{
    use ScopesFilamentToOrganization;

    protected static ?string $model = ReactivationRequest::class;

    protected static ?string $slug = 'discipline-reactivations';

    protected static ?int $navigationSort = 60;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-lock-open';

    public static function getNavigationGroup(): ?string
    {
        return __('discipline::filament.navigation_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('discipline::filament.reactivations.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('discipline::filament.reactivations.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('discipline::filament.reactivations.plural_model_label');
    }

    public static function canCreate(): bool
    {
        return false; // التقديم يمر حصرًا عبر RequestReactivationAction.
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('enrollment_id')
                ->label(__('discipline::attributes.enrollment_id'))
                ->disabled()
                ->dehydrated(false),

            TextInput::make('attempt_number')
                ->label(__('discipline::filament.attempt_number'))
                ->numeric()
                ->disabled()
                ->dehydrated(false),

            Textarea::make('student_statement')
                ->label(__('discipline::attributes.student_statement'))
                ->disabled()
                ->dehydrated(false)
                ->columnSpanFull(),

            Textarea::make('decision_note')
                ->label(__('discipline::attributes.decision_note'))
                ->disabled()
                ->dehydrated(false)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return RecordOriginGuide::for(
            $table,
            'discipline::origin.reactivation',
            'heroicon-o-lock-open',
        )
            ->columns([
                TextColumn::make('enrollment_id')
                    ->label(__('discipline::attributes.enrollment_id'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->label(__('discipline::attributes.status'))
                    ->badge()
                    ->formatStateUsing(fn (?ReactivationStatus $state): ?string => $state?->label())
                    ->color(fn (?ReactivationStatus $state): string => $state?->color() ?? 'gray')
                    ->sortable(),

                TextColumn::make('attempt_number')
                    ->label(__('discipline::filament.attempt_number'))
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('discipline::filament.submitted_at'))
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('reviewed_at')
                    ->label(__('discipline::filament.reviewed_at'))
                    ->dateTime()
                    ->placeholder(__('discipline::filament.not_reviewed'))
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('discipline::attributes.status'))
                    ->options(collect(ReactivationStatus::cases())
                        ->mapWithKeys(fn (ReactivationStatus $s): array => [$s->value => $s->label()])
                        ->all()),
            ])
            ->recordActions([
                ViewAction::make(),
                ...self::decisionActions(),
            ])
            ->bulkActions([]);
    }

    /**
     * أزرار حسم الطلب — تُركَّب في صف الجدول وفي رأس صفحة الطلب من مصدر واحد.
     *
     * كانت `DecideReactivationAction` مكتوبة ومختبَرة ولها سياسة `decide`، ولا
     * يصل إليها أحد من اللوحة: الطالب المجمَّد يقدّم طلبًا، ولوحة المعلومات تعرض
     * «طلبات فك تجميد معلّقة»، ولا زرَّ يغلق البند. والحسم قرار يومي فمكانه
     * الجدول لا صفحةً داخلية وحدها.
     *
     * @return list<Action>
     */
    public static function decisionActions(): array
    {
        return [
            self::decisionAction(ReactivationStatus::Approved, 'approve', 'approved', 'heroicon-m-check', 'success'),
            self::decisionAction(ReactivationStatus::Rejected, 'reject', 'rejected', 'heroicon-m-x-mark', 'danger'),
            self::cancelAction(),
        ];
    }

    private static function decisionAction(
        ReactivationStatus $decision,
        string $name,
        string $successKey,
        string $icon,
        string $color,
    ): Action {
        return Action::make($name)
            ->label(__('discipline::filament.reactivations.'.$name))
            ->icon($icon)
            ->color($color)
            ->authorize('decide')
            ->form([
                // السبب إلزامي بحكم البند 7: لا تغيير حسّاس بلا سبب مكتوب.
                Textarea::make('decision_note')
                    ->label(__('discipline::attributes.decision_note'))
                    ->required()
                    ->minLength(3)
                    ->maxLength(2000),

                /*
                 * القبول وحده يشترط محاولة اختبار الجدية حين تكون
                 * `discipline.reactivation.requires_assessment` مفعّلة، وإلا رمى
                 * الإجراءُ `reactivation_assessment_required`. يُطلب المعرّف نصًّا
                 * لا من قائمة، لأن Discipline لا يملك عقد قراءة معلنًا نحو
                 * Assessments، واستيراد نماذجه يكسر حدود الموديولات.
                 */
                TextInput::make('assessment_attempt_id')
                    ->label(__('discipline::attributes.assessment_attempt_id'))
                    ->helperText(__('discipline::filament.reactivations.assessment_hint'))
                    ->default(fn (ReactivationRequest $record): ?string => $record->assessment_attempt_id)
                    ->length(26)
                    ->visible($decision === ReactivationStatus::Approved)
                    ->required(
                        $decision === ReactivationStatus::Approved
                        && (bool) config('discipline.reactivation.requires_assessment', true),
                    ),
            ])
            ->action(function (ReactivationRequest $record, array $data) use ($decision, $successKey): void {
                app(DecideReactivationAction::class)->execute($record, [
                    'decision' => $decision,
                    'decision_note' => (string) $data['decision_note'],
                    'assessment_attempt_id' => $data['assessment_attempt_id'] ?? null,
                ]);

                Notification::make()
                    ->title(__('discipline::filament.reactivations.'.$successKey))
                    ->success()
                    ->send();
            });
    }

    private static function cancelAction(): Action
    {
        return Action::make('cancel_request')
            ->label(__('discipline::filament.reactivations.cancel'))
            ->icon('heroicon-m-arrow-uturn-left')
            ->color('gray')
            ->authorize('cancel')
            ->requiresConfirmation()
            ->action(function (ReactivationRequest $record): void {
                app(CancelReactivationAction::class)->execute($record);

                Notification::make()
                    ->title(__('discipline::filament.reactivations.cancelled'))
                    ->success()
                    ->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReactivationRequests::route('/'),
            'view' => Pages\ViewReactivationRequest::route('/{record}'),
        ];
    }
}
