<?php

declare(strict_types=1);

namespace Modules\Scheduling\Presentation\Filament\Resources;

use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Scheduling\Domain\Enums\PostponementStatus;
use Modules\Scheduling\Domain\Models\PostponementRequest;

/**
 * طلبات التأجيل — الطريق الوحيد المتاح للطالب لتغيير موعد حصة،
 * لأن زر الإلغاء مطفأ بقرار العميل.
 *
 * العمود الأهم هنا هو "على من الدور": الطلب المعلّق ينتظر ردًا من المعلم
 * خلال مهلة محددة، وبعدها يُصعَّد للإدارة.
 */
final class PostponementRequestResource extends Resource
{
    protected static ?string $model = PostponementRequest::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static ?int $navigationSort = 40;

    public static function getNavigationGroup(): ?string
    {
        return __('scheduling::filament.group');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('session.postpone.approve') ?? false;
    }

    public static function canCreate(): bool
    {
        // الطلب يُنشأ من واجهة الطالب عبر RequestPostponement، لا من اللوحة.
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getModelLabel(): string
    {
        return __('scheduling::filament.postponement.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('scheduling::filament.postponement.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('session_id')
                    ->label(__('scheduling::filament.postponement.session'))
                    ->searchable(),

                TextColumn::make('requested_for_student_id')
                    ->label(__('scheduling::filament.postponement.student'))
                    ->searchable(),

                TextColumn::make('status')
                    ->label(__('scheduling::filament.postponement.status'))
                    ->badge()
                    ->formatStateUsing(
                        fn (PostponementStatus $state): string => __('scheduling::postponement.'.$state->value),
                    )
                    ->color(fn (PostponementStatus $state): string => match ($state) {
                        PostponementStatus::Requested => 'warning',
                        PostponementStatus::AlternativeProposed => 'info',
                        PostponementStatus::Scheduled => 'success',
                        PostponementStatus::Fulfilled => 'success',
                        PostponementStatus::Expired => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('awaiting')
                    ->label(__('scheduling::filament.postponement.awaiting'))
                    ->state(fn (PostponementRequest $record): string => match ($record->status->awaitingActionFrom()) {
                        'teacher' => __('scheduling::filament.awaiting.teacher'),
                        'student' => __('scheduling::filament.awaiting.student'),
                        'admin' => __('scheduling::filament.awaiting.admin'),
                        default => __('scheduling::filament.awaiting.none'),
                    })
                    ->badge(),

                TextColumn::make('proposed_start')
                    ->label(__('scheduling::filament.postponement.proposed_start'))
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('agreed_start')
                    ->label(__('scheduling::filament.postponement.agreed_start'))
                    ->dateTime()
                    ->toggleable(),

                TextColumn::make('expires_at')
                    ->label(__('scheduling::filament.postponement.expires_in'))
                    // المهلة المتبقية للمعلم — بعدها يُصعَّد الطلب للإدارة.
                    ->state(function (PostponementRequest $record): string {
                        if (!$record->status->isPending() || $record->expires_at === null) {
                            return __('scheduling::filament.postponement.not_available');
                        }

                        $minutes = (int) CarbonImmutable::now('UTC')
                            ->diffInMinutes($record->expires_at, false);

                        return $minutes <= 0
                            ? __('scheduling::filament.postponement.expired')
                            : __('scheduling::filament.postponement.hours_left', [
                                'hours' => (int) floor($minutes / 60),
                            ]);
                    }),

                TextColumn::make('makeup_session_id')
                    ->label(__('scheduling::filament.postponement.makeup'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('scheduling::filament.postponement.status'))
                    ->options(collect(PostponementStatus::cases())
                        ->mapWithKeys(fn (PostponementStatus $c): array => [
                            $c->value => __('scheduling::postponement.'.$c->value),
                        ])
                        ->all()),
            ])
            ->recordActions([ViewAction::make()])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * @return array<string, mixed>
     */
    public static function getPages(): array
    {
        return [
            'index' => PostponementRequestResource\Pages\ListPostponementRequests::route('/'),
            'view' => PostponementRequestResource\Pages\ViewPostponementRequest::route('/{record}'),
        ];
    }
}
