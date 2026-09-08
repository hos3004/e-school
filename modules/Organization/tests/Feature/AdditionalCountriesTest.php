<?php

declare(strict_types=1);

namespace Modules\Organization\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Organization\Database\Seeders\AdditionalCountriesSeeder;
use Modules\Organization\Database\Seeders\GeographySeeder;
use Modules\Organization\Domain\Contracts\GeographyQueries;
use Tests\TestCase;

final class AdditionalCountriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_world_catalogue_preserves_curated_countries_and_prioritizes_five_additions(): void
    {
        $this->seed(GeographySeeder::class);
        $queries = app(GeographyQueries::class);
        $countries = $queries->countries();
        self::assertCount(249, $countries);
        self::assertSame(['TR', 'DE', 'GB', 'FR', 'US'], array_map(static fn ($country): string => $country->iso2, array_slice($countries, 22, 5)));
        foreach (['TR' => 'تركيا', 'DE' => 'ألمانيا', 'GB' => 'المملكة المتحدة', 'FR' => 'فرنسا', 'US' => 'الولايات المتحدة', 'CA' => 'كندا'] as $iso2 => $name) {
            $country = $queries->findCountryByIso2($iso2);
            self::assertNotNull($country);
            self::assertSame($name, $country->name['ar']);
            $regions = $queries->regionsOf($country->id);
            self::assertCount(1, $regions);
            self::assertSame('UNSPECIFIED', $regions[0]->code);
            self::assertSame('المنطقة غير محددة', $regions[0]->name['ar']);
            self::assertTrue($queries->regionExistsIn($regions[0]->id, $country->id));
        }
        $turkey = $queries->findCountryByIso2('TR');
        self::assertNotNull($turkey);
        self::assertSame('+90', $turkey->phoneCode);
        self::assertContains('Europe/Istanbul', \DateTimeZone::listIdentifiers(\DateTimeZone::PER_COUNTRY, $turkey->iso2));
        $egypt = $queries->findCountryByIso2('EG');
        self::assertNotNull($egypt);
        self::assertCount(27, $queries->regionsOf($egypt->id));
    }

    public function test_additive_sync_is_idempotent_and_does_not_reactivate_or_rename_existing_countries(): void
    {
        $this->seed(GeographySeeder::class);
        DB::table('countries')->where('iso2', 'DE')->update(['is_active' => false, 'name' => json_encode(['ar' => 'اسم محفوظ', 'en' => 'Saved label', 'fr' => 'Nom conservé'], JSON_THROW_ON_ERROR)]);
        $countries = DB::table('countries')->orderBy('id')->get()->toJson();
        $regions = DB::table('regions')->orderBy('id')->get()->toJson();

        $this->seed(AdditionalCountriesSeeder::class);
        $this->seed(AdditionalCountriesSeeder::class);

        self::assertSame($countries, DB::table('countries')->orderBy('id')->get()->toJson());
        self::assertSame($regions, DB::table('regions')->orderBy('id')->get()->toJson());
    }
}
