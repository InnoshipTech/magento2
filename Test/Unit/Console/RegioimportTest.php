<?php
declare(strict_types=1);

namespace InnoShip\InnoShip\Test\Unit\Console;

use InnoShip\InnoShip\Console\Regioimport;
use InnoShip\InnoShip\Model\CityPostcodeFiller;
use InnoShip\InnoShip\Model\Config;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\State;
use Magento\Framework\DB\Adapter\Pdo\Mysql;
use Magento\Framework\Filesystem\DirectoryList;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

class RegioimportTest extends TestCase
{
    public function testExistingRegionKeepsItsIdAndOnlyNewRegionsAreInserted(): void
    {
        $connection = $this->createMock(Mysql::class);
        $connection->method('fetchAll')->willReturn([
            ['region_id' => 302, 'code' => 'IS', 'default_name' => 'Iaşi'],
        ]);

        $queries = [];
        $connection->method('query')->willReturnCallback(static function (string $sql) use (&$queries) {
            $queries[] = $sql;
        });

        $updates = [];
        $connection->method('update')->willReturnCallback(static function ($table, array $data, array $where) use (&$updates) {
            $updates[] = [$table, $data, $where];

            return 1;
        });

        $inserts = [];
        $connection->method('insert')->willReturnCallback(static function ($table, array $data) use (&$inserts) {
            $inserts[] = [$table, $data];

            return 1;
        });
        $connection->method('lastInsertId')->willReturn('500');

        $cities = [];
        $connection->method('insertMultiple')->willReturnCallback(static function ($table, array $rows) use (&$cities) {
            $cities = array_merge($cities, $rows);

            return count($rows);
        });

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);

        $directoryList = $this->createMock(DirectoryList::class);
        $directoryList->method('getRoot')->willReturn(sys_get_temp_dir());

        $filler = $this->createMock(CityPostcodeFiller::class);
        $filler->method('execute')->willReturn(0);

        $command = $this->getMockBuilder(Regioimport::class)
            ->setConstructorArgs([
                $resource,
                $this->createMock(Config::class),
                $this->createMock(State::class),
                $directoryList,
                $filler,
            ])
            ->onlyMethods(['backupRegions', 'getAllCountryDetails'])
            ->getMock();
        $command->method('backupRegions')->willReturn(true);
        $command->method('getAllCountryDetails')->willReturn([
            'counties' => [
                [
                    'countyName' => 'Iasi',
                    'countyNameLocalized' => 'Iași',
                    'localities' => [['localityName' => 'Iasi', 'streets' => [['postalCode' => '700001']]]],
                ],
                [
                    'countyName' => 'Nou',
                    'countyNameLocalized' => 'Nou',
                    'localities' => [['localityName' => 'Satu Nou']],
                ],
            ],
        ]);

        $method = new \ReflectionMethod(Regioimport::class, 'importRegions');
        $method->invoke($command, 'RO', new BufferedOutput());

        foreach ($queries as $sql) {
            self::assertStringNotContainsString('DELETE FROM directory_country_region ', $sql . ' ');
            self::assertStringNotContainsString('DELETE FROM directory_country_region_name', $sql);
        }

        self::assertSame(
            [['directory_country_region', ['default_name' => 'Iași'], ['region_id = ?' => 302]]],
            $updates,
            'the existing county keeps region_id 302'
        );
        self::assertCount(1, $inserts, 'only the new county is inserted');
        self::assertSame('Nou', $inserts[0][1]['default_name']);

        self::assertSame(302, $cities[0]['regioId']);
        self::assertSame('IS', $cities[0]['regioCode'], 'cities of a kept region use its stored code');
        self::assertSame(500, $cities[1]['regioId']);
    }
}
