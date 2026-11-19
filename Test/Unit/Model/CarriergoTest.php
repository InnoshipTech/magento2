<?php
declare(strict_types=1);

namespace InnoShip\InnoShip\Test\Unit\Model;

use InnoShip\InnoShip\Model\Carriergo;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\DataObject;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\Method;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Rate\ResultFactory;
use PHPUnit\Framework\TestCase;

class CarriergoTest extends TestCase
{
    public function testEmptyConfigKeepsLocker(): void
    {
        self::assertSame(1, $this->countRates('', ['A' => '0', 'B' => '1']));
    }

    public function testAllProductsAllowedKeepsLocker(): void
    {
        self::assertSame(1, $this->countRates('allow_locker', ['A' => '1', 'B' => '1']));
    }

    public function testOneProductNotAllowedHidesLocker(): void
    {
        self::assertSame(0, $this->countRates('allow_locker', ['A' => '1', 'B' => '0']));
    }

    public function testUnsetAttributeKeepsLocker(): void
    {
        self::assertSame(1, $this->countRates('allow_locker', ['A' => null, 'B' => '1']));
    }

    public function testSkuMissingFromCatalogIsSkipped(): void
    {
        self::assertSame(1, $this->countRates('allow_locker', ['A' => '1'], ['A', 'GONE']));
    }

    /**
     * @param string $productForLocker
     * @param array<string, string|null> $attributeBySku products the catalog returns
     * @param string[]|null $cartSkus
     */
    private function countRates(string $productForLocker, array $attributeBySku, ?array $cartSkus = null): int
    {
        $config = [
            'check_if_available' => '1',
            'lungime' => 'len',
            'latime' => 'wid',
            'inaltime' => 'hei',
            'product_for_locker' => $productForLocker,
        ];

        $carrier = $this->getMockBuilder(Carriergo::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getConfigData', 'getMethodPrice', 'getCountrySpecificPudoFee'])
            ->getMock();
        $carrier->method('getConfigData')->willReturnCallback(
            static fn ($field) => $config[$field] ?? null
        );
        $carrier->method('getMethodPrice')->willReturn(20.0);
        $carrier->method('getCountrySpecificPudoFee')->willReturn(null);

        $products = [];
        foreach ($attributeBySku as $sku => $value) {
            $product = $this->getMockBuilder(Product::class)->disableOriginalConstructor()->onlyMethods(['getSku'])->getMock();
            $product->method('getSku')->willReturn($sku);
            $product->setData(['sku' => $sku, 'len' => 10, 'wid' => 10, 'hei' => 10, 'allow_locker' => $value]);
            $products[] = $product;
        }

        $collection = $this->getMockBuilder(Collection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setStoreId', 'addAttributeToSelect', 'addAttributeToFilter', 'getIterator'])
            ->getMock();
        $collection->method('setStoreId')->willReturnSelf();
        $collection->method('addAttributeToSelect')->willReturnSelf();
        $collection->expects(self::once())->method('addAttributeToFilter')->willReturnSelf();
        $collection->method('getIterator')->willReturn(new \ArrayIterator($products));

        // Generated factory: dev/tests/unit/framework/autoload.php generates it when absent.
        $collectionFactory = $this->getMockBuilder(CollectionFactory::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['create'])
            ->getMock();
        $collectionFactory->method('create')->willReturn($collection);

        $resultFactory = $this->getMockBuilder(ResultFactory::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['create'])
            ->getMock();
        $resultFactory->method('create')->willReturnCallback(
            fn () => $this->getMockBuilder(Result::class)->disableOriginalConstructor()->onlyMethods([])->getMock()
        );

        $methodFactory = $this->getMockBuilder(MethodFactory::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['create'])
            ->getMock();
        $methodFactory->method('create')->willReturnCallback(
            fn () => $this->getMockBuilder(Method::class)->disableOriginalConstructor()->onlyMethods(['setPrice'])->getMock()
        );

        $this->setProperty($carrier, 'productCollectionFactory', $collectionFactory);
        $this->setProperty($carrier, '_rateFactory', $resultFactory);
        $this->setProperty($carrier, '_rateMethodFactory', $methodFactory);
        $this->setProperty($carrier, '_code', 'innoshipcargusgo');

        $items = [];
        foreach ($cartSkus ?? array_keys($attributeBySku) as $sku) {
            $items[] = new DataObject(['sku' => $sku, 'qty' => 1, 'discount_amount' => 0]);
        }

        $request = $this->getMockBuilder(RateRequest::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $request->setData(['store_id' => 1, 'dest_country_id' => 'RO', 'all_items' => $items]);

        $method = new \ReflectionMethod(Carriergo::class, 'getQuotes');
        /** @var Result $result */
        $result = $method->invoke($carrier, $request);

        return count($result->getAllRates());
    }

    private function setProperty(object $object, string $name, $value): void
    {
        $class = new \ReflectionClass($object);
        while (!$class->hasProperty($name)) {
            $class = $class->getParentClass();
        }
        $property = $class->getProperty($name);
        $property->setValue($object, $value);
    }
}
