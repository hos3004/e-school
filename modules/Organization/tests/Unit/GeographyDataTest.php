<?php

declare(strict_types=1);

namespace Modules\Organization\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class GeographyDataTest extends TestCase
{
    public function test_reference_file_contains_the_arab_countries_and_all_egyptian_regions(): void
    {
        /** @var array{countries: list<array{iso2: string, iso3: string, phone_code: string, name: array<string, string>, sort_order: int}>, regions: array<string, list<array{code: string, name: array<string, string>, sort_order: int}>>} $data */
        $data = require __DIR__.'/../../database/data/geography.php';

        $iso2Codes = array_column($data['countries'], 'iso2');
        $expectedIso2Codes = [
            'AE', 'BH', 'DJ', 'DZ', 'EG', 'IQ', 'JO', 'KM', 'KW', 'LB', 'LY',
            'MA', 'MR', 'OM', 'PS', 'QA', 'SA', 'SD', 'SO', 'SY', 'TN', 'YE',
        ];

        sort($iso2Codes);
        sort($expectedIso2Codes);

        self::assertSame($expectedIso2Codes, $iso2Codes);
        self::assertCount(22, array_unique($iso2Codes));
        self::assertArrayHasKey('EG', $data['regions']);
        self::assertCount(27, $data['regions']['EG']);
        self::assertCount(27, array_unique(array_column($data['regions']['EG'], 'code')));

        foreach ($data['countries'] as $country) {
            self::assertSame(['ar', 'en', 'fr'], array_keys($country['name']));
            self::assertSame(2, strlen($country['iso2']));
            self::assertSame(3, strlen($country['iso3']));
            self::assertNotSame('', $country['phone_code']);
        }

        foreach ($data['regions']['EG'] as $region) {
            self::assertSame(['ar', 'en', 'fr'], array_keys($region['name']));
        }
    }
}
