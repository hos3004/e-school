<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Filament\Resources\Users;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Password;
use Modules\AccessControl\Domain\Contracts\AccessControlQuerier;
use Modules\AccessControl\Domain\ValueObjects\RoleData;
use Modules\Identity\Domain\Contracts\AvatarQueries;
use Modules\Identity\Domain\Enums\UserStatus;
use Modules\Identity\Domain\Models\User;
use Modules\Identity\Presentation\Filament\Resources\Users\Pages\CreateUser;
use Modules\Identity\Presentation\Filament\Resources\Users\Pages\EditUser;
use Modules\Identity\Presentation\Filament\Resources\Users\Pages\ListUsers;
use Modules\Identity\Presentation\Filament\Resources\Users\Pages\ViewUser;
use Shared\Support\Locales;

final class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?int $navigationSort = 100;

    public static function getNavigationGroup(): string
    {
        return __('identity::filament.navigation_group');
    }

    public static function getModelLabel(): string
    {
        return __('identity::filament.user.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('identity::filament.user.label_plural');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', User::class) ?? false;
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();

        return $user !== null
            && $user->can('identity.users.create')
            && $user->can('accesscontrol.assignments.assign_role');
    }

    /** @return Builder<User> */
    public static function getEloquentQuery(): Builder
    {
        $organizationId = auth()->user()?->getAttribute('organization_id');
        /** @var Builder<User> $query */
        $query = parent::getEloquentQuery();

        return is_string($organizationId) && $organizationId !== ''
            ? $query->forOrganization($organizationId)
            : $query->whereRaw('1 = 0');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('identity::filament.user.section_account'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('identity::labels.name'))
                            ->required()
                            ->maxLength(191),
                        TextInput::make('email')
                            ->label(__('identity::labels.email'))
                            ->email()
                            ->requiredWithout('phone')
                            ->maxLength(191)
                            ->unique(ignoreRecord: true)
                            ->disabledOn('edit'),
                        TextInput::make('username')
                            ->label(__('identity::labels.username'))
                            ->required()
                            ->minLength((int) config('admission.username.min_length'))
                            ->maxLength((int) config('admission.username.max_length'))
                            ->notIn((array) config('admission.username.reserved', []))
                            ->unique(ignoreRecord: true)
                            ->disabledOn('edit'),
                        TextInput::make('phone')
                            ->label(__('identity::labels.phone'))
                            ->requiredWithout('email')
                            ->maxLength(32),
                        TextInput::make('password')
                            ->label(__('identity::labels.password'))
                            ->password()
                            ->revealable()
                            ->rule(Password::defaults())
                            ->required()
                            ->visibleOn('create'),
                        TextInput::make('password_confirmation')
                            ->label(__('identity::admin.password_confirmation'))
                            ->password()
                            ->revealable()
                            ->same('password')
                            ->required()
                            ->visibleOn('create'),
                    ])
                    ->columns(2),
                Section::make(__('identity::filament.user.section_preferences'))
                    ->schema([
                        Select::make('locale')
                            ->label(__('identity::labels.locale'))
                            ->options(Locales::options('identity::locales.'))
                            ->default('ar'),
                        TextInput::make('timezone')
                            ->label(__('identity::labels.timezone'))
                            ->default((string) config('app.timezone'))
                            ->required(),
                        Select::make('role_name')
                            ->label(__('identity::admin.role'))
                            ->options(fn (): array => self::managedRoleOptions())
                            ->searchable()
                            ->required()
                            ->visibleOn('create'),
                        Textarea::make('reason')
                            ->label(__('identity::labels.reason'))
                            ->helperText(__('identity::admin.creation_reason_help'))
                            ->maxLength(2000)
                            ->required()
                            ->visibleOn('create')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('avatar')
                    ->label(__('identity::avatars.field'))
                    ->circular()
                    ->state(fn (User $record): string => app(AvatarQueries::class)->resolve($record->avatar_path, null)->url)
                    ->defaultImageUrl(fn (): string => app(AvatarQueries::class)->defaultUrl(null))
                    ->alt(fn (User $record): string => __('identity::avatars.alt', ['name' => $record->name]))
                    ->extraImgAttributes(['loading' => 'lazy']),
                TextColumn::make('name')
                    ->label(__('identity::labels.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label(__('identity::labels.email'))
                    ->searchable()
                    ->copyable(),
                TextColumn::make('status')
                    ->label(__('identity::labels.status'))
                    ->badge()
                    ->formatStateUsing(fn (UserStatus $state): string => $state->label())
                    ->color(fn (UserStatus $state): string => $state->color()),
                IconColumn::make('email_verified_at')
                    ->label(__('identity::labels.verified'))
                    ->boolean(),
                TextColumn::make('last_login_at')
                    ->label(__('identity::labels.last_login_at'))
                    ->since()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('identity::labels.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('identity::labels.status'))
                    ->options(collect(UserStatus::cases())
                        ->mapWithKeys(fn (UserStatus $s): array => [$s->value => $s->label()])
                        ->all()),
                TernaryFilter::make('email_verified_at')
                    ->label(__('identity::labels.verified')),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn (User $record, mixed $livewire = null): bool => auth()->user()?->can('update', $record) ?? false),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'view' => ViewUser::route('/{record}'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }

    /** @return array<string, string> */
    private static function managedRoleOptions(): array
    {
        $organizationId = data_get(auth()->user(), 'organization_id');

        if (!is_string($organizationId) || $organizationId === '') {
            return [];
        }

        return collect(app(AccessControlQuerier::class)->rolesAvailableToOrganization($organizationId))
            ->reject(fn (RoleData $role): bool => in_array(
                $role->name,
                (array) config('identity.managed_accounts.excluded_roles', []),
                true,
            ))
            ->mapWithKeys(static fn (RoleData $role): array => [$role->name => self::roleLabel($role->name)])
            ->all();
    }

    public static function roleLabel(string $roleName): string
    {
        $key = 'identity::admin.roles.'.$roleName;

        return trans()->has($key) ? __($key) : $roleName;
    }
}
