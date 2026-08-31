<?php

declare(strict_types=1);

namespace Modules\Guardians\Presentation\Filament\Resources;

use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Guardians\Application\Queries\GuardianAdministrationQueryService;
use Modules\Guardians\Domain\Enums\ContactChannel;
use Modules\Guardians\Domain\Models\GuardianProfile;
use Modules\Guardians\Presentation\Filament\Resources\Pages\CreateGuardianProfile;
use Modules\Guardians\Presentation\Filament\Resources\Pages\ManageGuardianProfiles;
use Modules\Guardians\Presentation\Filament\Resources\Pages\ViewGuardianProfile;

final class GuardianProfileFilamentResource extends Resource
{
    protected static ?string $model = GuardianProfile::class;

    protected static ?string $slug = 'guardians';

    protected static ?int $navigationSort = 30;

    public static function getNavigationGroup(): string
    {
        return __('guardians::filament.navigation_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('guardians::filament.profile.plural_label');
    }

    public static function getModelLabel(): string
    {
        return __('guardians::filament.profile.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('guardians::filament.profile.plural_label');
    }

    public static function canCreate(): bool
    {
        return (bool) auth()->user()?->can('guardian.link');
    }

    /** @return Builder<GuardianProfile> */
    public static function getEloquentQuery(): Builder
    {
        $organizationId = data_get(auth()->user(), 'organization_id');
        /** @var Builder<GuardianProfile> $query */
        $query = parent::getEloquentQuery();

        return is_string($organizationId) && $organizationId !== ''
            ? $query->forOrganization($organizationId)
            : $query->whereRaw('1 = 0');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('user_id')
                ->label(__('guardians::filament.profile.fields.user_id'))
                ->disabled()
                ->dehydrated(false),

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
                TextColumn::make('account_name')
                    ->label(__('guardians::admin.fields.name'))
                    ->state(fn (GuardianProfile $record): string => app(GuardianAdministrationQueryService::class)->accountName($record)),
                TextColumn::make('account_contact')
                    ->label(__('guardians::admin.fields.contact'))
                    ->state(fn (GuardianProfile $record): ?string => app(GuardianAdministrationQueryService::class)->accountContact($record))
                    ->placeholder(__('guardians::admin.common.not_available')),
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
                    ->counts('links')
                    ->badge(),
                TextColumn::make('created_at')
                    ->label(__('guardians::filament.common.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageGuardianProfiles::route('/'),
            'create' => CreateGuardianProfile::route('/create'),
            'view' => ViewGuardianProfile::route('/{record}'),
        ];
    }
}
