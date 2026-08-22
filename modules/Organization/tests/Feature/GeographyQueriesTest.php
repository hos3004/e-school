<?php

declare(strict_types=1);

namespace Modules\Organization\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Organization\Database\Seeders\GeographySeeder;
use Modules\Organization\Domain\Contracts\GeographyQueries;
use Modules\Organization\Domain\ValueObjects\CountryData;
use Modules\Organization\Domain\ValueObjects\RegionData;
use Tests\TestCase;

final class GeographyQueriesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GeographySeeder::class);
    }

    public function test_geography_queries_returns_dtos_for_countries_and_regions(): void
    {
        /** @var GeographyQueries $queries */
        $queries = app(GeographyQueries::class);

        $countries = $queries->countries();
        $this->assertCount(22, $countries);
        $this->assertInstanceOf(CountryData::class, $countries[0]);

        $egypt = $queries->findCountryByIso2('eg');
        $this->assertNotNull($egypt);
        $this->assertSame('EG', $egypt->iso2);

        $regions = $queries->regionsOf($egypt->id);
        $this->assertCount(27, $regions);
        $this->assertInstanceOf(RegionData::class, $regions[0]);

        $cairoRegion = $regions[0];
        $this->assertTrue($queries->regionExistsIn($cairoRegion->id, $egypt->id));
        $this->assertFalse($queries->regionExistsIn($cairoRegion->id, 'invalid-country-id'));
    }
}
