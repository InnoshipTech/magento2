<?php
namespace InnoShip\InnoShip\Observer;
class AddPudoFieldValue implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * @var \Magento\Quote\Model\QuoteFactory
     */
    protected $quoteFactory;

    public function __construct(
        \Magento\Quote\Model\QuoteFactory $quoteFactory
    )
    {
        $this->quoteFactory = $quoteFactory;
    }

    /*
     * Fetch Quote Factory and add field to this function
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();
        if (!$order || !$order->getQuoteId()) {
            return;
        }

        $quote = $this->quoteFactory->create()->load($order->getQuoteId());
        if (!$quote->getId() || !$quote->getShippingAddress() || !$order->getShippingAddress()) {
            return;
        }

        $pudoValue = $quote->getShippingAddress()->getInnoshipPudoId();
        $courierId = $quote->getShippingAddress()->getInnoshipCourierId();

        // Mirror the quote shipping address state onto the order shipping
        // address. When the quote has no locker (null or 0), force the order
        // pudo to NULL — never re-read the order's own value, otherwise a
        // stale id copied during quote→order conversion would be re-saved
        // and the locker would appear to "stick" on the order.
        $order->getShippingAddress()->setInnoshipPudoId(
            (int) $pudoValue > 0 ? $pudoValue : null,
        );
        $order->getShippingAddress()->setInnoshipCourierId(
            (int) $courierId > 0 ? $courierId : null,
        );

        $order->save();
    }
}
