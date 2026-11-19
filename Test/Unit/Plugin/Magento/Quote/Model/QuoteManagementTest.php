<?php
declare(strict_types=1);

namespace InnoShip\InnoShip\Test\Unit\Plugin\Magento\Quote\Model;

use InnoShip\InnoShip\Model\PudoAddress;
use InnoShip\InnoShip\Plugin\Magento\Quote\Model\QuoteManagement as Plugin;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\QuoteManagement;
use PHPUnit\Framework\TestCase;

class QuoteManagementTest extends TestCase
{
    public function testLockerAddressOfCustomerIsKeptOutOfAddressBook(): void
    {
        $shipping = $this->createAddress(PudoAddress::PUDO_SHIPPING_METHOD, null);
        $shipping->expects(self::once())->method('setCustomerId')->with(42);
        $shipping->expects(self::once())->method('setSaveInAddressBook')->with(0);

        $this->runPlugin($this->createQuote(42, false, $shipping));
    }

    public function testCourierAddressIsLeftAlone(): void
    {
        $shipping = $this->createAddress('innoship_1', null);
        $shipping->expects(self::never())->method('setCustomerId');
        $shipping->expects(self::never())->method('setSaveInAddressBook');

        $this->runPlugin($this->createQuote(42, false, $shipping));
    }

    public function testGuestIsLeftAlone(): void
    {
        $shipping = $this->createAddress(PudoAddress::PUDO_SHIPPING_METHOD, null);
        $shipping->expects(self::never())->method('setCustomerId');

        $this->runPlugin($this->createQuote(null, true, $shipping));
    }

    public function testLockerOrderWithoutLockerIsRefused(): void
    {
        $quote = $this->createQuote(null, true, $this->createAddress(PudoAddress::PUDO_SHIPPING_METHOD, null, null));

        $this->expectException(LocalizedException::class);
        $this->createPlugin($quote)->beforePlaceOrder($this->createMock(QuoteManagement::class), 7);
    }

    public function testLockerOrderWithZeroLockerIsRefused(): void
    {
        $quote = $this->createQuote(42, false, $this->createAddress(PudoAddress::PUDO_SHIPPING_METHOD, null, '0'));

        $this->expectException(LocalizedException::class);
        $this->createPlugin($quote)->beforePlaceOrder($this->createMock(QuoteManagement::class), 7);
    }

    public function testLockerOrderWithLockerPasses(): void
    {
        $quote = $this->createQuote(null, true, $this->createAddress(PudoAddress::PUDO_SHIPPING_METHOD, null, '1234'));

        self::assertNull($this->createPlugin($quote)->beforePlaceOrder($this->createMock(QuoteManagement::class), 7));
    }

    public function testCourierOrderWithoutLockerPasses(): void
    {
        $quote = $this->createQuote(null, true, $this->createAddress('innoship_1', null, null));

        self::assertNull($this->createPlugin($quote)->beforePlaceOrder($this->createMock(QuoteManagement::class), 7));
    }

    public function testLockerOrderWithLockerAddressAsBillingIsRefused(): void
    {
        // order 000000231: "same as shipping" ticked on Locker copied the locker into billing
        $street = 'Barati ALEXANDRU PIRU NR 109 Nr. 0 Cod postal. 607316';
        $shipping = $this->createAddress(PudoAddress::PUDO_SHIPPING_METHOD, null, '64917');
        $shipping->addData(['street' => $street, 'city' => 'Barati', 'postcode' => '607316', 'country_id' => 'RO']);
        $billing = $this->createBilling([$street], 'Barati', '607316', 'RO');
        $quote = $this->createQuote(null, true, $shipping, $billing);

        $this->expectException(LocalizedException::class);
        $this->createPlugin($quote)->beforePlaceOrder($this->createMock(QuoteManagement::class), 7);
    }

    public function testLockerAddressAsBillingIsRefusedWhateverCaseAndSpacing(): void
    {
        $shipping = $this->createAddress(PudoAddress::PUDO_SHIPPING_METHOD, null, '64917');
        $shipping->addData(['street' => 'Str. Piru  109', 'city' => 'Barati', 'postcode' => '607316', 'country_id' => 'RO']);
        $billing = $this->createBilling(['str. piru 109 '], ' BARATI', '607316', 'RO');
        $quote = $this->createQuote(42, false, $shipping, $billing);

        $this->expectException(LocalizedException::class);
        $this->createPlugin($quote)->beforePlaceOrder($this->createMock(QuoteManagement::class), 7);
    }

    public function testLockerOrderWithOwnBillingAddressPasses(): void
    {
        $shipping = $this->createAddress(PudoAddress::PUDO_SHIPPING_METHOD, null, '64917');
        $shipping->addData(['street' => 'Str. Piru 109', 'city' => 'Barati', 'postcode' => '607316', 'country_id' => 'RO']);
        $billing = $this->createBilling(['Str. Lunga 5'], 'Bacau', '600001', 'RO');
        $quote = $this->createQuote(null, true, $shipping, $billing);

        self::assertNull($this->createPlugin($quote)->beforePlaceOrder($this->createMock(QuoteManagement::class), 7));
    }

    public function testCourierOrderWithBillingSameAsShippingPasses(): void
    {
        $shipping = $this->createAddress('innoship_1', null, null);
        $shipping->addData(['street' => 'Str. Lunga 5', 'city' => 'Bacau', 'postcode' => '600001', 'country_id' => 'RO']);
        $billing = $this->createBilling(['Str. Lunga 5'], 'Bacau', '600001', 'RO');
        $quote = $this->createQuote(null, true, $shipping, $billing);

        self::assertNull($this->createPlugin($quote)->beforePlaceOrder($this->createMock(QuoteManagement::class), 7));
    }

    private function createBilling(array $street, string $city, string $postcode, string $countryId): Address
    {
        $billing = $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getShippingMethod'])
            ->getMock();
        $billing->setData(['street' => $street, 'city' => $city, 'postcode' => $postcode, 'country_id' => $countryId]);

        return $billing;
    }

    private function runPlugin(Quote $quote): void
    {
        $result = $this->createPlugin($quote)->beforeSubmit($this->createMock(QuoteManagement::class), $quote, ['x' => 1]);

        self::assertSame([$quote, ['x' => 1]], $result);
    }

    private function createPlugin(Quote $quote): Plugin
    {
        $repository = $this->createMock(CartRepositoryInterface::class);
        $repository->method('getActive')->with(7)->willReturn($quote);

        return new Plugin($repository);
    }

    private function createAddress(string $method, ?int $customerAddressId, ?string $pudoId = null): Address
    {
        $address = $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getShippingMethod',
                'getCustomerAddressId',
                'setCustomerId',
                'setSaveInAddressBook',
            ])
            ->getMock();
        $address->method('getShippingMethod')->willReturn($method);
        $address->method('getCustomerAddressId')->willReturn($customerAddressId);
        // Magic getter: DataObject::__call reads the stored value (addMethods() is gone in PHPUnit 12).
        $address->setData('innoship_pudo_id', $pudoId);

        return $address;
    }

    private function createQuote(?int $customerId, bool $isGuest, Address $shipping, ?Address $billing = null): Quote
    {
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'isVirtual',
                'getShippingAddress',
                'getBillingAddress',
                'getCustomerIsGuest',
            ])
            ->getMock();
        $quote->method('isVirtual')->willReturn(false);
        $quote->method('getCustomerIsGuest')->willReturn($isGuest);
        $quote->setData('customer_id', $customerId);
        $quote->method('getShippingAddress')->willReturn($shipping);
        $quote->method('getBillingAddress')->willReturn($billing ?? $this->createBilling([], '', '', ''));

        return $quote;
    }
}
