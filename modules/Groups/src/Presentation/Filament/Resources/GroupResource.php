<?php

declare(strict_types=1);

namespace Modules\Groups\Presentation\Filament\Resources;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Modules\Groups\Domain\Enums\GroupStatus;
use Modules\Groups\Domain\Models\Group;
use Modules\Groups\Presentation\Filament\Resources\GroupResource\Pages;
use Shared\Concerns\ScopesFilamentToOrganization;

/**
 * إدارة المجموعات في لوحة التحكم — كل النصوص عبر ملفات الترجمة.
 */
final class GroupResource extends Resource
{
    use ScopesFilamentToOrganization;

    protected static ?string $model = Group::class;

    protected static ?string $slug = 'groups';

    protected static ?int $navigationSort = 11;

    public static function getNavigationGroup(): ?string
    {
        return __('groups::filament.navigation_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('groups::filament.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('groups::filament.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('groups::filament.plural_model_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('organization_id')
                    ->label(__('groups::attributes.organization_id'))
                    ->required()
                    ->length(26),

                TextInput::make('code')
                    ->label(__('groups::attributes.code'))
                    ->required()
                    ->maxLength(32)
                    ->unique(ignoreRecord: true),

                KeyValue::make('name')
                    ->label(__('groups::attributes.name'))
                    ->keyLabel(__('groups::filament.name_locale_key'))
                    ->valueLabel(__('groups::filament.name_value_label'))
                    ->required(),

                TextInput::make('capacity')
                    ->label(__('groups::attributes.capacity'))
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(25),

                TextInput::make('timezone')
                    ->label(__('groups::attributes.timezone'))
                    ->required()
                    ->maxLength(64),

                Select::make('status')
                    ->label(__('groups::attributes.status'))
                    ->options(collect(GroupStatus::cases())
                        ->mapWithKeys(fn (GroupStatus $status): array => [$status->value => $status->label()])
                        ->all())
                    ->disabled()
                    ->dehydrated(false),

                DatePicker::make('starts_on')
                    ->label(__('groups::attributes.starts_on'))
                    ->required(),

                DatePicker::make('ends_on')
                    ->label(__('groups::attributes.ends_on'))
                    ->afterOrEqual('starts_on')
                    ->nullable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label(__('groups::attributes.code'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name.ar')
                    ->label(__('groups::attributes.name'))
                    ->searchable(),

                TextColumn::make('capacity')
                    ->label(__('groups::attributes.capacity'))
                    ->numeric()
                    ->sortable(),

                TextColumn::make('memberships_count')
                    ->counts(['memberships' => fn ($query) => $query->whereNull('left_at')])
                    ->label(__('groups::filament.active_members_count')),

                TextColumn::make('status')
                    ->label(__('groups::attributes.status'))
                    ->badge()
                    ->formatStateUsing(fn (?GroupStatus $state): string => $state?->label() ?? '')
                    ->color(fn (?GroupStatus $state): string => $state?->color() ?? 'gray'),

                TextColumn::make('starts_on')
                    ->label(__('groups::attributes.starts_on'))
                    ->date()
                    ->sortable(),

                TextColumn::make('ends_on')
                    ->label(__('groups::attributes.ends_on'))
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('groups::attributes.status'))
                    ->options(collect(GroupStatus::cases())
                        ->mapWithKeys(fn (GroupStatus $status): array => [$status->value => $status->label()])
                        ->all()),

                TrashedFilter::make(),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGroups::route('/'),
            'create' => Pages\CreateGroup::route('/create'),
            'view' => Pages\ViewGroup::route('/{record}'),
            'edit' => Pages\EditGroup::route('/{record}/edit'),
        ];
    }
}
