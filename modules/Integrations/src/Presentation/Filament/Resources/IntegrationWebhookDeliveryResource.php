<?php

declare(strict_types=1);

namespace Modules\Integrations\Presentation\Filament\Resources;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Integrations\Application\Actions\RequeueDeadDeliveryAction;
use Modules\Integrations\Domain\Enums\DeliveryStatus;
use Modules\Integrations\Domain\Enums\WebhookDirection;
use Modules\Integrations\Domain\Models\IntegrationWebhookDelivery;
use Shared\Filament\RecordOriginGuide;

/**
 * مورد متابعة إيصالات Webhook في لوحة الإدارة — للقراءة والمتابعة.
 */
final class IntegrationWebhookDeliveryResource extends Resource
{
    protected static ?string $model = IntegrationWebhookDelivery::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?int $navigationSort = 104;

    // الخانة معلنة هنا لا في الصنف الأب: `$navigationParentItem` في Filament
    // مشتركة بين كل الموارد، فبلا إعادة إعلانها يدهس آخرُ إسناد ما قبله.
    // القيمة نفسها تُضبط مركزيًا في App\Filament\AdminNavigation.
    protected static ?string $navigationParentItem = null;

    public static function getNavigationGroup(): string
    {
        return __('integrations::navigation.group');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getModelLabel(): string
    {
        return __('integrations::navigation.delivery.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('integrations::navigation.delivery.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('integrations::fields.delivery'))
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('connection_id')
                            ->label(__('integrations::fields.connection'))
                            ->relationship('connection', 'id')
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('event_type')
                            ->label(__('integrations::fields.event_type'))
                            ->required()
                            ->maxLength(120),
                        Select::make('direction')
                            ->label(__('integrations::fields.direction'))
                            ->options(
                                collect(WebhookDirection::cases())
                                    ->mapWithKeys(fn (WebhookDirection $d): array => [$d->value => $d->label()])
                                    ->all(),
                            )
                            ->required(),
                        Select::make('status')
                            ->label(__('integrations::fields.status'))
                            ->options(
                                collect(DeliveryStatus::cases())
                                    ->mapWithKeys(fn (DeliveryStatus $s): array => [$s->value => $s->label()])
                                    ->all(),
                            )
                            ->required(),
                    ]),
                    Textarea::make('response_body')
                        ->label(__('integrations::fields.response_body'))
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return RecordOriginGuide::for(
            $table,
            'integrations::origin.delivery',
            'heroicon-o-arrows-right-left',
        )
            ->columns([
                TextColumn::make('event_type')
                    ->label(__('integrations::fields.event_type'))
                    ->searchable()
                    ->badge()
                    ->color('gray'),
                TextColumn::make('direction')
                    ->label(__('integrations::fields.direction'))
                    ->formatStateUsing(fn ($state): string => $state instanceof WebhookDirection
                        ? $state->label()
                        : (string) $state)
                    ->toggleable(),
                TextColumn::make('status')
                    ->label(__('integrations::fields.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof DeliveryStatus
                        ? $state->label()
                        : (string) $state)
                    ->color(fn ($state): string => $state instanceof DeliveryStatus
                        ? $state->color()
                        : 'gray')
                    ->sortable(),
                TextColumn::make('attempts')
                    ->label(__('integrations::fields.attempts'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('response_code')
                    ->label(__('integrations::fields.response_code'))
                    ->numeric()
                    ->toggleable(),
                TextColumn::make('next_retry_at')
                    ->label(__('integrations::fields.next_retry_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('delivered_at')
                    ->label(__('integrations::fields.delivered_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('integrations::fields.status'))
                    ->options(
                        collect(DeliveryStatus::cases())
                            ->mapWithKeys(fn (DeliveryStatus $s): array => [$s->value => $s->label()])
                            ->all(),
                    ),
                SelectFilter::make('direction')
                    ->label(__('integrations::fields.direction'))
                    ->options(
                        collect(WebhookDirection::cases())
                            ->mapWithKeys(fn (WebhookDirection $d): array => [$d->value => $d->label()])
                            ->all(),
                    ),
            ])
            ->recordActions([self::requeueAction()])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * إعادة إدراج إيصال ميت في الطابور.
     *
     * `RequeueDeadDeliveryAction` وسياسة `requeue` كانتا موجودتين بلا زر، فكان
     * الإيصال الفاشل نهائيًا يبقى ميتًا ما لم يتدخل أحد على مستوى القاعدة —
     * أي أن تكاملًا خارجيًا يسقط بلا طريق تعافٍ من اللوحة.
     */
    public static function requeueAction(): Action
    {
        return Action::make('requeue')
            ->label(__('integrations::fields.requeue'))
            ->icon('heroicon-m-arrow-path')
            ->color('warning')
            ->authorize('requeue')
            ->requiresConfirmation()
            // الإجراء نفسه يرفض غير الميت؛ إخفاء الزر يمنع محاولةً مآلها خطأ.
            ->visible(fn (IntegrationWebhookDelivery $record): bool => $record->status === DeliveryStatus::Dead)
            ->action(function (IntegrationWebhookDelivery $record): void {
                app(RequeueDeadDeliveryAction::class)->execute($record, (string) auth()->id());

                Notification::make()
                    ->title(__('integrations::fields.requeued'))
                    ->success()
                    ->send();
            });
    }

    /**
     * @return array<string, mixed>
     */
    public static function getPages(): array
    {
        return [
            'index' => IntegrationWebhookDeliveryResource\Pages\ListIntegrationWebhookDeliveries::route('/'),
        ];
    }
}
