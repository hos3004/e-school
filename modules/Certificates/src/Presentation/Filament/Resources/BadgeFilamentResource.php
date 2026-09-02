<?php

declare(strict_types=1);

namespace Modules\Certificates\Presentation\Filament\Resources;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Modules\Certificates\Application\Actions\AwardBadgeAction;
use Modules\Certificates\Domain\Enums\BadgeTier;
use Modules\Certificates\Domain\Models\Badge;
use Modules\Certificates\Domain\Models\BadgeAward;
use Modules\Identity\Domain\Contracts\UserQueryService;
use Shared\Codes\EntityCodeGenerator;
use Shared\Concerns\ScopesFilamentToOrganization;

/**
 * مورد إدارة شارات الكتالوج في لوحة الإدارة.
 */
final class BadgeFilamentResource extends Resource
{
    use ScopesFilamentToOrganization;

    protected static ?string $model = Badge::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-trophy';

    protected static ?int $navigationSort = 53;

    // الخانة معلنة هنا لا في الصنف الأب: `$navigationParentItem` في Filament
    // مشتركة بين كل الموارد، فبلا إعادة إعلانها يدهس آخرُ إسناد ما قبله.
    // القيمة نفسها تُضبط مركزيًا في App\Filament\AdminNavigation.
    protected static ?string $navigationParentItem = null;

    public static function getNavigationGroup(): ?string
    {
        return __('certificates::navigation.group');
    }

    public static function getModelLabel(): string
    {
        return __('certificates::navigation.badge.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('certificates::navigation.badge.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('certificates::fields.identity'))
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('organization_id')
                            ->label(__('certificates::fields.organization'))
                            ->required()
                            ->length(26),
                        TextInput::make('code')
                            ->label(__('certificates::fields.code'))
                            ->required()
                            ->alphaDash()
                            ->default(fn (EntityCodeGenerator $codes): string => $codes->next('badge'))
                            ->maxLength(8),
                        Select::make('tier')
                            ->label(__('certificates::fields.tier'))
                            ->options(collect(BadgeTier::cases())
                                ->mapWithKeys(fn (BadgeTier $tier): array => [$tier->value => $tier->label()])
                                ->all())
                            ->required(),
                        Toggle::make('is_active')
                            ->label(__('certificates::fields.is_active'))
                            ->default(true),
                    ]),
                    Textarea::make('name')
                        ->label(__('certificates::fields.name'))
                        ->formatStateUsing(fn ($state): string => is_array($state)
                            ? json_encode($state, JSON_UNESCAPED_UNICODE)
                            : (string) $state)
                        ->required()
                        ->columnSpanFull(),
                    Textarea::make('description')
                        ->label(__('certificates::fields.description'))
                        ->formatStateUsing(fn ($state): string => is_array($state)
                            ? json_encode($state, JSON_UNESCAPED_UNICODE)
                            : (string) $state)
                        ->columnSpanFull(),
                    TextInput::make('icon_path')
                        ->label(__('certificates::fields.icon'))
                        ->maxLength(2048)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label(__('certificates::fields.code'))
                    ->searchable()
                    ->badge(),
                TextColumn::make('name')
                    ->label(__('certificates::fields.name'))
                    ->formatStateUsing(fn ($state): string => is_array($state)
                        ? ($state[app()->getLocale()] ?? reset($state))
                        : (string) $state)
                    ->limit(40)
                    ->searchable(),
                TextColumn::make('tier')
                    ->label(__('certificates::fields.tier'))
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof BadgeTier
                        ? $state->label()
                        : (string) $state)
                    ->color(fn ($state): string => $state instanceof BadgeTier
                        ? $state->color()
                        : 'gray'),
                IconColumn::make('is_active')
                    ->label(__('certificates::fields.is_active'))
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('tier')
                    ->label(__('certificates::fields.tier'))
                    ->options(collect(BadgeTier::cases())
                        ->mapWithKeys(fn (BadgeTier $tier): array => [$tier->value => $tier->label()])
                        ->all()),
                TernaryFilter::make('is_active')
                    ->label(__('certificates::fields.is_active')),
            ])
            ->recordActions([self::awardAction()])
            ->defaultSort('code');
    }

    /**
     * منح الشارة لمستخدم بعينه.
     *
     * `AwardBadgeAction` كان بلا زر، فلم يكن المنح ممكنًا إلا بقاعدة آلية.
     * مكان الزر هنا لا في «منح الشارات»: المنح يبدأ من الشارة لا من سجل المنح،
     * وهو ما يجعل الشارة معلومةً والمستفيد هو المتغيّر.
     *
     * الإجراء نفسه يرفض الشارة المعطّلة والمنح المكرر، فيبقى الحارس واحدًا.
     */
    public static function awardAction(): Action
    {
        return Action::make('award')
            ->label(__('certificates::navigation.badge.award'))
            ->icon('heroicon-m-gift')
            ->color('success')
            /*
             * الصلاحية تُفحص صراحةً على `BadgeAward` لا عبر `authorize()`:
             * الأخيرة تمرّر سجل الصف (شارة) إلى السياسة، والصلاحية المطلوبة
             * هنا هي إنشاء **منحة** لا تعديل شارة.
             */
            ->visible(fn (Badge $record): bool => (bool) $record->is_active
                && (auth()->user()?->can('create', BadgeAward::class) ?? false))
            ->form([
                /*
                 * البحث عبر عقد Identity المعلن لا عبر نموذج User: Identity
                 * موديول مختوم، واستيراد نماذجه من هنا يكسر البند 2.
                 */
                Select::make('user_id')
                    ->label(__('certificates::fields.user'))
                    ->searchable()
                    ->required()
                    ->getSearchResultsUsing(function (string $search): array {
                        $users = app(UserQueryService::class);
                        $ids = $users->searchUserIdsForOrganization(
                            (string) session('organization_id'),
                            $search,
                            25,
                        );

                        $options = [];
                        foreach ($users->summariesByIds($ids) as $summary) {
                            $options[$summary->id] = $summary->name;
                        }

                        return $options;
                    })
                    ->getOptionLabelUsing(
                        fn (string $value): ?string => app(UserQueryService::class)
                            ->findSummary($value)?->name,
                    ),
                Textarea::make('reason')
                    ->label(__('certificates::fields.reason'))
                    ->maxLength(1000),
            ])
            ->action(function (Badge $record, array $data): void {
                app(AwardBadgeAction::class)->execute(
                    $record,
                    (string) $data['user_id'],
                    $data['reason'] ?? null,
                    (string) auth()->id(),
                );

                Notification::make()
                    ->title(__('certificates::navigation.badge.awarded'))
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
            'index' => BadgeFilamentResource\Pages\ListBadges::route('/'),
        ];
    }
}
