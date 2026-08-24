<?php

declare(strict_types=1);

namespace Modules\AccessControl\Presentation\Filament\Resources\PermissionResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\AccessControl\Presentation\Filament\Resources\PermissionResource;

final class ListPermissions extends ListRecords
{
    protected static string $resource = PermissionResource::class;
}
