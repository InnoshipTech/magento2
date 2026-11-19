<?php
declare(strict_types=1);

namespace InnoShip\InnoShip\Test\Unit\Plugin\Magento\Checkout\Model;

use InnoShip\InnoShip\Model\PudoAddress;
use InnoShip\InnoShip\Plugin\Magento\Checkout\Model\TotalsInformationManagement;
use Magento\Checkout\Api\Data\TotalsInformationInterface;
use Magento\Checkout\Model\TotalsInformationManagement as Subject;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use PHPUnit\Framework\TestCase;

/**
 * Order 000000347: a totals refresh after shipping-information posted the customer's saved card
 * (Iasi) and left the locker street with the card's city, county and postcode.
 */
class TotalsInformationManagementTest extends TestCase
{
    private const PUDO_ID = 12405;

    private QuoteAddress $stored;

    protected function setUp(): void
    {
        $this->stored = $this->address();
        $this->stored->setData('shipping_method', PudoAddress::PUDO_SHIPPING_METHOD);
        $this->stored->setData(PudoAddress::PUDO_ID_FIELD, self::PUDO_ID);
    }

    public function testLockerMethodGetsTheStoredLockerApplied(): void
    {
        $posted = $this->savedCard();

        self::assertTrue($this->calculate('innoshipcargusgo', 'innoshipcargusgo_1', $posted));
    }

    public function testNoPostedMethodOnLockerQuoteGetsTheLockerApplied(): void
    {
        self::assertTrue($this->calculate(null, null, $this->savedCard()));
    }

    public function testLeavingLockerIsLeftAlone(): void
    {
        self::assertFalse($this->calculate('innoship', '1', $this->savedCard()));
    }

    public function testNoPostedMethodOnCourierQuoteIsLeftAlone(): void
    {
        $this->stored->setData('shipping_method', 'innoship_1');

        self::assertFalse($this->calculate(null, null, $this->savedCard()));
    }

    public function testLockerMethodWithoutStoredLockerIsLeftAlone(): void
    {
        $this->stored->setData(PudoAddress::PUDO_ID_FIELD, null);

        self::assertFalse($this->calculate('innoshipcargusgo', 'innoshipcargusgo_1', $this->savedCard()));
    }

    /**
     * @return bool True when the locker was applied to the posted address.
     */
    private function calculate(?string $carrier, ?string $method, QuoteAddress $posted): bool
    {
        $quote = $this->createMock(Quote::class);
        $quote->method('isVirtual')->willReturn(false);
        $quote->method('getShippingAddress')->willReturn($this->stored);

        $repository = $this->createMock(CartRepositoryInterface::class);
        $repository->method('get')->willReturn($quote);

        $information = $this->createMock(TotalsInformationInterface::class);
        $information->method('getAddress')->willReturn($posted);
        $information->method('getShippingCarrierCode')->willReturn($carrier);
        $information->method('getShippingMethodCode')->willReturn($method);

        $applied = false;
        $pudoAddress = $this->createMock(PudoAddress::class);
        $pudoAddress->method('applyToAddress')->willReturnCallback(
            static function (QuoteAddress $address, int $pudoId) use ($posted, &$applied): bool {
                self::assertSame($posted, $address);
                self::assertSame(self::PUDO_ID, $pudoId);
                $applied = true;

                return true;
            }
        );

        $plugin = new TotalsInformationManagement($repository, $pudoAddress);
        self::assertNull($plugin->beforeCalculate($this->createMock(Subject::class), 2322, $information));

        return $applied;
    }

    private function savedCard(): QuoteAddress
    {
        $address = $this->address();
        $address->setData('city', 'Iasi');
        $address->setData('region_id', 1941);
        $address->setData('postcode', '700376');

        return $address;
    }

    private function address(): QuoteAddress
    {
        return $this->getMockBuilder(QuoteAddress::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
    }
}
