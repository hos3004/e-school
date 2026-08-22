<?php

declare(strict_types=1);

namespace Modules\Organization\Application\Queries;

use Illuminate\Support\Facades\DB;
use Modules\Organization\Domain\Contracts\OrganizationSettingQueries;

final readonly class OrganizationSettingQueryService implements OrganizationSettingQueries
{
    public function value(string $organizationId, string $key): mixed
    {
        $value = DB::table('organization_settings')
            ->where('organization_id', $organizationId)
            ->where('key', $key)
            ->value('value');

        if (!is_string($value)) {
            return $value;
        }

        return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
    }
}
