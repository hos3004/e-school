<?php

declare(strict_types=1);

namespace Modules\Organization\Domain\Contracts;

use Modules\Organization\Domain\ValueObjects\CountryData;
use Modules\Organization\Domain\ValueObjects\RegionData;

interface GeographyQueries
{
    /**
     * @return list<CountryData>
     */
    public function countries(bool $activeOnly = true): array;

    /**
     * @return list<RegionData>
     */
    public function regionsOf(string $countryId, bool $activeOnly = true): array;

    public function findCountryByIso2(string $iso2): ?CountryData;

    public function regionExistsIn(string $regionId, string $countryId): bool;
}
