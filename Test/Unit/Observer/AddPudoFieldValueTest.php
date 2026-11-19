<?php
declare(strict_types=1);

namespace InnoShip\InnoShip\Test\Unit\Observer;

use InnoShip\InnoShip\Observer\AddPudoFieldValue;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Magento\Quote\Model\QuoteFactory;
use Magento\Sales\Api\OrderAddressRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address as OrderAddress;
use PHPUnit\Framework\TestCase;

class AddPudoFieldValueTest extends TestCase
{
    public function testAddressSavedOnceAndOrderNeverSaved(): void
    {
        $orderAddress = $this->createOrderAddress(null, null);
        $order = $this->createOrder($orderAddress);
        $order->expects(self::never())->method('save');

        $repository = $this->createMock(OrderAddressRepositoryInterface::class);
        $repository->expects(self::once())->method('save')->with($orderAddress);

        $observer = new AddPudoFieldValue($this->createQuoteFactory('1234', '3'), $repository);
        $observer->execute($this->createEvent($order));
        $observer->execute($this->createEvent($order));

        self::assertSame(1234, $orderAddress->getData('innoship_pudo_id'));
        self::assertSame(3, $orderAddress->getData('innoship_courier_id'));
    }

    public function testNothingSavedWhenValuesAlreadyMatch(): void
    {
        $order = $this->createOrder($this->createOrderAddress('1234', null));

        $repository = $this->createMock(OrderAddressRepositoryInterface::class);
        $repository->expects(self::never())->method('save');

        (new AddPudoFieldValue($this->createQuoteFactory('1234', '0'), $repository))->execute($this->createEvent($order));
    }

    public function testStaleOrderLockerClearedWhenQuoteHasNone(): void
    {
        $orderAddress = $this->createOrderAddress('1234', null);

        $repository = $this->createMock(OrderAddressRepositoryInterface::class);
        $repository->expects(self::once())->method('save');

        (new AddPudoFieldValue($this->createQuoteFactory(null, null), $repository))
            ->execute($this->createEvent($this->createOrder($orderAddress)));

        self::assertNull($orderAddress->getData('innoship_pudo_id'));
    }

    private function createOrderAddress(?string $pudoId, ?string $courierId): OrderAddress
    {
        $address = $this->getMockBuilder(OrderAddress::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $address->setData('innoship_pudo_id', $pudoId);
        $address->setData('innoship_courier_id', $courierId);

        return $address;
    }

    private function createOrder(OrderAddress $shippingAddress): Order
    {
        $order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getShippingAddress', 'save'])
            ->getMock();
        $order->method('getShippingAddress')->willReturn($shippingAddress);
        $order->setData('quote_id', 55);

        return $order;
    }

    private function createQuoteFactory(?string $pudoId, ?string $courierId): QuoteFactory
    {
        $quoteAddress = $this->getMockBuilder(QuoteAddress::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $quoteAddress->setData('innoship_pudo_id', $pudoId);
        $quoteAddress->setData('innoship_courier_id', $courierId);

        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['load', 'getId', 'getShippingAddress'])
            ->getMock();
        $quote->method('load')->willReturnSelf();
        $quote->method('getId')->willReturn(55);
        $quote->method('getShippingAddress')->willReturn($quoteAddress);

        // QuoteFactory is generated code: dev/tests/unit/framework/autoload.php generates it when absent.
        $factory = $this->getMockBuilder(QuoteFactory::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['create'])
            ->getMock();
        $factory->method('create')->willReturn($quote);

        return $factory;
    }

    private function createEvent(Order $order): Observer
    {
        return new Observer(['event' => new Event(['order' => $order])]);
    }
}
