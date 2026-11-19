<?php
declare(strict_types=1);

namespace InnoShip\InnoShip\Test\Unit\Controller\Adminhtml\Awb;

use InnoShip\InnoShip\Controller\Adminhtml\Awb\Save;
use InnoShip\InnoShip\Model\Config;
use Magento\Framework\Message\Manager;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Config as OrderConfig;
use PHPUnit\Framework\TestCase;

class SaveTest extends TestCase
{
    public function testCompletesProcessingOrderWhenEnabled(): void
    {
        [$order] = $this->completeAfterAwb(true, Order::STATE_PROCESSING, 1);

        self::assertSame(Order::STATE_COMPLETE, $order->getState());
        self::assertSame('complete', $order->getStatus());
    }

    public function testLeavesOrderAloneWhenDisabled(): void
    {
        [$order] = $this->completeAfterAwb(false, Order::STATE_PROCESSING, 0);

        self::assertSame(Order::STATE_PROCESSING, $order->getState());
    }

    public function testNeverReopensCanceledOrder(): void
    {
        [$order] = $this->completeAfterAwb(true, Order::STATE_CANCELED, 0);

        self::assertSame(Order::STATE_CANCELED, $order->getState());
    }

    public function testSaveFailureOnlyWarns(): void
    {
        $this->completeAfterAwb(true, Order::STATE_PROCESSING, 1, true);
    }

    /**
     * @return array{0: Order, 1: OrderRepositoryInterface}
     */
    private function completeAfterAwb(bool $enabled, string $state, int $saves, bool $saveThrows = false): array
    {
        $config = $this->createMock(Config::class);
        $config->method('isCompleteAfterAwb')->with(2)->willReturn($enabled);

        $orderConfig = $this->createMock(OrderConfig::class);
        $orderConfig->method('getStateDefaultStatus')->with(Order::STATE_COMPLETE)->willReturn('complete');

        $order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getStoreId', 'getConfig'])
            ->getMock();
        $order->method('getStoreId')->willReturn(2);
        $order->method('getConfig')->willReturn($orderConfig);
        $order->setState($state);

        $repository = $this->createMock(OrderRepositoryInterface::class);
        $save = $repository->expects(self::exactly($saves))->method('save')->with($order);
        if ($saveThrows) {
            $save->willThrowException(new \RuntimeException('db down'));
        }

        $manager = $this->createMock(Manager::class);
        $manager->expects(self::exactly($saveThrows ? 1 : 0))->method('addWarningMessage');

        $controller = (new \ReflectionClass(Save::class))->newInstanceWithoutConstructor();
        foreach (['config' => $config, 'orderRepositoryInterface' => $repository, 'manager' => $manager] as $name => $value) {
            $property = new \ReflectionProperty(Save::class, $name);
            $property->setValue($controller, $value);
        }

        $method = new \ReflectionMethod(Save::class, 'completeOrderAfterAwb');
        $method->invoke($controller, $order);

        return [$order, $repository];
    }
}
