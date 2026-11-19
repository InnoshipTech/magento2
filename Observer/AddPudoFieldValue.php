<?php
namespace InnoShip\InnoShip\Observer;
class AddPudoFieldValue implements \Magento\Framework\Event\ObserverInterface
{
    /** Order flag: this request already mirrored the quote's locker/courier onto the order. */
    const PROCESSED_FLAG = 'innoship_pudo_processed';

    /**
     * @var \Magento\Quote\Model\QuoteFactory
     */
    protected $quoteFactory;

    /**
     * @var \Magento\Sales\Api\OrderAddressRepositoryInterface
     */
    private $orderAddressRepository;

    public function __construct(
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Magento\Sales\Api\OrderAddressRepositoryInterface $orderAddressRepository
    )
    {
        $this->quoteFactory = $quoteFactory;
        $this->orderAddressRepository = $orderAddressRepository;
    }

    /*
     * Fetch Quote Factory and add field to this function
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();
        if (!$order || !$order->getQuoteId() || $order->getData(self::PROCESSED_FLAG)) {
            return;
        }
        // Set before any save below: an order save from another observer re-enters here.
        $order->setData(self::PROCESSED_FLAG, true);

        $quote = $this->quoteFactory->create()->load($order->getQuoteId());
        $shippingAddress = $order->getShippingAddress();
        if (!$quote->getId() || !$quote->getShippingAddress() || !$shippingAddress) {
            return;
        }

        $pudoValue = $quote->getShippingAddress()->getInnoshipPudoId();
        $courierId = $quote->getShippingAddress()->getInnoshipCourierId();

        // Mirror the quote shipping address state onto the order shipping
        // address. When the quote has no locker (null or 0), force the order
        // pudo to NULL — never re-read the order's own value, otherwise a
        // stale id copied during quote→order conversion would be re-saved
        // and the locker would appear to "stick" on the order.
        $newPudo = (int) $pudoValue > 0 ? (int) $pudoValue : null;
        $newCourier = (int) $courierId > 0 ? (int) $courierId : null;
        $oldPudo = (int) $shippingAddress->getInnoshipPudoId() > 0 ? (int) $shippingAddress->getInnoshipPudoId() : null;
        $oldCourier = (int) $shippingAddress->getInnoshipCourierId() > 0 ? (int) $shippingAddress->getInnoshipCourierId() : null;

        if ($newPudo === $oldPudo && $newCourier === $oldCourier) {
            return;
        }

        $shippingAddress->setInnoshipPudoId($newPudo);
        $shippingAddress->setInnoshipCourierId($newCourier);

        // Address only: a full order save inside sales_order_save_after re-fires every order-save observer.
        $this->orderAddressRepository->save($shippingAddress);
    }
}
