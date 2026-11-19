<?php
declare(strict_types=1);

namespace InnoShip\InnoShip\Test\Unit\Model;

use InnoShip\InnoShip\Model\CityPostcodeFiller;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\TestCase;

class CityPostcodeFillerTest extends TestCase
{
    public function testFillsZeroPostcodeFromPudoAndLeavesUnmatchedCity(): void
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('join')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $select->method('group')->willReturnSelf();

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('isTableExists')->willReturn(true);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchAll')->willReturnOnConsecutiveCalls(
            // innoship_citys rows with postcode 0
            [
                ['id' => 1, 'country' => 'RO', 'localitate' => 'Iași', 'default_name' => 'Iași'],
                ['id' => 2, 'country' => 'RO', 'localitate' => 'Sat Fără Locker', 'default_name' => 'Vaslui'],
            ],
            // innoship_pudo MIN(postalCode) per locality, accents differ from the city row
            [
                ['countryCode' => 'RO', 'countyName' => 'Iasi', 'localityName' => 'Iasi', 'postalCode' => '700259'],
                ['countryCode' => 'RO', 'countyName' => 'IASI', 'localityName' => 'IAȘI', 'postalCode' => '700001'],
            ],
        );
        $connection->expects(self::once())
            ->method('update')
            ->with(
                'innoship_citys',
                ['codPostal' => '700001'],
                ['id = ?' => 1],
            )
            ->willReturn(1);

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);

        self::assertSame(1, (new CityPostcodeFiller($resource))->execute());
    }

    public function testNothingToFill(): void
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('join')->willReturnSelf();
        $select->method('where')->willReturnSelf();

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('isTableExists')->willReturn(true);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchAll')->willReturn([]);
        $connection->expects(self::never())->method('update');

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);

        self::assertSame(0, (new CityPostcodeFiller($resource))->execute());
    }
}
