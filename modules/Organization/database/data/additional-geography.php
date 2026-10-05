<?php

declare(strict_types=1);

/** @var list<array{iso2: string, iso3: string, phone_code: string, name: array<string, string>, sort_order: int}> $countries */
$countries = json_decode(file_get_contents(__DIR__.'/additional-countries.json') ?: '[]', true, 512, JSON_THROW_ON_ERROR);
$regions = [];

foreach ($countries as $country) {
    // A missing subdivision is explicit; this is not an invented province.
    $regions[$country['iso2']] = [[
        'code' => 'UNSPECIFIED',
        'name' => ['ar' => 'المنطقة غير محددة', 'en' => 'Region not specified', 'fr' => 'Région non précisée'],
        'sort_order' => 0,
    ]];
}

return ['countries' => $countries, 'regions' => $regions];
