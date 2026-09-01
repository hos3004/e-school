<?php

declare(strict_types=1);

namespace Modules\Notifications\Presentation\Filament\Resources;

use Filament\Actions\BulkAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Notifications\Application\Actions\TransitionPopupCampaignAction;
use Modules\Notifications\Application\Services\PopupPageRegistry;
use Modules\Notifications\Domain\Enums\PopupAudience;
use Modules\Notifications\Domain\Enums\PopupCampaignStatus;
use Modules\Notifications\Domain\Enums\PopupFrequency;
use Modules\Notifications\Domain\Enums\PopupPlacement;
use Modules\Notifications\Domain\Enums\PopupType;
use Modules\Notifications\Domain\Models\PopupCampaign;
use Modules\Notifications\Presentation\Filament\Resources\PopupCampaignResource\Pages;
use Shared\Support\BusinessRuleViolation;

/**
 * إدارة حملات النوافذ المنبثقة — المحتوى نص عادي مُهرَّب دائمًا،
 * بلا HTML أو CSS أو JavaScript من الأدمن.
 */
final class PopupCampaignResource extends Resource
{
    protected static ?string $model = PopupCampaign::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-window';

    protected static ?int $navigationSort = 72;

    public static function getModelLabel(): string
    {
        return __('notifications::navigation.popup.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('notifications::navigation.popup.plural');
    }

    public static function getNavigationGroup(): string
    {
        return __('notifications::navigation.group');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', PopupCampaign::class) ?? false;
    }

    /** عزل المؤسسة — لا رؤية لحملات مؤسسة أخرى إطلاقًا. */
    public static function getEloquentQuery(): Builder
    {
        $organizationId = data_get(auth()->user(), 'organization_id');
        $query = parent::getEloquentQuery();

        return is_string($organizationId) && $organizationId !== ''
            ? $query->where('organization_id', $organizationId)
            : $query->whereRaw('1 = 0');
    }

    public static function form(Schema $schema): Schema
    {
        $limits = (array) config('popups.content');

        return $schema->schema([
            Tabs::make('popup_tabs')->tabs([
                Tab::make(__('notifications::popups.tabs.content'))
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        TextInput::make('internal_name')
                            ->label(__('notifications::popups.fields.internal_name'))
                            ->required()
                            ->maxLength((int) ($limits['internal_name_max'] ?? 120)),
                        Select::make('type')
                            ->label(__('notifications::popups.fields.type'))
                            ->options(PopupType::options())
                            ->required()
                            ->default(PopupType::General->value),
                        Fieldset::make(__('notifications::popups.fields.arabic_content'))->schema([
                            TextInput::make('title.ar')
                                ->label(__('notifications::popups.fields.title_ar'))
                                ->required()
                                ->maxLength((int) ($limits['title_max'] ?? 120)),
                            Textarea::make('body.ar')
                                ->label(__('notifications::popups.fields.body_ar'))
                                ->required()
                                ->maxLength((int) ($limits['body_max'] ?? 2000))
                                ->columnSpanFull()
                                ->helperText(__('notifications::popups.fields.plain_text_help')),
                        ]),
                        Fieldset::make(__('notifications::popups.fields.optional_translations'))->schema([
                            TextInput::make('title.en')
                                ->label(__('notifications::popups.fields.title_en'))
                                ->maxLength((int) ($limits['title_max'] ?? 120)),
                            Textarea::make('body.en')
                                ->label(__('notifications::popups.fields.body_en'))
                                ->maxLength((int) ($limits['body_max'] ?? 2000)),
                            TextInput::make('title.fr')
                                ->label(__('notifications::popups.fields.title_fr'))
                                ->maxLength((int) ($limits['title_max'] ?? 120)),
                            Textarea::make('body.fr')
                                ->label(__('notifications::popups.fields.body_fr'))
                                ->maxLength((int) ($limits['body_max'] ?? 2000)),
                        ]),
                        Fieldset::make(__('notifications::popups.fields.cta_section'))->schema([
                            Select::make('action_type')
                                ->label(__('notifications::popups.fields.action_type'))
                                ->options([
                                    '' => __('notifications::popups.options.no_action'),
                                    'internal_page' => __('notifications::popups.options.internal_page'),
                                    'external_url' => __('notifications::popups.options.external_url'),
                                ])
                                ->live(),
                            Select::make('internal_action_target')
                                ->label(__('notifications::popups.fields.internal_page'))
                                ->options(PopupPageRegistry::options())
                                ->visible(fn (Get $get): bool => $get('action_type') === 'internal_page')
                                ->required(fn (Get $get): bool => $get('action_type') === 'internal_page'),
                            TextInput::make('external_action_target')
                                ->label(__('notifications::popups.fields.external_url'))
                                ->url()
                                ->rule('regex:#^https://[^\s]+$#i')
                                ->helperText(__('notifications::popups.fields.external_url_help'))
                                ->visible(fn (Get $get): bool => $get('action_type') === 'external_url')
                                ->required(fn (Get $get): bool => $get('action_type') === 'external_url'),
                            TextInput::make('action_label.ar')
                                ->label(__('notifications::popups.fields.action_label_ar'))
                                ->maxLength((int) ($limits['action_label_max'] ?? 60))
                                ->visible(fn (Get $get): bool => filled($get('action_type'))),
                        ])->columns(2),
                    ])->columns(2),

                Tab::make(__('notifications::popups.tabs.audience'))
                    ->icon('heroicon-o-user-group')
                    ->schema([
                        CheckboxList::make('audiences')
                            ->label(__('notifications::popups.fields.audiences'))
                            ->options(PopupAudience::options())
                            ->required()
                            ->columns(3)
                            ->hintIcon('heroicon-o-information-circle')
                            ->helperText(__('notifications::popups.fields.audiences_help')),
                    ]),

                Tab::make(__('notifications::popups.tabs.display'))
                    ->icon('heroicon-o-computer-desktop')
                    ->schema([
                        Radio::make('placement')
                            ->label(__('notifications::popups.fields.placement'))
                            ->options(PopupPlacement::options())
                            ->required()
                            ->live()
                            ->default(PopupPlacement::AfterLogin->value),
                        Select::make('page_key')
                            ->label(__('notifications::popups.fields.page_key'))
                            ->options(PopupPageRegistry::options())
                            ->visible(fn (Get $get): bool => $get('placement') === PopupPlacement::SpecificPage->value)
                            ->required(fn (Get $get): bool => $get('placement') === PopupPlacement::SpecificPage->value),
                        Select::make('frequency')
                            ->label(__('notifications::popups.fields.frequency'))
                            ->options(collect(PopupFrequency::cases())
                                ->reject(fn (PopupFrequency $frequency): bool => $frequency === PopupFrequency::EveryEligibleVisit)
                                ->mapWithKeys(static fn (PopupFrequency $frequency): array => [
                                    $frequency->value => $frequency->label().' — '.__('notifications::popups.frequency_help.'.$frequency->value),
                                ])->all())
                            ->required()
                            ->default(PopupFrequency::Once->value),
                        Toggle::make('is_dismissible')
                            ->label(__('notifications::popups.fields.is_dismissible'))
                            ->default(true)
                            ->live(),
                        Toggle::make('requires_acknowledgement')
                            ->label(__('notifications::popups.fields.requires_acknowledgement'))
                            ->live()
                            ->rules([
                                static fn (Get $get): \Closure => static function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                                    // لا حبس للمستخدم: يجب إغلاق أو إقرار على الأقل.
                                    if (!$value && !$get('is_dismissible')) {
                                        $fail(__('notifications::popups.errors.unsafe_exit'));
                                    }
                                },
                            ]),
                        TextInput::make('acknowledgement_label.ar')
                            ->label(__('notifications::popups.fields.acknowledgement_label'))
                            ->maxLength((int) ($limits['acknowledgement_label_max'] ?? 60))
                            ->placeholder(__('notifications::popups.frontend.acknowledge_default'))
                            ->visible(fn (Get $get): bool => (bool) $get('requires_acknowledgement')),
                        TextInput::make('priority')
                            ->label(__('notifications::popups.fields.priority'))
                            ->numeric()
                            ->minValue((int) config('popups.priority.min', 1))
                            ->maxValue((int) config('popups.priority.max', 10))
                            ->default((int) config('popups.priority.default', 5))
                            ->required(),
                    ])->columns(2),

                Tab::make(__('notifications::popups.tabs.scheduling'))
                    ->icon('heroicon-o-calendar-days')
                    ->schema([
                        DateTimePicker::make('starts_at')
                            ->label(__('notifications::popups.fields.starts_at'))
                            ->required()
                            ->default(now())
                            ->seconds(false),
                        DateTimePicker::make('ends_at')
                            ->label(__('notifications::popups.fields.ends_at'))
                            ->nullable()
                            ->after('starts_at')
                            ->seconds(false),
                    ])->columns(2),

                Tab::make(__('notifications::popups.tabs.review'))
                    ->icon('heroicon-o-clipboard-document-check')
                    ->schema([
                        Placeholder::make('review_audiences')
                            ->label(__('notifications::popups.review.audiences'))
                            ->content(function (Get $get): string {
                                $selected = array_values((array) ($get('audiences') ?? []));

                                return $selected === []
                                    ? __('notifications::popups.errors.audience_required')
                                    : implode('، ', array_map(
                                        static fn (string $value): string => PopupAudience::from($value)->label(),
                                        $selected,
                                    ));
                            }),
                        Placeholder::make('review_placement')
                            ->label(__('notifications::popups.review.placement'))
                            ->content(function (Get $get): string {
                                $placement = $get('placement');

                                if (!is_string($placement)) {
                                    return '—';
                                }

                                $label = PopupPlacement::tryFrom($placement)?->label() ?? '—';
                                $page = $placement === PopupPlacement::SpecificPage->value
                                    ? ' · '.(string) $get('page_key')
                                    : '';

                                return $label.$page;
                            }),
                        Placeholder::make('review_window')
                            ->label(__('notifications::popups.review.window'))
                            ->content(function (Get $get): string {
                                $start = $get('starts_at');
                                $end = $get('ends_at');

                                return trim(($start ?? '?').' ← '.($end ?? __('notifications::popups.review.no_end')), ' ←');
                            }),
                        Placeholder::make('review_exit')
                            ->label(__('notifications::popups.review.exit_mode'))
                            ->content(function (Get $get): string {
                                $parts = [];

                                if ((bool) $get('is_dismissible')) {
                                    $parts[] = __('notifications::popups.fields.is_dismissible');
                                }

                                if ((bool) $get('requires_acknowledgement')) {
                                    $parts[] = __('notifications::popups.fields.requires_acknowledgement');
                                }

                                return $parts === [] ? __('notifications::popups.errors.unsafe_exit') : implode(' + ', $parts);
                            }),
                    ])->columns(2),
            ])->columnSpanFull(),

            // سبب إداري إلزامي — يُستهلك في التدقيق ولا يُخزَّن مع الحملة.
            Textarea::make('reason')
                ->label(__('notifications::popups.fields.reason'))
                ->helperText(__('notifications::popups.fields.reason_help'))
                ->maxLength(2000)
                ->required()
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('internal_name')
                    ->label(__('notifications::popups.fields.internal_name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('title')
                    ->label(__('notifications::popups.fields.title_ar'))
                    ->state(static fn (PopupCampaign $record): string => (string) ($record->title['ar'] ?? '—'))
                    ->limit(40),
                TextColumn::make('type')
                    ->label(__('notifications::popups.fields.type'))
                    ->badge()
                    ->formatStateUsing(static fn (PopupType $state): string => $state->label())
                    ->color(static fn (PopupType $state): string => $state->color()),
                TextColumn::make('audiences')
                    ->label(__('notifications::popups.fields.audiences'))
                    ->badge()
                    ->state(static fn (PopupCampaign $record): array => array_map(
                        static fn (string $audience): string => (PopupAudience::tryFrom($audience)?->label() ?? $audience),
                        (array) $record->audiences,
                    ))
                    ->color('gray'),
                TextColumn::make('placement')
                    ->label(__('notifications::popups.fields.placement'))
                    ->formatStateUsing(static fn (PopupPlacement $state): string => $state->label()),
                TextColumn::make('status')
                    ->label(__('notifications::popups.fields.status'))
                    ->badge()
                    ->state(static fn (PopupCampaign $record): string => $record->status->effectiveLabel(
                        $record->starts_at,
                        $record->ends_at,
                        now('UTC')->toImmutable(),
                    ))
                    ->color(static fn (PopupCampaign $record): string => $record->status->color()),
                TextColumn::make('priority')
                    ->label(__('notifications::popups.fields.priority'))
                    ->numeric()
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('starts_at')
                    ->label(__('notifications::popups.fields.starts_at'))
                    ->dateTime()
                    ->timezone(config('app.timezone'))
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->label(__('notifications::popups.fields.ends_at'))
                    ->dateTime()
                    ->timezone(config('app.timezone')),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('notifications::popups.fields.type'))
                    ->options(PopupType::options()),
                SelectFilter::make('status')
                    ->label(__('notifications::popups.fields.status'))
                    ->options(PopupCampaignStatus::options()),
                SelectFilter::make('placement')
                    ->label(__('notifications::popups.fields.placement'))
                    ->options(PopupPlacement::options()),
                TernaryFilter::make('is_active_now')
                    ->label(__('notifications::popups.filters.active_now'))
                    ->queries(
                        true: static fn (Builder $query): Builder => $query
                            ->where('status', PopupCampaignStatus::Published->value)
                            ->where('starts_at', '<=', now())
                            ->where(static function (Builder $inner): void {
                                $inner->whereNull('ends_at')->orWhere('ends_at', '>', now());
                            }),
                        false: static fn (Builder $query): Builder => $query
                            ->where(static function (Builder $inner): void {
                                $inner
                                    ->where('status', '!=', PopupCampaignStatus::Published->value)
                                    ->orWhere('starts_at', '>', now())
                                    ->orWhere('ends_at', '<=', now());
                            }),
                    ),
            ])
            ->recordActions([
                ...Pages\ListPopupCampaigns::recordActionsForTable(),
            ])
            ->bulkActions([
                BulkAction::make('pause')
                    ->label(__('notifications::popups.actions.pause'))
                    ->icon('heroicon-o-pause-circle')
                    ->requiresConfirmation()
                    ->form([
                        Textarea::make('reason')
                            ->label(__('notifications::popups.fields.reason'))
                            ->required()
                            ->maxLength(2000),
                    ])
                    ->visible(static fn (): bool => auth()->user()?->can('popup_campaign.pause') ?? false)
                    ->deselectRecordsAfterCompletion()
                    ->action(static function (Collection $records, array $data): void {
                        foreach ($records as $record) {
                            try {
                                app(TransitionPopupCampaignAction::class)->execute(
                                    campaign: $record,
                                    target: PopupCampaignStatus::Paused,
                                    actorId: (string) auth()->id(),
                                    reason: (string) $data['reason'],
                                );
                            } catch (BusinessRuleViolation) {
                                continue;
                            }
                        }
                    }),
                BulkAction::make('archive')
                    ->label(__('notifications::popups.actions.archive'))
                    ->icon('heroicon-o-archive-box')
                    ->requiresConfirmation()
                    ->form([
                        Textarea::make('reason')
                            ->label(__('notifications::popups.fields.reason'))
                            ->required()
                            ->maxLength(2000),
                    ])
                    ->visible(static fn (): bool => auth()->user()?->can('popup_campaign.archive') ?? false)
                    ->deselectRecordsAfterCompletion()
                    ->action(static function (Collection $records, array $data): void {
                        foreach ($records as $record) {
                            try {
                                app(TransitionPopupCampaignAction::class)->execute(
                                    campaign: $record,
                                    target: PopupCampaignStatus::Archived,
                                    actorId: (string) auth()->id(),
                                    reason: (string) $data['reason'],
                                );
                            } catch (BusinessRuleViolation) {
                                continue;
                            }
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPopupCampaigns::route('/'),
            'create' => Pages\CreatePopupCampaign::route('/create'),
            'view' => Pages\ViewPopupCampaign::route('/{record}'),
            'edit' => Pages\EditPopupCampaign::route('/{record}/edit'),
        ];
    }
}
