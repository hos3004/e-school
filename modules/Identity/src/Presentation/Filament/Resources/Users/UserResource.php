<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Filament\Resources\Users;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Password;
use Modules\Identity\Domain\Enums\UserStatus;
use Modules\Identity\Domain\Models\User;
use Modules\Identity\Presentation\Filament\Resources\Users\Pages\CreateUser;
use Modules\Identity\Presentation\Filament\Resources\Users\Pages\EditUser;
use Modules\Identity\Presentation\Filament\Resources\Users\Pages\ListUsers;

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

    /** @return Builder<User> */
    public static function getEloquentQuery(): Builder
    {
        $organizationId = auth()->user()?->getAttribute('organization_id');

        return parent::getEloquentQuery()
            ->when(
                is_string($organizationId) && $organizationId !== '',
                fn (Builder $query): Builder => $query->forOrganization($organizationId),
                fn (Builder $query): Builder => $query->whereRaw('1 = 0'),
            );
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
                            ->unique(ignoreRecord: true),
                        TextInput::make('username')
                            ->label(__('identity::labels.username'))
                            ->required()
                            ->minLength((int) config('admission.username.min_length'))
                            ->maxLength((int) config('admission.username.max_length'))
                            ->notIn((array) config('admission.username.reserved', []))
                            ->unique(ignoreRecord: true),
                        TextInput::make('phone')
                            ->label(__('identity::labels.phone'))
                            ->requiredWithout('email')
                            ->maxLength(32),
                        TextInput::make('password')
                            ->label(__('identity::labels.password'))
                            ->password()
                            ->revealable()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->rule(Password::defaults())
                            ->dehydrated(fn (?string $state): bool => filled($state)),
                    ])
                    ->columns(2),
                Section::make(__('identity::filament.user.section_preferences'))
                    ->schema([
                        Select::make('locale')
                            ->label(__('identity::labels.locale'))
                            ->options([
                                'ar' => __('identity::locales.ar'),
                                'en' => __('identity::locales.en'),
                                'fr' => __('identity::locales.fr'),
                            ])
                            ->default('ar'),
                        DateTimePicker::make('email_verified_at')
                            ->label(__('identity::labels.email_verified_at')),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
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
            ->actions([
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
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
