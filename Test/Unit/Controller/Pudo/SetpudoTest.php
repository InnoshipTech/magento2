<?php
declare(strict_types=1);

namespace InnoShip\InnoShip\Test\Unit\Controller\Pudo;

use InnoShip\InnoShip\Controller\Pudo\Setpudo;
use InnoShip\InnoShip\Model\PudoAddress;
use InnoShip\InnoShip\Model\SessionQuoteId;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SetpudoTest extends TestCase
{
    private const OWNED = [
        'innoship_pudo_id' => 123,
        'customer_address_id' => null,
        'customer_id' => 42,
        'street' => 'Str. Exemplu 1',
    ];

    /**
     * @var AdapterInterface&MockObject
     */
    private $connection;

    /**
     * @var CartRepositoryInterface&MockObject
     */
    private $quoteRepository;

    /**
     * @var PudoAddress&MockObject
     */
    private $pudoAddress;

    /**
     * @var Address&MockObject
     */
    private $address;

    /**
     * @var RequestInterface&MockObject
     */
    private $request;

    /**
     * @var SessionQuoteId&MockObject
     */
    private $sessionQuoteId;

    private Setpudo $controller;

    protected function setUp(): void
    {
        $this->request = $this->createMock(RequestInterface::class);
        $context = $this->createMock(Context::class);
        $context->method('getRequest')->willReturn($this->request);

        $this->connection = $this->createMock(AdapterInterface::class);
        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($this->connection);
        $resource->method('getTableName')->willReturnArgument(0);

        $json = $this->createMock(Json::class);
        $json->method('setData')->willReturnSelf();
        $jsonFactory = $this->createMock(JsonFactory::class);
        $jsonFactory->method('create')->willReturn($json);

        $this->address = $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'save'])
            ->getMock();
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getShippingAddress'])
            ->getMock();
        $quote->method('getShippingAddress')->willReturn($this->address);

        $this->quoteRepository = $this->createMock(CartRepositoryInterface::class);
        $this->quoteRepository->method('get')->willReturn($quote);

        $this->sessionQuoteId = $this->createMock(SessionQuoteId::class);

        $this->pudoAddress = $this->createMock(PudoAddress::class);

        $this->controller = new Setpudo(
            $context,
            $resource,
            $jsonFactory,
            $this->quoteRepository,
            $this->sessionQuoteId,
            $this->createMock(FormKeyValidator::class),
            $this->pudoAddress,
        );
    }

    public function testLockerWritesOnlyOwnedColumnsWithoutQuoteSave(): void
    {
        $this->givenRequest('123');
        $this->address->method('getId')->willReturn(77);
        $this->pudoAddress->expects(self::once())->method('applyToAddress')->willReturn(true);
        $this->pudoAddress->method('getOwnedColumns')->willReturn(self::OWNED);

        $this->connection->expects(self::once())
            ->method('update')
            ->with('quote_address', self::OWNED, ['address_id = ?' => 77]);
        $this->address->expects(self::never())->method('save');
        $this->quoteRepository->expects(self::never())->method('save');

        $this->controller->execute();
    }

    public function testClearWritesOnlyThePudoId(): void
    {
        $this->givenRequest('0');
        $this->address->method('getId')->willReturn(77);
        $this->pudoAddress->expects(self::never())->method('applyToAddress');

        $this->connection->expects(self::once())
            ->method('update')
            ->with('quote_address', ['innoship_pudo_id' => null], ['address_id = ?' => 77]);
        $this->quoteRepository->expects(self::never())->method('save');

        $this->controller->execute();
    }

    public function testNoStoredAddressRowKeepsTheSavePath(): void
    {
        $this->givenRequest('123');
        $this->address->method('getId')->willReturn(null);
        $this->pudoAddress->method('applyToAddress')->willReturn(true);

        $this->connection->expects(self::never())->method('update');
        $this->address->expects(self::once())->method('save');
        $this->quoteRepository->expects(self::once())->method('save');

        $this->controller->execute();
    }

    public function testQuoteOfAnotherVisitorIsRejected(): void
    {
        $this->request->method('getParam')->willReturnMap([
            ['quote', null, '999'],
            ['pudoid', null, '123'],
        ]);
        $this->sessionQuoteId->method('resolve')->with('999')->willReturn(null);

        $this->quoteRepository->expects(self::never())->method('get');
        $this->connection->expects(self::never())->method('update');
        $this->quoteRepository->expects(self::never())->method('save');

        $this->controller->execute();
    }

    private function givenRequest(string $pudoId): void
    {
        $this->sessionQuoteId->method('resolve')->willReturn(5);
        $this->request->method('getParam')->willReturnMap([
            ['quote', null, 'masked-quote-id'],
            ['pudoid', null, $pudoId],
        ]);
    }
}
