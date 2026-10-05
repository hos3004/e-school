<?php

declare(strict_types=1);

namespace Modules\Organization\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Add missing reference countries without changing existing IDs, names or activation. */
final class AdditionalCountriesSeeder extends Seeder
{
    public function run(): void
    {
        /** @var array{countries: list<array{iso2: string, iso3: string, phone_code: string, name: array<string, string>, sort_order: int}>, regions: array<string, list<array{code: string, name: array<string, string>, sort_order: int}>>} $data */
        $data = require __DIR__.'/../data/additional-geography.php';

        DB::transaction(function () use ($data): void {
            $now = now();
            foreach ($data['countries'] as $country) {
                DB::table('countries')->insertOrIgnore([
                    'id' => (string) Str::ulid(),
                    'iso2' => $country['iso2'],
                    'iso3' => $country['iso3'],
                    'phone_code' => $country['phone_code'],
                    'name' => json_encode($country['name'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'is_active' => true,
                    'sort_order' => $country['sort_order'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $countryId = DB::table('countries')->where('iso2', $country['iso2'])->value('id');

                if (!is_string($countryId) || DB::table('regions')->where('country_id', $countryId)->exists()) {
                    continue;
                }

                $region = $data['regions'][$country['iso2']][0];
                DB::table('regions')->insertOrIgnore([
                    'id' => (string) Str::ulid(),
                    'country_id' => $countryId,
                    'code' => $region['code'],
                    'name' => json_encode($region['name'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'is_active' => true,
                    'sort_order' => $region['sort_order'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }
}
