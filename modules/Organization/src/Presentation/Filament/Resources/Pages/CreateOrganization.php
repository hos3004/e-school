<?php

declare(strict_types=1);

namespace Modules\Organization\Presentation\Filament\Resources\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Organization\Presentation\Filament\Resources\OrganizationFilamentResource;

final class CreateOrganization extends CreateRecord
{
    protected static string $resource = OrganizationFilamentResource::class;
}
