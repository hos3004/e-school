<?php

declare(strict_types=1);

namespace Modules\Messaging\Presentation\Filament\Resources;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Modules\Messaging\Domain\Models\WhatsappInbound;
use Shared\Concerns\ScopesFilamentToOrganization;
use Shared\Filament\RecordOriginGuide;

/**
 * مورد صندوق رسائل واتساب الواردة في لوحة الإدارة.
 */
final class WhatsappInboundResource extends Resource
{
    use ScopesFilamentToOrganization;

    protected static ?string $model = WhatsappInbound::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static ?int $navigationSort = 70;

    // الخانة معلنة هنا لا في الصنف الأب: `$navigationParentItem` في Filament
    // مشتركة بين كل الموارد، فبلا إعادة إعلانها يدهس آخرُ إسناد ما قبله.
    // القيمة نفسها تُضبط مركزيًا في App\Filament\AdminNavigation.
    protected static ?string $navigationParentItem = null;

    public static function getNavigationGroup(): string
    {
        return __('messaging::navigation.group');
    }

    public static function getModelLabel(): string
    {
        return __('messaging::navigation.whatsapp_inbound.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('messaging::navigation.whatsapp_inbound.plural');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                TextInput::make('from_phone')
                    ->label(__('messaging::fields.from_phone'))
                    ->required()
                    ->maxLength(32),
                TextInput::make('message_id')
                    ->label(__('messaging::fields.message_id'))
                    ->required(),
                DateTimePicker::make('received_at')
                    ->label(__('messaging::fields.received_at'))
                    ->required(),
                TextInput::make('matched_user_id')
                    ->label(__('messaging::fields.matched_user'))
                    ->maxLength(26),
                TextInput::make('handled_by')
                    ->label(__('messaging::fields.handled_by'))
                    ->maxLength(26)
                    ->disabled(),
            ]),
            Textarea::make('body')
                ->label(__('messaging::fields.body'))
                ->required()
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return RecordOriginGuide::for(
            $table,
            'messaging::origin.whatsapp',
            'heroicon-o-device-phone-mobile',
        )
            ->columns([
                TextColumn::make('id')
                    ->label(__('messaging::fields.id'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('from_phone')
                    ->label(__('messaging::fields.from_phone'))
                    ->searchable()
                    ->copyable(),
                TextColumn::make('body')
                    ->label(__('messaging::fields.body'))
                    ->limit(50)
                    ->searchable(),
                IconColumn::make('is_handled')
                    ->label(__('messaging::fields.is_handled'))
                    ->state(fn (WhatsappInbound $record): bool => $record->handled_at !== null)
                    ->boolean(),
                TextColumn::make('received_at')
                    ->label(__('messaging::fields.received_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('handled_at')
                    ->label(__('messaging::fields.handled_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('handled')
                    ->label(__('messaging::fields.is_handled'))
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('handled_at'),
                        false: fn ($query) => $query->whereNull('handled_at'),
                    ),
            ])
            ->defaultSort('received_at', 'desc');
    }
}
