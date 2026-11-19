<?php
declare(strict_types=1);

namespace InnoShip\InnoShip\Test\Unit\Plugin\Model;

use InnoShip\InnoShip\Model\Config;
use InnoShip\InnoShip\Model\PaymentRestriction;
use InnoShip\InnoShip\Observer\RestrictPaymentMethod;
use InnoShip\InnoShip\Plugin\Model\PaymentMethodAvailable;
use Magento\Framework\DataObject;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Payment\Model\MethodInterface;
use Magento\Payment\Model\MethodList;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class PaymentMethodAvailableTest extends TestCase
{
    public function testEmptyRestrictionKeepsEveryMethod(): void
    {
        self::assertSame(['checkmo', 'cashondelivery'], $this->filterMethods('', 'innoshipcargusgo_innoshipcargusgo_1'));
    }

    public function testRestrictionLimitsLockerMethods(): void
    {
        self::assertSame(['cashondelivery'], $this->filterMethods('cashondelivery', 'innoshipcargusgo_innoshipcargusgo_1'));
    }

    public function testCourierIsNotRestrictedByLockerRestriction(): void
    {
        self::assertSame(['checkmo', 'cashondelivery'], $this->filterMethods('cashondelivery', 'innoship_1'));
    }

    public function testEmptyCourierRestrictionKeepsEveryMethod(): void
    {
        self::assertSame(['checkmo', 'cashondelivery'], $this->filterMethods('cashondelivery', 'innoship_1', ''));
    }

    public function testCourierRestrictionLimitsCourierMethods(): void
    {
        self::assertSame(['cashondelivery'], $this->filterMethods('', 'innoship_1', 'cashondelivery, banktransfer'));
    }

    public function testCourierRestrictionDoesNotTouchLocker(): void
    {
        self::assertSame(['checkmo', 'cashondelivery'], $this->filterMethods('', 'innoshipcargusgo_innoshipcargusgo_1', 'cashondelivery'));
    }

    public function testOtherCarrierIsNotRestricted(): void
    {
        self::assertSame(['checkmo', 'cashondelivery'], $this->filterMethods('cashondelivery', 'flatrate_flatrate', 'cashondelivery'));
    }

    public function testObserverRefusesPostedMethodOutsideLockerRestriction(): void
    {
        self::assertFalse($this->observe('banktransfer', 'innoshipcargusgo_innoshipcargusgo_1', 'cashondelivery'));
        self::assertTrue($this->observe('banktransfer', 'innoshipcargusgo_innoshipcargusgo_1', 'banktransfer'));
    }

    public function testObserverRefusesPostedMethodOutsideCourierRestriction(): void
    {
        self::assertFalse($this->observe('', 'innoship_1', 'cashondelivery', 'banktransfer'));
        self::assertTrue($this->observe('', 'innoship_1', 'banktransfer', 'banktransfer'));
    }

    public function testObserverLeavesUnrestrictedAndOtherCarriersAlone(): void
    {
        self::assertTrue($this->observe('', 'innoshipcargusgo_innoshipcargusgo_1', 'cashondelivery'));
        self::assertTrue($this->observe('banktransfer', 'flatrate_flatrate', 'cashondelivery', 'banktransfer'));
    }

    public function testObserverNeverReEnablesAnUnavailableMethod(): void
    {
        self::assertFalse($this->observe('banktransfer', 'innoshipcargusgo_innoshipcargusgo_1', 'banktransfer', null, false));
    }

    /**
     * @return string[]
     */
    private function filterMethods(string $restriction, string $shippingMethod, ?string $courierRestriction = null): array
    {
        $methods = [];
        foreach (['checkmo', 'cashondelivery'] as $code) {
            $methods[] = $this->method($code);
        }

        $plugin = new PaymentMethodAvailable($this->restriction($restriction, $courierRestriction));
        $result = $plugin->afterGetAvailableMethods(
            $this->createMock(MethodList::class),
            $methods,
            $this->quote($shippingMethod)
        );

        return array_values(array_map(static fn ($method) => $method->getCode(), $result));
    }

    private function observe(
        string $restriction,
        string $shippingMethod,
        string $methodCode,
        ?string $courierRestriction = null,
        bool $availableBefore = true
    ): bool {
        $result = new DataObject(['is_available' => $availableBefore]);
        $event = new Event([
            'result' => $result,
            'method_instance' => $this->method($methodCode),
            'quote' => $this->quote($shippingMethod),
        ]);

        (new RestrictPaymentMethod($this->restriction($restriction, $courierRestriction)))
            ->execute(new Observer(['event' => $event]));

        return (bool) $result->getData('is_available');
    }

    private function restriction(string $restriction, ?string $courierRestriction): PaymentRestriction
    {
        $config = $this->createMock(Config::class);
        $config->method('getPaymentRestriction')->with(3)->willReturn($restriction);
        $config->method('getPaymentRestrictionCourier')->with(3)->willReturn($courierRestriction);

        return new PaymentRestriction($config, $this->createMock(StoreManagerInterface::class));
    }

    private function quote(string $shippingMethod): Quote
    {
        $address = $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getShippingMethod'])
            ->getMock();
        $address->method('getShippingMethod')->willReturn($shippingMethod);

        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getShippingAddress', 'getStoreId'])
            ->getMock();
        $quote->method('getShippingAddress')->willReturn($address);
        $quote->method('getStoreId')->willReturn(3);

        return $quote;
    }

    private function method(string $code): MethodInterface
    {
        $method = $this->createMock(MethodInterface::class);
        $method->method('getCode')->willReturn($code);

        return $method;
    }
}
