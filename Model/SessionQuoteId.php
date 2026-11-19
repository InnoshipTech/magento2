<?php
declare(strict_types=1);

namespace InnoShip\InnoShip\Model;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;

/**
 * Resolves the quote id a storefront request names, and only if it is the visitor's own quote.
 *
 * The checkout JS sends quote.getQuoteId(): the masked id for a guest, the numeric entity id for
 * a customer. The requested id must match the quote of the checkout session.
 */
class SessionQuoteId
{
    /**
     * @param CheckoutSession $checkoutSession
     * @param MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
     */
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
    ) {
    }

    /**
     * The quote id when the requested (masked or numeric) id is the session's quote, else null.
     *
     * @param mixed $requestedQuoteId
     * @return int|null
     */
    public function resolve($requestedQuoteId): ?int
    {
        if (!is_scalar($requestedQuoteId)) {
            return null;
        }

        $requestedQuoteId = trim((string) $requestedQuoteId);
        if ($requestedQuoteId === '') {
            return null;
        }

        $sessionQuoteId = (int) $this->checkoutSession->getQuoteId();
        if ($sessionQuoteId <= 0) {
            return null;
        }

        try {
            $quoteId = $this->maskedQuoteIdToQuoteId->execute($requestedQuoteId);
        } catch (\Exception $e) {
            // Not a masked id: a logged-in customer's checkout sends the numeric id.
            $quoteId = ctype_digit($requestedQuoteId) ? (int) $requestedQuoteId : 0;
        }

        return $quoteId === $sessionQuoteId ? $quoteId : null;
    }
}
