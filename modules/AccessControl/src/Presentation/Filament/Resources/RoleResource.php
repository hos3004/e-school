<?php

declare(strict_types=1);

namespace Modules\AccessControl\Presentation\Filament\Resources;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
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
use Modules\AccessControl\Application\Actions\AssignRoleAction;
use Modules\AccessControl\Application\Actions\RevokeRoleAction;
use Modules\AccessControl\Domain\Contracts\RoleAssignmentTargetScope;
use Modules\AccessControl\Domain\Enums\GuardName;
use Modules\AccessControl\Domain\Models\Permission;
use Modules\AccessControl\Domain\Models\Role;
use Modules\AccessControl\Presentation\Filament\Resources\RoleResource\Pages\CreateRole;
use Modules\AccessControl\Presentation\Filament\Resources\RoleResource\Pages\EditRole;
use Modules\AccessControl\Presentation\Filament\Resources\RoleResource\Pages\ListRoles;

final class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    protected static ?int $navigationSort = 101;

    public static function getNavigationGroup(): ?string
    {
        return __('accesscontrol::filament.group');
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->can('viewAny', Role::class);
    }

    /** @return Builder<Role> */
    public static function getEloquentQuery(): Builder
    {
        $organizationId = auth()->user()?->getAttribute('organization_id');

        return parent::getEloquentQuery()
            ->when(
                is_string($organizationId) && $organizationId !== '',
                fn (Builder $query): Builder => $query->includingGlobal($organizationId),
                fn (Builder $query): Builder => $query->whereRaw('1 = 0'),
            );
    }

    public static function getModelLabel(): string
    {
        return __('accesscontrol::filament.role.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('accesscontrol::filament.role.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                TextInput::make('name')
                    ->label(__('accesscontrol::filament.role.fields.name'))
                    ->required()
                    ->maxLength(191)
                    ->disabled(fn (?Role $record): bool => (bool) $record?->is_system),

                Select::make('guard_name')
                    ->label(__('accesscontrol::filament.role.fields.guard'))
                    ->options(array_combine(
                        array_map(static fn (GuardName $g): string => $g->value, GuardName::all()),
                        array_map(static fn (GuardName $g): string => $g->label(), GuardName::all()),
                    ))
                    ->default(GuardName::Web->value)
                    ->required()
                    ->disabled(fn (?Role $record): bool => $record !== null),

                Select::make('permission_names')
                    ->label(__('accesscontrol::filament.role.fields.permissions'))
                    ->options(fn (?Role $record): array => Permission::query()
                        ->where('guard_name', $record?->guard_name->value ?? GuardName::Web->value)
                        ->orderBy('name')
                        ->pluck('name', 'name')
                        ->all())
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->afterStateHydrated(function (Select $component, ?Role $record): void {
                        if ($record !== null) {
                            $component->state($record->permissions()->orderBy('name')->pluck('name')->all());
                        }
                    })
                    ->visible(fn (?Role $record): bool => $record !== null && !$record->is_system),

            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('accesscontrol::filament.role.fields.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('organization_id')
                    ->label(__('accesscontrol::filament.role.fields.organization'))
                    ->limit(12),

                TextColumn::make('guard_name')
                    ->label(__('accesscontrol::filament.role.fields.guard'))
                    ->badge()
                    ->formatStateUsing(fn (GuardName|string $state): string => $state instanceof GuardName
                        ? $state->label()
                        : (GuardName::tryFrom($state)?->label() ?? $state)),

                IconColumn::make('is_system')
                    ->label(__('accesscontrol::filament.role.fields.system'))
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label(__('accesscontrol::filament.fields.created_at'))
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_system')
                    ->label(__('accesscontrol::filament.role.filters.system')),

                SelectFilter::make('guard_name')
                    ->label(__('accesscontrol::filament.role.fields.guard'))
                    ->options(array_combine(
                        array_map(static fn (GuardName $g): string => $g->value, GuardName::all()),
                        array_map(static fn (GuardName $g): string => $g->label(), GuardName::all()),
                    )),
            ])
            ->actions([
                self::assignUserAction(),
                self::revokeUserAction(),
                EditAction::make()
                    ->visible(fn (Role $record): bool => !$record->is_system
                        && auth()->user()?->can('update', $record) === true),
            ]);
    }

    private static function assignUserAction(): Action
    {
        return Action::make('assign_user')
            ->label(__('accesscontrol::filament.role.actions.assign_user'))
            ->icon('heroicon-m-user-plus')
            ->visible(fn (Role $record): bool => auth()->user()?->can('assign', $record) === true)
            ->form([
                TextInput::make('user_id')
                    ->label(__('accesscontrol::filament.role.fields.user_id'))
                    ->required()
                    ->length(26),
            ])
            ->action(function (array $data, Role $record): void {
                $organizationId = self::actorOrganizationId();
                $userId = (string) $data['user_id'];
                $targets = app(RoleAssignmentTargetScope::class);

                app(AssignRoleAction::class)->execute(
                    roleId: (string) $record->getKey(),
                    modelType: $targets->modelTypeFor($organizationId, $userId),
                    modelId: $userId,
                    actorId: (string) auth()->id(),
                    organizationId: $organizationId,
                );
            });
    }

    private static function revokeUserAction(): Action
    {
        return Action::make('revoke_user')
            ->label(__('accesscontrol::filament.role.actions.revoke_user'))
            ->icon('heroicon-m-user-minus')
            ->color('warning')
            ->visible(fn (Role $record): bool => auth()->user()?->can('revoke', $record) === true)
            ->form([
                TextInput::make('user_id')
                    ->label(__('accesscontrol::filament.role.fields.user_id'))
                    ->required()
                    ->length(26),
            ])
            ->action(function (array $data, Role $record): void {
                $organizationId = self::actorOrganizationId();
                $userId = (string) $data['user_id'];
                $targets = app(RoleAssignmentTargetScope::class);

                app(RevokeRoleAction::class)->execute(
                    roleId: (string) $record->getKey(),
                    modelType: $targets->modelTypeFor($organizationId, $userId),
                    modelId: $userId,
                    actorId: (string) auth()->id(),
                    organizationId: $organizationId,
                );
            });
    }

    private static function actorOrganizationId(): string
    {
        $organizationId = auth()->user()?->getAttribute('organization_id');
        abort_unless(is_string($organizationId) && $organizationId !== '', 403);

        return $organizationId;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
            'create' => CreateRole::route('/create'),
            'edit' => EditRole::route('/{record}/edit'),
        ];
    }
}
