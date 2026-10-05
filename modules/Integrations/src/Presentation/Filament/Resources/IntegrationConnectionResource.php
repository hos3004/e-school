<?php

declare(strict_types=1);

namespace Modules\Integrations\Presentation\Filament\Resources;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Integrations\Domain\Enums\ConnectionStatus;
use Modules\Integrations\Domain\Models\IntegrationConnection;
use Modules\Integrations\Domain\Models\IntegrationProvider;
use Shared\Concerns\ScopesFilamentToOrganization;

/**
 * مورد إدارة اتصالات المؤسسات بالمزوّدين في لوحة الإدارة.
 */
final class IntegrationConnectionResource extends Resource
{
    use ScopesFilamentToOrganization;

    protected static ?string $model = IntegrationConnection::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-link';

    protected static ?int $navigationSort = 104;

    // الخانة معلنة هنا لا في الصنف الأب: `$navigationParentItem` في Filament
    // مشتركة بين كل الموارد، فبلا إعادة إعلانها يدهس آخرُ إسناد ما قبله.
    // القيمة نفسها تُضبط مركزيًا في App\Filament\AdminNavigation.
    protected static ?string $navigationParentItem = null;

    public static function getNavigationGroup(): string
    {
        return __('integrations::navigation.group');
    }

    public static function getModelLabel(): string
    {
        return __('integrations::navigation.connection.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('integrations::navigation.connection.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('integrations::fields.link'))
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('organization_id')
                            ->label(__('integrations::fields.organization'))
                            ->required()
                            ->maxLength(26),
                        Select::make('provider_id')
                            ->label(__('integrations::fields.provider'))
                            ->options(
                                IntegrationProvider::query()->orderBy('key')->pluck('key', 'id')->all(),
                            )
                            ->searchable()
                            ->required(),
                        Select::make('status')
                            ->label(__('integrations::fields.status'))
                            ->options(
                                collect(ConnectionStatus::cases())
                                    ->mapWithKeys(fn (ConnectionStatus $s): array => [$s->value => $s->label()])
                                    ->all(),
                            )
                            ->required(),
                        DateTimePicker::make('activated_at')
                            ->label(__('integrations::fields.activated_at')),
                    ]),
                ]),
            Section::make(__('integrations::fields.configuration'))
                ->schema([
                    KeyValue::make('settings')
                        ->label(__('integrations::fields.settings'))
                        ->columnSpanFull(),
                    Textarea::make('last_error_message')
                        ->label(__('integrations::fields.last_error_message'))
                        ->columnSpanFull()
                        ->disabled(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('provider.key')
                    ->label(__('integrations::fields.provider'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('organization_id')
                    ->label(__('integrations::fields.organization'))
                    ->limit(12)
                    ->copyable(),
                TextColumn::make('status')
                    ->label(__('integrations::fields.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof ConnectionStatus
                        ? $state->label()
                        : (string) $state)
                    ->color(fn ($state): string => $state instanceof ConnectionStatus
                        ? $state->color()
                        : 'gray'),
                TextColumn::make('activated_at')
                    ->label(__('integrations::fields.activated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('last_error_at')
                    ->label(__('integrations::fields.last_error_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('integrations::fields.status'))
                    ->options(
                        collect(ConnectionStatus::cases())
                            ->mapWithKeys(fn (ConnectionStatus $s): array => [$s->value => $s->label()])
                            ->all(),
                    ),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * @return array<string, mixed>
     */
    public static function getPages(): array
    {
        return [
            'index' => IntegrationConnectionResource\Pages\ListIntegrationConnections::route('/'),
        ];
    }
}
