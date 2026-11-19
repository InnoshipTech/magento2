<?php
declare(strict_types=1);

namespace InnoShip\InnoShip\Test\Unit\Model;

use InnoShip\InnoShip\Model\SessionQuoteId;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use PHPUnit\Framework\TestCase;

class SessionQuoteIdTest extends TestCase
{
    public function testOwnMaskedQuote(): void
    {
        self::assertSame(5, $this->create(5, ['abc123' => 5])->resolve('abc123'));
    }

    public function testOwnNumericQuote(): void
    {
        self::assertSame(5, $this->create(5, [])->resolve('5'));
    }

    public function testForeignNumericQuoteIsRejected(): void
    {
        self::assertNull($this->create(5, [])->resolve('6'));
    }

    public function testForeignMaskedQuoteIsRejected(): void
    {
        self::assertNull($this->create(5, ['other' => 6])->resolve('other'));
    }

    public function testNoSessionQuoteIsRejected(): void
    {
        self::assertNull($this->create(null, [])->resolve('5'));
    }

    public function testNonScalarIsRejected(): void
    {
        self::assertNull($this->create(5, [])->resolve(['5']));
    }

    /**
     * @param int|null $sessionQuoteId
     * @param array<string, int> $masks
     * @return SessionQuoteId
     */
    private function create(?int $sessionQuoteId, array $masks): SessionQuoteId
    {
        $session = $this->getMockBuilder(CheckoutSession::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getQuoteId'])
            ->getMock();
        $session->method('getQuoteId')->willReturn($sessionQuoteId);

        $masked = $this->createMock(MaskedQuoteIdToQuoteIdInterface::class);
        $masked->method('execute')->willReturnCallback(
            static function (string $maskedId) use ($masks): int {
                if (!isset($masks[$maskedId])) {
                    throw new NoSuchEntityException();
                }

                return $masks[$maskedId];
            }
        );

        return new SessionQuoteId($session, $masked);
    }
}
