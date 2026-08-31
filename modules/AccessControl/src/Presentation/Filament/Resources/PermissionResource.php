<?php

declare(strict_types=1);

namespace Modules\AccessControl\Presentation\Filament\Resources;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\AccessControl\Domain\Enums\GuardName;
use Modules\AccessControl\Domain\Models\Permission;
use Modules\AccessControl\Presentation\Filament\Resources\PermissionResource\Pages\ListPermissions;
use Modules\AccessControl\Presentation\Support\AccessControlLabels;

final class PermissionResource extends Resource
{
    protected static ?string $model = Permission::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-key';

    protected static ?int $navigationSort = 101;

    public static function getNavigationGroup(): ?string
    {
        return __('accesscontrol::filament.group');
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->can('viewAny', Permission::class);
    }

    public static function getModelLabel(): string
    {
        return __('accesscontrol::filament.permission.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('accesscontrol::filament.permission.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                TextInput::make('name')
                    ->label(__('accesscontrol::filament.permission.fields.name'))
                    ->required()
                    ->maxLength(191)
                    ->regex('/^[a-z][a-z0-9_.-]*$/')
                    ->unique(ignoreRecord: true),

                Select::make('guard_name')
                    ->label(__('accesscontrol::filament.permission.fields.guard'))
                    ->options(array_combine(
                        array_map(static fn (GuardName $g): string => $g->value, GuardName::all()),
                        array_map(static fn (GuardName $g): string => $g->label(), GuardName::all()),
                    ))
                    ->default(GuardName::Web->value)
                    ->required(),

                TextInput::make('module')
                    ->label(__('accesscontrol::filament.permission.fields.module'))
                    ->maxLength(64),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('accesscontrol::filament.permission.fields.name'))
                    // البحث والفرز على المفتاح الأصلي؛ المعروض هو الترجمة.
                    ->formatStateUsing(fn (?string $state): string => AccessControlLabels::permission($state))
                    ->description(fn (Permission $record): string => (string) $record->name)
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyableState(fn (Permission $record): string => (string) $record->name),

                TextColumn::make('module')
                    ->label(__('accesscontrol::filament.permission.fields.module'))
                    ->formatStateUsing(fn (?string $state): string => AccessControlLabels::module($state))
                    ->badge()
                    ->sortable(),

                TextColumn::make('guard_name')
                    ->label(__('accesscontrol::filament.permission.fields.guard'))
                    ->badge()
                    ->formatStateUsing(fn (GuardName|string $state): string => $state instanceof GuardName
                        ? $state->label()
                        : (GuardName::tryFrom($state)?->label() ?? $state)),
            ])
            ->filters([
                SelectFilter::make('guard_name')
                    ->label(__('accesscontrol::filament.permission.fields.guard'))
                    ->options(array_combine(
                        array_map(static fn (GuardName $g): string => $g->value, GuardName::all()),
                        array_map(static fn (GuardName $g): string => $g->label(), GuardName::all()),
                    )),

                SelectFilter::make('module')
                    ->label(__('accesscontrol::filament.permission.fields.module'))
                    ->options(
                        fn (): array => AccessControlLabels::options(
                            'modules',
                            Permission::query()
                                ->whereNotNull('module')
                                ->distinct()
                                ->orderBy('module')
                                ->pluck('module')
                                ->all(),
                        ),
                    ),
            ])
            ->paginated([25, 50, 100]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPermissions::route('/'),
        ];
    }
}
