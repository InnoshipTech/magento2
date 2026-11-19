<?php
declare(strict_types=1);

namespace InnoShip\InnoShip\Test\Unit\Model\Checkout;

use InnoShip\InnoShip\Model\Checkout\SelectedLockerConfigProvider;
use InnoShip\InnoShip\Model\PudoAddress;
use Magento\Checkout\Model\Session;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SelectedLockerConfigProviderTest extends TestCase
{
    private const PUDO_ROW = [
        'pudo_id' => 100,
        'addressText' => 'Sos. Pacurari Nr. 97-99',
        'localityName' => 'Iasi',
        'countyName' => 'Iași',
        'postalCode' => '700515',
    ];

    public function testLockerOnQuoteIsReturnedInTheMapAddressFormat(): void
    {
        $config = $this->runProvider(503, 'innoshipcargusgo_innoshipcargusgo_1', 100, self::PUDO_ROW)->getConfig();

        self::assertSame(
            [
                'innoshipSelectedLocker' => [
                    'id' => 100,
                    // checkout.js setLockerList(): addressText, localityName, countyName, postalCode
                    'address' => 'Sos. Pacurari Nr. 97-99, Iasi, Iași, 700515',
                ],
            ],
            $config
        );
    }

    public function testCourierMethodGivesNullEvenWithAStalePudoId(): void
    {
        $config = $this->runProvider(503, 'innoship_1', 100, self::PUDO_ROW)->getConfig();

        self::assertSame(['innoshipSelectedLocker' => null], $config);
    }

    public function testPlainCheckoutQuoteWithoutSavedMethodStillGivesThePickedLocker(): void
    {
        $config = $this->runProvider(660, '', 100, self::PUDO_ROW)->getConfig();

        self::assertSame(100, $config['innoshipSelectedLocker']['id'] ?? null);
    }

    public function testLockerMethodWithoutPickedLockerGivesNull(): void
    {
        $config = $this->runProvider(503, 'innoshipcargusgo_innoshipcargusgo_1', 0, null)->getConfig();

        self::assertSame(['innoshipSelectedLocker' => null], $config);
    }

    public function testNoQuoteDoesNotLoadOne(): void
    {
        $session = $this->getMockBuilder(Session::class)->disableOriginalConstructor()
            ->onlyMethods(['getQuoteId', 'getQuote'])->getMock();
        $session->method('getQuoteId')->willReturn(null);
        $session->expects(self::never())->method('getQuote');

        $provider = new SelectedLockerConfigProvider(
            $session,
            $this->createMock(PudoAddress::class),
            $this->createMock(LoggerInterface::class)
        );

        self::assertSame(['innoshipSelectedLocker' => null], $provider->getConfig());
    }

    public function testFailureStillRendersCheckout(): void
    {
        $session = $this->getMockBuilder(Session::class)->disableOriginalConstructor()
            ->onlyMethods(['getQuoteId', 'getQuote'])->getMock();
        $session->method('getQuoteId')->willReturn(503);
        $session->method('getQuote')->willThrowException(new \RuntimeException('db down'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $provider = new SelectedLockerConfigProvider($session, $this->createMock(PudoAddress::class), $logger);

        self::assertSame(['innoshipSelectedLocker' => null], $provider->getConfig());
    }

    private function runProvider(int $quoteId, string $method, int $pudoId, ?array $pudoRow): SelectedLockerConfigProvider
    {
        $address = $this->getMockBuilder(Address::class)->disableOriginalConstructor()
            ->onlyMethods(['getData', 'getShippingMethod'])->getMock();
        $address->method('getShippingMethod')->willReturn($method);
        $address->method('getData')->willReturnCallback(
            static fn ($key = '') => $key === PudoAddress::PUDO_ID_FIELD ? $pudoId : null
        );

        $quote = $this->getMockBuilder(Quote::class)->disableOriginalConstructor()
            ->onlyMethods(['getShippingAddress'])->getMock();
        $quote->method('getShippingAddress')->willReturn($address);

        $session = $this->getMockBuilder(Session::class)->disableOriginalConstructor()
            ->onlyMethods(['getQuoteId', 'getQuote'])->getMock();
        $session->method('getQuoteId')->willReturn($quoteId);
        $session->method('getQuote')->willReturn($quote);

        $pudoAddress = $this->createMock(PudoAddress::class);
        $pudoAddress->method('getPudoRow')->willReturnCallback(
            static fn (int $id) => $id === $pudoId && $id > 0 ? $pudoRow : null
        );

        return new SelectedLockerConfigProvider($session, $pudoAddress, $this->createMock(LoggerInterface::class));
    }
}
