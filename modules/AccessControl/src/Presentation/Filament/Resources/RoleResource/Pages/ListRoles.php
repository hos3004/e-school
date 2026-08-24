<?php

declare(strict_types=1);

namespace Modules\AccessControl\Presentation\Filament\Resources\RoleResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\AccessControl\Domain\Models\Role;
use Modules\AccessControl\Presentation\Filament\Resources\RoleResource;

final class ListRoles extends ListRecords
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn (): bool => auth()->user()?->can('create', Role::class) === true),
        ];
    }
}
