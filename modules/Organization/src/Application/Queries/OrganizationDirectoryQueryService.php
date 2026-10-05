<?php

declare(strict_types=1);

namespace Modules\Organization\Application\Queries;

use Modules\Organization\Domain\Contracts\OrganizationDirectoryQueries;
use Modules\Organization\Domain\Models\Organization;

final class OrganizationDirectoryQueryService implements OrganizationDirectoryQueries
{
    public function activeOrganizationIds(): array
    {
        return Organization::query()
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->values()
            ->all();
    }
}
