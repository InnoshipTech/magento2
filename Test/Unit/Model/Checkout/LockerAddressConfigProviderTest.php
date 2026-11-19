<?php
declare(strict_types=1);

namespace InnoShip\InnoShip\Test\Unit\Model\Checkout;

use InnoShip\InnoShip\Model\Checkout\LockerAddressConfigProvider;
use Magento\Customer\Model\Address;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\Session;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class LockerAddressConfigProviderTest extends TestCase
{
    public function testLockerRowIsFlaggedAndRealAddressIsNot(): void
    {
        $ids = $this->runProvider(
            true,
            [
                // the locker fill wrote addressText with commas as spaces
                [11, ['Str. Fabricii  nr 46   Lidl'], 'RO', '400620'],
                [12, ['Str. Memorandumului 28', 'ap. 4'], 'RO', '400114'],
            ],
            [
                ['countryCode' => 'RO', 'postalCode' => '400620', 'addressText' => 'Str. Fabricii, nr 46, Lidl'],
                ['countryCode' => 'RO', 'postalCode' => '400114', 'addressText' => 'Piata Unirii 1'],
            ]
        );

        self::assertSame([11], $ids);
    }

    public function testGuestGetsEmptyListWithoutQuery(): void
    {
        self::assertSame([], $this->runProvider(false, [[11, ['x'], 'RO', '1']], [], false));
    }

    public function testDatabaseFailureStillRendersCheckout(): void
    {
        $session = $this->createSession(true, [[11, ['Str. A 1'], 'RO', '400620']]);
        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willThrowException(new \RuntimeException('db down'));

        $provider = new LockerAddressConfigProvider($session, $resource, $this->createMock(LoggerInterface::class));

        self::assertSame(['innoshipLockerAddressIds' => []], $provider->getConfig());
    }

    private function runProvider(bool $loggedIn, array $addresses, array $pudoRows, bool $expectQuery = true): array
    {
        $select = $this->getMockBuilder(Select::class)->disableOriginalConstructor()->onlyMethods(['from', 'where'])->getMock();
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->expects($expectQuery ? self::once() : self::never())->method('fetchAll')->willReturn($pudoRows);

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);

        $provider = new LockerAddressConfigProvider(
            $this->createSession($loggedIn, $addresses),
            $resource,
            $this->createMock(LoggerInterface::class)
        );

        return $provider->getConfig()['innoshipLockerAddressIds'];
    }

    private function createSession(bool $loggedIn, array $addresses): Session
    {
        $addressModels = [];
        foreach ($addresses as [$id, $street, $country, $postcode]) {
            $address = $this->getMockBuilder(Address::class)
                ->disableOriginalConstructor()
                ->onlyMethods(['getId', 'getStreet'])
                ->getMock();
            $address->method('getId')->willReturn($id);
            $address->method('getStreet')->willReturn($street);
            // getCountryId()/getPostcode() are magic getters on the address model
            $address->setData('country_id', $country);
            $address->setData('postcode', $postcode);
            $addressModels[] = $address;
        }

        $customer = $this->getMockBuilder(Customer::class)->disableOriginalConstructor()->onlyMethods(['getAddresses'])->getMock();
        $customer->method('getAddresses')->willReturn($addressModels);

        $session = $this->getMockBuilder(Session::class)->disableOriginalConstructor()->onlyMethods(['isLoggedIn', 'getCustomer'])->getMock();
        $session->method('isLoggedIn')->willReturn($loggedIn);
        $session->method('getCustomer')->willReturn($customer);

        return $session;
    }
}
