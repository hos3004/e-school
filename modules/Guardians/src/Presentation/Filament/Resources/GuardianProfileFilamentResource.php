<?php

declare(strict_types=1);

namespace Modules\Guardians\Presentation\Filament\Resources;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Modules\Guardians\Domain\Enums\ContactChannel;
use Modules\Guardians\Domain\Models\GuardianProfile;
use Modules\Guardians\Presentation\Filament\Resources\Pages\ManageGuardianProfiles;

final class GuardianProfileFilamentResource extends Resource
{
    protected static ?string $model = GuardianProfile::class;

    protected static bool $shouldRegisterNavigation = true;

    public static function getModelLabel(): string
    {
        return __('guardians::filament.profile.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('guardians::filament.profile.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('user_id')
                ->label(__('guardians::filament.profile.fields.user_id'))
                ->required()
                ->length(26)
                ->disabled(),

            TextInput::make('national_id_last4')
                ->label(__('guardians::filament.profile.fields.national_id_last4'))
                ->numeric()
                ->length(4),

            TextInput::make('occupation')
                ->label(__('guardians::filament.profile.fields.occupation'))
                ->maxLength(120),

            Select::make('preferred_contact_channel')
                ->label(__('guardians::filament.profile.fields.preferred_contact_channel'))
                ->options(collect(ContactChannel::cases())->mapWithKeys(
                    fn (ContactChannel $channel): array => [$channel->value => $channel->label()],
                )->all())
                ->searchable(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label(__('guardians::filament.common.id'))
                    ->sortable()
                    ->copyable(),
                TextColumn::make('occupation')
                    ->label(__('guardians::filament.profile.fields.occupation'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('preferred_contact_channel')
                    ->label(__('guardians::filament.profile.fields.preferred_contact_channel'))
                    ->badge()
                    ->formatStateUsing(fn (?ContactChannel $state): ?string => $state?->label()),
                TextColumn::make('links_count')
                    ->label(__('guardians::filament.profile.fields.links_count'))
                    ->counts('links'),
                TextColumn::make('created_at')
                    ->label(__('guardians::filament.common.created_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('deleted_at')
                    ->label(__('guardians::filament.common.archived_at'))
                    ->dateTime()
                    ->placeholder(__('guardians::filament.common.not_archived')),
            ])
            ->filters([
                TernaryFilter::make('archived')
                    ->label(__('guardians::filament.profile.filters.archived'))
                    ->queries(
                        true: fn ($query) => $query->onlyTrashed(),
                        false: fn ($query) => $query->withoutTrashed(),
                    ),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageGuardianProfiles::route('/'),
        ];
    }
}
