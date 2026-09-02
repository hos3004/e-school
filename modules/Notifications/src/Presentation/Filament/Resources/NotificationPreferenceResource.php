<?php

declare(strict_types=1);

namespace Modules\Notifications\Presentation\Filament\Resources;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Modules\Notifications\Domain\Enums\Channel;
use Modules\Notifications\Domain\Models\NotificationPreference;
use Shared\Concerns\ScopesFilamentToOrganization;

/**
 * مورد تفضيلات الإشعارات في لوحة الإدارة — إدارة مركزية لتفضيلات المستخدمين.
 */
final class NotificationPreferenceResource extends Resource
{
    use ScopesFilamentToOrganization;

    protected static ?string $model = NotificationPreference::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?int $navigationSort = 71;

    // الخانة معلنة هنا لا في الصنف الأب: `$navigationParentItem` في Filament
    // مشتركة بين كل الموارد، فبلا إعادة إعلانها يدهس آخرُ إسناد ما قبله.
    // القيمة نفسها تُضبط مركزيًا في App\Filament\AdminNavigation.
    protected static ?string $navigationParentItem = null;

    public static function getNavigationGroup(): string
    {
        return __('notifications::navigation.group');
    }

    protected static ?string $recordTitleAttribute = 'id';

    public static function getModelLabel(): string
    {
        return __('notifications::navigation.preference.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('notifications::navigation.preference.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                TextInput::make('user_id')
                    ->label(__('notifications::fields.user_id'))
                    ->required()
                    ->maxLength(26),
                Select::make('category')
                    ->label(__('notifications::fields.category'))
                    ->options(self::categoryOptions())
                    ->searchable()
                    ->required(),
                Select::make('channel')
                    ->label(__('notifications::fields.channel'))
                    ->options(collect(Channel::cases())
                        ->mapWithKeys(fn (Channel $c): array => [$c->value => $c->label()])
                        ->all())
                    ->required(),
                Toggle::make('enabled')
                    ->label(__('notifications::fields.enabled'))
                    ->default(true)
                    ->required(),
            ])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label(__('notifications::fields.id'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('user_id')
                    ->label(__('notifications::fields.user_id'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category')
                    ->label(__('notifications::fields.category'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('channel')
                    ->label(__('notifications::fields.channel'))
                    ->formatStateUsing(fn ($state): string => $state instanceof Channel
                        ? $state->label()
                        : Channel::tryFrom((string) $state)?->label() ?? (string) $state)
                    ->badge()
                    ->sortable(),
                IconColumn::make('enabled')
                    ->label(__('notifications::fields.enabled'))
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label(__('notifications::fields.updated_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label(__('notifications::fields.category'))
                    ->options(self::categoryOptions()),
                SelectFilter::make('channel')
                    ->label(__('notifications::fields.channel'))
                    ->options(collect(Channel::cases())
                        ->mapWithKeys(fn (Channel $c): array => [$c->value => $c->label()])
                        ->all()),
                TernaryFilter::make('enabled')
                    ->label(__('notifications::fields.enabled')),
            ])
            ->defaultSort('updated_at', direction: 'desc');
    }

    /**
     * فئات الإشعارات المعرّفة في الإعداد — مفتاح الفئة نفسه هو التسمية.
     *
     * @return array<string, string>
     */
    private static function categoryOptions(): array
    {
        $categories = array_keys((array) config('notifications.categories', []));
        sort($categories);

        return array_combine($categories, $categories) ?: [];
    }
}
