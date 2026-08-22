<?php

declare(strict_types=1);

namespace Modules\Organization\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class GeographySeeder extends Seeder
{
    public function run(): void
    {
        /** @var array{countries: list<array{iso2: string, iso3: string, phone_code: string, name: array<string, string>, sort_order: int}>, regions: array<string, list<array{code: string, name: array<string, string>, sort_order: int}>>} $data */
        $data = require __DIR__.'/../data/geography.php';

        DB::transaction(function () use ($data): void {
            /** @var array<string, string> $countryMap */
            $countryMap = [];
            $now = now();

            foreach ($data['countries'] as $country) {
                $existing = DB::table('countries')->where('iso2', $country['iso2'])->value('id');
                $countryId = is_string($existing) ? $existing : (string) Str::ulid();
                $values = [
                    'id' => $countryId,
                    'iso3' => $country['iso3'],
                    'phone_code' => $country['phone_code'],
                    'name' => json_encode($country['name'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'is_active' => true,
                    'sort_order' => $country['sort_order'],
                    'updated_at' => $now,
                ];

                if ($existing === null) {
                    $values['created_at'] = $now;
                }

                DB::table('countries')->updateOrInsert(
                    ['iso2' => $country['iso2']],
                    $values,
                );

                $countryMap[$country['iso2']] = $countryId;
            }

            foreach ($data['regions'] as $iso2 => $regions) {
                $countryId = $countryMap[$iso2] ?? null;
                if ($countryId === null) {
                    continue;
                }

                foreach ($regions as $region) {
                    $existingRegion = DB::table('regions')
                        ->where('country_id', $countryId)
                        ->where('code', $region['code'])
                        ->value('id');
                    $regionId = is_string($existingRegion) ? $existingRegion : (string) Str::ulid();
                    $values = [
                        'id' => $regionId,
                        'name' => json_encode($region['name'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                        'is_active' => true,
                        'sort_order' => $region['sort_order'],
                        'updated_at' => $now,
                    ];

                    if ($existingRegion === null) {
                        $values['created_at'] = $now;
                    }

                    DB::table('regions')->updateOrInsert(
                        ['country_id' => $countryId, 'code' => $region['code']],
                        $values,
                    );
                }
            }
        });
    }
}
