<?php
declare(strict_types=1);

namespace InnoShip\InnoShip\Test\Unit\Cron;

use InnoShip\InnoShip\Cron\Pudoinnoship;
use InnoShip\InnoShip\Model\Api\GetPudo;
use InnoShip\InnoShip\Model\Api\JsonArrayReader;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use PHPUnit\Framework\TestCase;

class PudoinnoshipTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/innoship_pudo_test_' . uniqid();
        mkdir($this->tmpDir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmpDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->tmpDir);
    }

    public function testFailedRequestDeletesNothing(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::never())->method('delete');
        $connection->expects(self::never())->method('insertOnDuplicate');

        $this->createCron(
            [
                'success' => false,
                'status_code' => 500,
                'status_message' => 'Server Error',
            ],
            '{"message":"Server Error"}',
            $connection,
        )->execute();

        self::assertSame([], glob($this->tmpDir . '/*'), 'the downloaded file is removed');
    }

    public function testSuccessWithoutLockersDeletesNothing(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::never())->method('delete');
        $connection->expects(self::never())->method('insertOnDuplicate');

        $this->createCron($this->ok(), '[]', $connection)->execute();
    }

    public function testImportStoresClosingHourAndRemovesMissingLockers(): void
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchCol')->willReturn(['10', '99']);
        $connection->expects(self::once())
            ->method('insertOnDuplicate')
            ->with(
                'innoship_pudo',
                self::callback(static function (array $rows): bool {
                    $row = $rows[0] ?? [];

                    return count($rows) === 1
                        && $row['pudo_id'] === 10
                        && $row['open_hours_mo_start'] === '08:00'
                        && $row['open_hours_mo_end'] === '20:00'
                        && $row['addressText'] === '';
                }),
            );
        $connection->expects(self::once())
            ->method('delete')
            ->with('innoship_pudo', ['pudo_id IN (?)' => [99]]);

        $this->createCron(
            $this->ok(),
            json_encode([
                [
                    'id' => 10,
                    'name' => 'Locker 10',
                    'schedule' => [
                        ['openingHour' => '08:00', 'closingHour' => '20:00'],
                    ],
                ],
            ]),
            $connection,
        )->execute();

        self::assertSame([], glob($this->tmpDir . '/*'), 'the downloaded file is removed');
    }

    public function testTruncatedListDeletesNothing(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::never())->method('delete');

        $this->expectException(\UnexpectedValueException::class);

        try {
            $this->createCron(
                $this->ok(),
                '[{"id":10,"name":"Locker 10"},{"id":11,"na',
                $connection,
            )->execute();
        } finally {
            self::assertSame([], glob($this->tmpDir . '/*'), 'the downloaded file is removed');
        }
    }

    private function ok(): array
    {
        return [
            'success' => true,
            'status_code' => 200,
            'status_message' => 'OK',
        ];
    }

    private function createCron(array $result, string $body, AdapterInterface $connection): Pudoinnoship
    {
        $getPudo = $this->createMock(GetPudo::class);
        $getPudo->method('downloadPudoList')->willReturnCallback(
            static function (string $path) use ($result, $body): array {
                file_put_contents($path, $body);

                return $result;
            },
        );

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);

        $tmpDir = $this->tmpDir;
        $directory = $this->createMock(WriteInterface::class);
        $directory->method('getAbsolutePath')->willReturnCallback(
            static function (string $path) use ($tmpDir): string {
                return $tmpDir . '/' . $path;
            },
        );
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('getDirectoryWrite')->willReturn($directory);

        return new Pudoinnoship($getPudo, $resource, $filesystem, new JsonArrayReader());
    }
}
