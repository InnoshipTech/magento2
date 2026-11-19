<?php
declare(strict_types=1);

namespace InnoShip\InnoShip\Test\Unit\Model;

use InnoShip\InnoShip\Model\PudoAddress;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PudoAddressTest extends TestCase
{
    private const PUDO_ROW = [
        'pudo_id' => 123,
        'addressText' => 'Str. Exemplu 1',
        'localityName' => 'Iasi',
        'postalCode' => '700001',
        'countryCode' => 'RO',
        // Empty county: region resolution returns early, no directory lookup in a unit test.
        'countyName' => '',
    ];

    /**
     * @var PudoAddress&MockObject
     */
    private $pudoAddress;

    protected function setUp(): void
    {
        // Constructor skipped: its region CollectionFactory is generated code, absent before
        // setup:di:compile; the locker row comes from the stubbed getPudoRow().
        $this->pudoAddress = $this->getMockBuilder(PudoAddress::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPudoRow'])
            ->getMock();
        $this->pudoAddress->method('getPudoRow')->willReturn(self::PUDO_ROW);
    }

    public function testApplySetsQuoteCustomerIdForLoggedInCustomer(): void
    {
        $address = $this->createAddress(42);

        self::assertTrue($this->pudoAddress->applyToAddress($address, 123));
        self::assertSame(42, $address->getCustomerId());
        self::assertSame(0, $address->getSaveInAddressBook());
        self::assertSame(0, $address->getSameAsBilling());
        self::assertNull($address->getCustomerAddressId());
    }

    public function testApplyLeavesGuestCustomerIdEmpty(): void
    {
        $address = $this->createAddress(null);

        self::assertTrue($this->pudoAddress->applyToAddress($address, 123));
        self::assertNull($address->getCustomerId());
    }

    public function testOwnedColumnsAfterApply(): void
    {
        $address = $this->createAddress(42);
        $address->setData('customer_address_id', 9);
        $address->setData('firstname', 'Ion');
        $address->setData('telephone', '0712345678');

        $this->pudoAddress->applyToAddress($address, 123);
        $columns = $this->pudoAddress->getOwnedColumns($address);

        self::assertSame(
            [
                'innoship_pudo_id',
                'customer_address_id',
                'customer_id',
                'save_in_address_book',
                'same_as_billing',
                'street',
                'city',
                'postcode',
                'country_id',
                'region_id',
                'region',
                'collect_shipping_rates',
            ],
            array_keys($columns),
        );
        self::assertSame(123, $columns['innoship_pudo_id']);
        self::assertNull($columns['customer_address_id']);
        self::assertSame(42, $columns['customer_id']);
        self::assertSame('Str. Exemplu 1', $columns['street']);
        self::assertSame('Iasi', $columns['city']);
        self::assertSame('700001', $columns['postcode']);
        self::assertSame('RO', $columns['country_id']);
        self::assertSame(1, $columns['collect_shipping_rates']);
        self::assertArrayNotHasKey('firstname', $columns);
        self::assertArrayNotHasKey('telephone', $columns);
    }

    public function testOwnedColumnsWhenClearedAreOnlyThePudoId(): void
    {
        $address = $this->createAddress(42);
        $address->setData(PudoAddress::PUDO_ID_FIELD, null);

        self::assertSame(
            [PudoAddress::PUDO_ID_FIELD => null],
            $this->pudoAddress->getOwnedColumns($address),
        );
    }

    /**
     * @param int|null $customerId
     * @return Address&MockObject
     */
    private function createAddress(?int $customerId): Address
    {
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        // Magic getter: DataObject::__call reads the stored value (addMethods() is gone in PHPUnit 12).
        $quote->setData('customer_id', $customerId);

        $address = $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getQuote',
                'isAddressMultilineAttribute',
            ])
            ->getMock();
        $address->method('getQuote')->willReturn($quote);
        $address->method('isAddressMultilineAttribute')->willReturnCallback(
            static fn ($code): bool => $code === 'street'
        );

        return $address;
    }
}
