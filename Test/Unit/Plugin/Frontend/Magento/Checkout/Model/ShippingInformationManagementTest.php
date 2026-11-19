<?php
declare(strict_types=1);

namespace InnoShip\InnoShip\Test\Unit\Plugin\Frontend\Magento\Checkout\Model;

use InnoShip\InnoShip\Model\CourierPriceStore;
use InnoShip\InnoShip\Model\PudoAddress;
use InnoShip\InnoShip\Plugin\Frontend\Magento\Checkout\Model\ShippingInformationManagement;
use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Checkout\Model\ShippingInformationManagement as Subject;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * #10: a courier price picked for one locality was still charged after the address moved to another.
 */
class ShippingInformationManagementTest extends TestCase
{
    private const QUOTE_ID = 5;

    private CourierPriceStore $store;

    private QuoteAddress $stored;

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

        // courier FAN (7) picked in Iasi at 1.22
        $this->store->save(self::QUOTE_ID, [7 => ['name' => 'FAN', 'price' => 1.22]], $this->store->locationKey('RO', 1940, 'Iasi'));
        $this->store->rememberSelection(self::QUOTE_ID, 7);
        $this->stored = $this->address('RO', 'Iasi');
        $this->stored->setData(PudoAddress::COURIER_ID_FIELD, 7);
        $this->stored->setData('innoship_shipping_price', 1.22);
    }

    public function testCityChangeDropsTheCourierAndItsPrice(): void
    {
        $incoming = $this->save('innoship', '1', $this->address('RO', 'Abrud'));

        self::assertTrue($incoming->hasData('innoship_shipping_price'), 'the key must be on the DTO to override the stored value');
        self::assertNull($incoming->getData('innoship_shipping_price'));
        self::assertNull($incoming->getData(PudoAddress::COURIER_ID_FIELD));
    }

    public function testSameLocalityKeepsTheCourier(): void
    {
        $incoming = $this->save('innoship', '1', $this->address('RO', 'Iaşi'));

        self::assertFalse($incoming->hasData('innoship_shipping_price'), 'the stored price stays');
        self::assertSame(7, $incoming->getData(PudoAddress::COURIER_ID_FIELD));
    }

    /**
     * Ineu, Arad priced (locker detour) and saved as Ineu, Bihor: order 000000150 was charged 0.
     */
    public function testCountyChangeWithTheSameCityDropsThePrice(): void
    {
        $incoming = $this->save('innoship', '1', $this->address('RO', 'Iasi', 1920));

        self::assertTrue($incoming->hasData('innoship_shipping_price'));
        self::assertNull($incoming->getData('innoship_shipping_price'));
        self::assertNull($incoming->getData(PudoAddress::COURIER_ID_FIELD));
    }

    public function testLeavingCurierDropsThePrice(): void
    {
        $incoming = $this->save('flatrate', 'flatrate', $this->address('RO', 'Iasi'));

        self::assertTrue($incoming->hasData('innoship_shipping_price'));
        self::assertNull($incoming->getData('innoship_shipping_price'));
        self::assertNull($incoming->getData(PudoAddress::COURIER_ID_FIELD));
    }

    public function testCurierWithoutStoredPriceIsUntouched(): void
    {
        $this->stored->setData('innoship_shipping_price', null);
        $incoming = $this->save('innoship', '1', $this->address('RO', 'Abrud'));

        self::assertFalse($incoming->hasData('innoship_shipping_price'));
        self::assertSame(7, $incoming->getData(PudoAddress::COURIER_ID_FIELD));
    }

    private function save(string $carrier, string $method, QuoteAddress $incoming): QuoteAddress
    {
        $quote = $this->createMock(Quote::class);
        $quote->method('isVirtual')->willReturn(false);
        $quote->method('getShippingAddress')->willReturn($this->stored);

        $repository = $this->createMock(CartRepositoryInterface::class);
        $repository->method('getActive')->willReturn($quote);

        $information = $this->createMock(ShippingInformationInterface::class);
        $information->method('getShippingAddress')->willReturn($incoming);
        $information->method('getShippingCarrierCode')->willReturn($carrier);
        $information->method('getShippingMethodCode')->willReturn($method);

        $plugin = new ShippingInformationManagement(
            $repository,
            $this->createMock(PudoAddress::class),
            $this->createMock(LoggerInterface::class),
            $this->store,
        );
        $plugin->beforeSaveAddressInformation($this->createMock(Subject::class), self::QUOTE_ID, $information);

        return $incoming;
    }

    private function address(string $countryId, string $city, int $regionId = 1940): QuoteAddress
    {
        $address = $this->getMockBuilder(QuoteAddress::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $address->setData('country_id', $countryId);
        $address->setData('region_id', $regionId);
        $address->setData('city', $city);

        return $address;
    }
}
