<?php

declare(strict_types=1);

namespace Modules\Organization\Presentation\Filament\Resources\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Organization\Presentation\Filament\Resources\OrganizationFilamentResource;

final class ListOrganizations extends ListRecords
{
    protected static string $resource = OrganizationFilamentResource::class;
}
