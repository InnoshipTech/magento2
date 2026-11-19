<?php
declare(strict_types=1);

namespace InnoShip\InnoShip\Test\Unit\Model;

use InnoShip\InnoShip\Model\CourierPriceStore;
use Magento\Checkout\Model\Session as CheckoutSession;
use PHPUnit\Framework\TestCase;

class CourierPriceStoreTest extends TestCase
{
    private CourierPriceStore $store;

    protected function setUp(): void
    {
        $data = [];
        $session = $this->getMockBuilder(CheckoutSession::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getData', '__call'])
            ->getMock();
        // setData() is magic (SessionManager::__call); addMethods() is gone in PHPUnit 12.
        $session->method('__call')->willReturnCallback(
            static function (string $method, array $args) use (&$data, $session) {
                if ($method === 'setData') {
                    $data[$args[0]] = $args[1];
                }

                return $session;
            }
        );
        $session->method('getData')->willReturnCallback(
            static function (string $key = '') use (&$data) {
                return $data[$key] ?? null;
            }
        );

        $this->store = new CourierPriceStore($session);
    }

    public function testQuotedPriceIsReturnedForTheSameQuote(): void
    {
        $this->store->save(5, [
            7 => ['name' => 'Cargus', 'price' => 19.9],
            8 => ['name' => 'Sameday', 'price' => null],
        ]);

        self::assertSame(19.9, $this->store->get(5, 7));
        self::assertNull($this->store->get(5, 8), 'courier listed without a price');
        self::assertNull($this->store->get(5, 9), 'courier never listed');
        self::assertNull($this->store->get(6, 7), 'another quote');
    }

    public function testNothingStored(): void
    {
        self::assertNull($this->store->get(5, 7));
        self::assertFalse($this->store->isSelectionFor(5, 7, $this->store->locationKey('RO', 1940, 'Iasi')));
    }

    public function testSelectionHoldsOnlyForTheAddressItWasPricedFor(): void
    {
        $this->store->save(5, [7 => ['name' => 'FAN', 'price' => 1.22]], $this->store->locationKey('RO', 1940, 'Iaşi'));
        $this->store->rememberSelection(5, 7);

        self::assertTrue($this->store->isSelectionFor(5, 7, $this->store->locationKey('ro', '1940', ' iași ')), 'ş/ș, case, spaces, id as string');
        self::assertFalse($this->store->isSelectionFor(5, 7, $this->store->locationKey('RO', 1940, 'Abrud')), 'locality changed');
        self::assertFalse($this->store->isSelectionFor(5, 7, $this->store->locationKey('BG', 1940, 'Iasi')), 'country changed');
        self::assertFalse($this->store->isSelectionFor(5, 8, $this->store->locationKey('RO', 1940, 'Iasi')), 'another courier');
        self::assertFalse($this->store->isSelectionFor(6, 7, $this->store->locationKey('RO', 1940, 'Iasi')), 'another quote');
    }

    /**
     * Ineu exists in Arad (1918) and Bihor (1920): order 000000150 was charged the Arad Cargus price (0) for Bihor.
     */
    public function testSameLocalityInAnotherCountyIsAnotherAddress(): void
    {
        $this->store->save(5, [1 => ['name' => 'Cargus', 'price' => 0.0]], $this->store->locationKey('RO', 1918, 'Ineu'));
        $this->store->rememberSelection(5, 1);

        self::assertFalse($this->store->isSelectionFor(5, 1, $this->store->locationKey('RO', 1920, 'Ineu')), 'county changed');
        self::assertFalse($this->store->isSelectionFor(5, 1, $this->store->locationKey('RO', null, 'Ineu')), 'no county sent');
        self::assertTrue($this->store->isSelectionFor(5, 1, $this->store->locationKey('RO', 1918, 'Ineu')));
    }

    public function testListForAnotherAddressAfterTheSelectionKeepsTheSelectionLocation(): void
    {
        $this->store->save(5, [7 => ['name' => 'FAN', 'price' => 1.22]], $this->store->locationKey('RO', 1940, 'Iasi'));
        $this->store->rememberSelection(5, 7);
        // the list re-requested with the old city (body region=Alba&city=Iasi) answered nothing
        $this->store->save(5, [], $this->store->locationKey('RO', 1902, 'Abrud'));

        self::assertTrue($this->store->isSelectionFor(5, 7, $this->store->locationKey('RO', 1940, 'Iasi')));
        self::assertFalse($this->store->isSelectionFor(5, 7, $this->store->locationKey('RO', 1902, 'Abrud')));
    }

    public function testListWithoutLocationNeverValidatesASelection(): void
    {
        $this->store->save(5, [7 => ['name' => 'FAN', 'price' => 1.22]]);
        $this->store->rememberSelection(5, 7);

        self::assertFalse($this->store->isSelectionFor(5, 7, $this->store->locationKey('', null, '')));
    }
}
