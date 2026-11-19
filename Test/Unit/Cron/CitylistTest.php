<?php
declare(strict_types=1);

namespace InnoShip\InnoShip\Test\Unit\Cron;

use InnoShip\InnoShip\Cron\Citylist;
use PHPUnit\Framework\TestCase;

class CitylistTest extends TestCase
{
    private const LOCALITY = [
        'localityName' => 'Tarpiu',
        'streets' => [
            [
                'postalCode' => '427345',
            ],
        ],
    ];

    public function testAcceptsRegionCodeLongerThanTenCharacters(): void
    {
        self::assertTrue($this->validate(self::LOCALITY, 'BistritaNasaud'));
        self::assertTrue($this->validate(self::LOCALITY, 'TomaszowMazowieckiTomaszowski'));
    }

    public function testRejectsRegionCodeWiderThanTheDirectoryColumn(): void
    {
        self::assertFalse($this->validate(self::LOCALITY, str_repeat('A', 33)));
    }

    public function testRejectsLocalityWithoutPostcode(): void
    {
        self::assertFalse($this->validate(['localityName' => 'Tarpiu', 'streets' => []], 'BN'));
    }

    public function testKeepsLowestStreetPostcodeWhateverTheApiOrder(): void
    {
        $streets = [
            ['postalCode' => '400496'],
            ['postalCode' => ''],
            ['postalCode' => '400001'],
            ['postalCode' => '400120'],
        ];
        $job = (new \ReflectionClass(Citylist::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(Citylist::class, 'lowestPostalCode');

        self::assertSame('400001', $method->invoke($job, ['streets' => $streets]));
        self::assertSame('400001', $method->invoke($job, ['streets' => array_reverse($streets)]));
        self::assertSame('', $method->invoke($job, ['streets' => [['postalCode' => '']]]));

        // A stray code outside the locality's usual prefix never wins (Bistrita: 042020 among 420xxx).
        $bistrita = [
            ['postalCode' => '420008'],
            ['postalCode' => '042020'],
            ['postalCode' => '420115'],
        ];
        self::assertSame('420008', $method->invoke($job, ['streets' => $bistrita]));
    }

    public function testMatchesLatvianMunicipalityWithoutNovadsSuffix(): void
    {
        // Shape and names of the INNOSHIP LV answer (2026-11-11) and of Magento's LV regions.
        $counties = [
            ['countyNameLocalized' => 'Daugavpils', 'localities' => ['city']],
            ['countyNameLocalized' => 'Jelgava', 'localities' => ['city']],
            ['countyNameLocalized' => 'Jelgavas novads', 'localities' => ['municipality']],
            ['countyNameLocalized' => 'Aizkraukles', 'localities' => ['aizkraukle']],
            ['countyNameLocalized' => 'Ādažu', 'localities' => ['adazi']],
        ];
        $regions = [
            'daugavpils',
            'daugavpils novads',
            'jelgava',
            'jelgavas novads',
            'aizkraukles novads',
            'ādažu novads',
        ];
        $job = (new \ReflectionClass(Citylist::class))->newInstanceWithoutConstructor();

        self::assertSame(['aizkraukle'], $job->matchCounty($counties, 'Aizkraukles novads', $regions)['localities']);
        self::assertSame(['adazi'], $job->matchCounty($counties, 'Ādažu novads', $regions)['localities']);
        // Exact names keep winning.
        self::assertSame(['city'], $job->matchCounty($counties, 'Jelgava', $regions)['localities']);
        self::assertSame(['municipality'], $job->matchCounty($counties, 'Jelgavas novads', $regions)['localities']);
        // The city 'Daugavpils' never fills the region 'Daugavpils novads'.
        self::assertNull($job->matchCounty($counties, 'Daugavpils novads', $regions));
        // Romanian counties are unaffected.
        self::assertNull($job->matchCounty($counties, 'Cluj', $regions));
    }

    private function validate(array $locality, string $regionCode): bool
    {
        $job = (new \ReflectionClass(Citylist::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(Citylist::class, 'validateLocalityData');

        return $method->invoke($job, $locality, 'RO', 5, $regionCode);
    }
}
