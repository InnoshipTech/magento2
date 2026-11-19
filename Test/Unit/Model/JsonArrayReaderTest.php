<?php
declare(strict_types=1);

namespace InnoShip\InnoShip\Test\Unit\Model;

use InnoShip\InnoShip\Model\Api\JsonArrayReader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class JsonArrayReaderTest extends TestCase
{
    private const CHUNK_SIZE = 65536;

    private string $file;

    protected function setUp(): void
    {
        $this->file = tempnam(sys_get_temp_dir(), 'innoship_json_');
    }

    protected function tearDown(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }
    }

    public function testYieldsEachElementLikeJsonDecode(): void
    {
        $list = [
            ['id' => 1, 'name' => 'Brackets ] } [ { and "quotes" and \\ backslash', 'schedule' => [['a' => 1]]],
            ['id' => 2, 'name' => 'Ăîșț — unicode', 'nested' => ['x' => ['y' => [1, 2, 3]]]],
            [],
        ];
        file_put_contents($this->file, json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        self::assertSame($list, iterator_to_array((new JsonArrayReader())->read($this->file), false));
    }

    public function testSkipsScalarElements(): void
    {
        file_put_contents($this->file, '[1, "a]b", {"id":3}, null, true]');

        self::assertSame([['id' => 3]], iterator_to_array((new JsonArrayReader())->read($this->file), false));
    }

    public function testEmptyArrayYieldsNothing(): void
    {
        file_put_contents($this->file, " [ ] \n");

        self::assertSame([], iterator_to_array((new JsonArrayReader())->read($this->file), false));
    }

    /**
     * An escape (\") split over two read chunks, and an element spanning several chunks.
     */
    public function testEscapeAndElementAcrossChunkBoundary(): void
    {
        $prefix = '[{"id":1,"name":"';
        // the backslash lands on the last byte of the first chunk, the escaped quote on the first of the next
        $padding = str_repeat('x', self::CHUNK_SIZE - strlen($prefix) - 1);
        $json = $prefix . $padding . '\\"' . str_repeat('y', self::CHUNK_SIZE * 2) . '"},{"id":2,"name":"z"}]';
        file_put_contents($this->file, $json);

        $expected = json_decode($json, true);
        self::assertSame(self::CHUNK_SIZE - 1, strpos($json, '\\'));
        self::assertSame($expected, iterator_to_array((new JsonArrayReader())->read($this->file), false));
    }

    /**
     * @dataProvider invalidProvider
     */
    #[DataProvider('invalidProvider')]
    public function testRejectsIncompleteOrNonArrayAnswer(string $body): void
    {
        file_put_contents($this->file, $body);

        $this->expectException(\UnexpectedValueException::class);
        iterator_to_array((new JsonArrayReader())->read($this->file), false);
    }

    public static function invalidProvider(): array
    {
        return [
            'truncated inside an element' => ['[{"id":1},{"id":2,"na'],
            'truncated after an element' => ['[{"id":1},'],
            'object, not array' => ['{"message":"error"}'],
            'string' => ['"error"'],
            'empty file' => [''],
            'two arrays' => ['[][]'],
            'unbalanced close' => ['[]}'],
        ];
    }

    public function testCorruptElementThrows(): void
    {
        file_put_contents($this->file, '[{"id":1,}]');

        $this->expectException(\JsonException::class);
        iterator_to_array((new JsonArrayReader())->read($this->file), false);
    }

    /**
     * The real list: ~445k lockers. Decoding it at once peaked at ~3.5 GB; reading it must stay flat.
     */
    public function testMemoryStaysFlatOnAFullSizeList(): void
    {
        $locker = json_encode([
            'id' => 0,
            'name' => 'easybox Roua Residence',
            'fixedLocationTypeId' => 2,
            'courierId' => 6,
            'localityName' => 'Iasi',
            'countyName' => 'Iasi',
            'countryCode' => 'RO',
            'addressText' => 'Str. Moara De Vant Nr. 159',
            'postalCode' => '700376',
            'lat' => 47.17,
            'long' => 27.57,
            'schedule' => array_fill(0, 7, ['openingHour' => '00:00', 'closingHour' => '23:59']),
        ]);
        $handle = fopen($this->file, 'wb');
        fwrite($handle, '[');
        for ($i = 1; $i <= 445000; $i++) {
            fwrite($handle, ($i > 1 ? ',' : '') . str_replace('"id":0', '"id":' . $i, $locker));
        }
        fwrite($handle, ']');
        fclose($handle);

        $before = memory_get_usage();
        memory_reset_peak_usage();
        $count = 0;
        $lastId = 0;
        foreach ((new JsonArrayReader())->read($this->file) as $element) {
            $count++;
            $lastId = $element['id'];
        }
        $peakDelta = memory_get_peak_usage() - $before;

        self::assertSame(445000, $count);
        self::assertSame(445000, $lastId);
        self::assertGreaterThan(100 * 1024 * 1024, filesize($this->file));
        self::assertLessThan(4 * 1024 * 1024, $peakDelta, 'peak memory while reading: ' . $peakDelta);
    }
}
