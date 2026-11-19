<?php
declare(strict_types=1);

namespace InnoShip\InnoShip\Test\Unit\Helper;

use InnoShip\InnoShip\Helper\ExternalSync;
use InnoShip\InnoShip\Model\Config;
use InnoShip\InnoShip\Model\ResourceModel\External as ExternalResource;
use Magento\Framework\DB\Adapter\AdapterInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ExternalSyncTest extends TestCase
{
    /**
     * @dataProvider badAnswers
     */
    #[DataProvider('badAnswers')]
    public function testBadAnswerKeepsStoredLocations(array $answer): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::never())->method('delete');
        $connection->expects(self::never())->method('truncateTable');
        $connection->expects(self::never())->method('beginTransaction');

        $sync = $this->createSync($answer, $connection);
        $sync->expects(self::never())->method('saveExternalLocation');

        self::assertFalse($sync->synchronizeExternalLocations()['success']);
    }

    public static function badAnswers(): array
    {
        return [
            'empty' => [[]],
            'invalid rows only' => [[['countryCode' => 'RO'], 'text']],
        ];
    }

    public function testValidAnswerReplacesLocationsInOneTransaction(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('delete')->with('innoship_external');
        $connection->expects(self::once())->method('commit');
        $connection->expects(self::never())->method('rollBack');

        $sync = $this->createSync(
            [
                ['externalLocationId' => 'Test_ENV', 'countryName' => 'Romania', 'countryCode' => 'RO'],
                ['countryCode' => 'RO'],
            ],
            $connection
        );
        $sync->expects(self::once())->method('saveExternalLocation')->with('Test_ENV', 'Romania', 'RO');

        $result = $sync->synchronizeExternalLocations();

        self::assertTrue($result['success']);
        self::assertSame(1, $result['synced']);
    }

    public function testNothingSavedRollsBack(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::once())->method('delete');
        $connection->expects(self::never())->method('commit');
        $connection->expects(self::once())->method('rollBack');

        $sync = $this->createSync([['externalLocationId' => 'A']], $connection);
        $sync->method('saveExternalLocation')->willThrowException(new \Exception('db down'));

        self::assertFalse($sync->synchronizeExternalLocations()['success']);
    }

    /**
     * @return ExternalSync&\PHPUnit\Framework\MockObject\MockObject
     */
    private function createSync(array $answer, AdapterInterface $connection): ExternalSync
    {
        $resource = $this->getMockBuilder(ExternalResource::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getConnection', 'getMainTable'])
            ->getMock();
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getMainTable')->willReturn('innoship_external');

        $sync = $this->getMockBuilder(ExternalSync::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getExternalIdFromInnoship', 'saveExternalLocation'])
            ->getMock();
        $sync->method('getExternalIdFromInnoship')->willReturn($answer);

        foreach ([
            'config' => $this->createMock(Config::class),
            'externalResource' => $resource,
            'logger' => $this->createMock(LoggerInterface::class),
        ] as $name => $value) {
            $property = new \ReflectionProperty(ExternalSync::class, $name);
            $property->setValue($sync, $value);
        }

        return $sync;
    }
}
