<?php

declare(strict_types=1);

namespace Modules\Notifications\Presentation\Filament\Resources;

use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Notifications\Domain\Enums\Channel;
use Modules\Notifications\Domain\Models\NotificationCategorySetting;
use Modules\Notifications\Presentation\Filament\Resources\NotificationCategorySettingResource\Pages;

/**
 * مورد إعدادات فئات الإشعارات — تحكم الأدمن في: أي قنوات لكل فئة، وهل الفئة
 * حرجة (تتجاوز تفضيلات المستخدم وساعات الهدوء)، وهل تخضع لساعات الهدوء.
 * الفئات ثابتة؛ تُضمَن صفوفها تلقائيًا من الافتراضي عند فتح القائمة.
 */
final class NotificationCategorySettingResource extends Resource
{
    protected static ?string $model = NotificationCategorySetting::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-signal';

    protected static ?int $navigationSort = 73;

    protected static ?string $recordTitleAttribute = 'category';

    public static function getNavigationGroup(): string
    {
        return __('notifications::navigation.group');
    }

    public static function getModelLabel(): string
    {
        return __('notifications::navigation.category_setting.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('notifications::navigation.category_setting.plural');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('notifications::category_settings.routing'))
                ->description(__('notifications::category_settings.routing_hint'))
                ->schema([
                    TextInput::make('category')
                        ->label(__('notifications::fields.category'))
                        ->disabled()
                        ->dehydrated(false),
                    Select::make('channels')
                        ->label(__('notifications::fields.channel'))
                        ->multiple()
                        ->options(collect(Channel::cases())
                            ->mapWithKeys(fn (Channel $c): array => [$c->value => $c->label()])
                            ->all())
                        ->helperText(__('notifications::category_settings.channels_hint'))
                        ->required(),
                    Toggle::make('is_critical')
                        ->label(__('notifications::category_settings.is_critical'))
                        ->helperText(__('notifications::category_settings.is_critical_hint'))
                        ->inline(false),
                    Toggle::make('respects_quiet_hours')
                        ->label(__('notifications::category_settings.respects_quiet_hours'))
                        ->helperText(__('notifications::category_settings.respects_quiet_hours_hint'))
                        ->inline(false),
                ])
                ->columns(2)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('category')
                    ->label(__('notifications::fields.category'))
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('channels')
                    ->label(__('notifications::fields.channel'))
                    ->badge()
                    ->state(fn (NotificationCategorySetting $record): array => array_map(
                        static fn (string $channel): string => Channel::tryFrom($channel)?->label() ?? $channel,
                        array_values((array) $record->channels),
                    )),
                IconColumn::make('is_critical')
                    ->label(__('notifications::category_settings.is_critical'))
                    ->boolean(),
                IconColumn::make('respects_quiet_hours')
                    ->label(__('notifications::category_settings.respects_quiet_hours'))
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label(__('notifications::fields.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('is_critical')
                    ->label(__('notifications::category_settings.is_critical')),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->paginated(false)
            ->defaultSort('category');
    }

    /**
     * إعدادات مؤسسة المستخدم فقط؛ غياب المؤسسة يغلق الاستعلام بدل كشف الجميع.
     *
     * @return Builder<NotificationCategorySetting>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<NotificationCategorySetting> $query */
        $query = parent::getEloquentQuery();
        $organizationId = data_get(auth()->user(), 'organization_id');

        if (!is_string($organizationId) || $organizationId === '') {
            return $query->whereRaw('1 = 0');
        }

        return $query->forOrganization($organizationId);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNotificationCategorySettings::route('/'),
            'edit' => Pages\EditNotificationCategorySetting::route('/{record}/edit'),
        ];
    }
}
