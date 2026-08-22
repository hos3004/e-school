<?php

declare(strict_types=1);

namespace Modules\AccessControl\Presentation\Filament\Resources;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Modules\AccessControl\Domain\Enums\GuardName;
use Modules\AccessControl\Domain\Models\Role;

final class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null
            && ($user->can('accesscontrol.roles.view_any') || $user->can('accesscontrol.roles.view'));
    }

    public static function getModelLabel(): string
    {
        return __('accesscontrol::filament.role.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('accesscontrol::filament.role.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('accesscontrol::filament.group');
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
                    ->required(),

                TextInput::make('organization_id')
                    ->label(__('accesscontrol::filament.role.fields.organization'))
                    ->length(26)
                    ->nullable(),
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
                    ->formatStateUsing(fn (string $state): string => GuardName::tryFrom($state)?->label() ?? $state),

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
                EditAction::make()
                    ->visible(fn (Role $record): bool => ! $record->is_system
                        && auth()->user()?->can('update', $record) === true),
                DeleteAction::make()
                    ->visible(fn (Role $record): bool => ! $record->is_system
                        && auth()->user()?->can('delete', $record) === true),
            ]);
    }
}
