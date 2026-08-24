<?php

declare(strict_types=1);

namespace Modules\Organization\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class GeographyDataTest extends TestCase
{
    public function test_reference_file_contains_all_arab_countries_and_their_administrative_divisions(): void
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
        $expectedRegionCounts = [
            'AE' => 7, 'BH' => 4, 'DJ' => 6, 'DZ' => 58, 'EG' => 27, 'IQ' => 19,
            'JO' => 12, 'KM' => 3, 'KW' => 6, 'LB' => 8, 'LY' => 22, 'MA' => 12,
            'MR' => 15, 'OM' => 11, 'PS' => 16, 'QA' => 8, 'SA' => 13, 'SD' => 18,
            'SO' => 18, 'SY' => 14, 'TN' => 24, 'YE' => 22,
        ];

        $regionIso2Codes = array_keys($data['regions']);
        sort($regionIso2Codes);
        self::assertSame($expectedIso2Codes, $regionIso2Codes);

        foreach ($expectedRegionCounts as $iso2 => $count) {
            self::assertCount($count, $data['regions'][$iso2]);
        }

        self::assertSame(343, array_sum($expectedRegionCounts));

        foreach ($data['countries'] as $country) {
            self::assertSame(['ar', 'en', 'fr'], array_keys($country['name']));
            self::assertSame(2, strlen($country['iso2']));
            self::assertSame(3, strlen($country['iso3']));
            self::assertNotSame('', $country['phone_code']);
        }

        foreach ($data['regions'] as $regions) {
            self::assertCount(count($regions), array_unique(array_column($regions, 'code')));

            foreach ($regions as $region) {
                self::assertSame(['ar', 'en', 'fr'], array_keys($region['name']));
            }
        }
    }
}
