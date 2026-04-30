<?php
declare(strict_types=1);

namespace InnoShip\InnoShip\Plugin\Frontend\Magento\Checkout\Model;

use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Framework\Exception\InputException;
use Magento\Quote\Api\CartRepositoryInterface;
use Psr\Log\LoggerInterface;

class ShippingInformationManagement
{
    protected $_pudoID;
    protected $_courierID;

    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param \Magento\Checkout\Model\ShippingInformationManagement $subject
     * @param $cartId
     * @param $addressInformation
     *
     * @return array
     * @throws InputException
     */
    public function beforeSaveAddressInformation(
        \Magento\Checkout\Model\ShippingInformationManagement $subject,
                                                              $cartId,
                                                              $addressInformation
    )
    {
        /** @var ShippingInformationInterface $addressInformation */
        if ($addressInformation->getShippingCarrierCode() === "innoshipcargusgo_innoshipcargusgo_1") {
            $address = $addressInformation->getShippingAddress();
            $this->_pudoID = $address->getInnoshipPudoId();
        }

        if ($addressInformation->getShippingCarrierCode() === "innoship_1") {
            $address = $addressInformation->getShippingAddress();
            $this->_courierID = $address->getInnoshipCourierId();
        }

        return [$cartId, $addressInformation];
    }

    public function afterSaveAddressInformation(
        \Magento\Checkout\Model\ShippingInformationManagement $subject,
                                                              $result,
                                                              $cartId,
        \Magento\Checkout\Api\Data\ShippingInformationInterface $addressInformation
    )
    {
        // Get the selected shipping method
        $shippingMethod = $addressInformation->getShippingMethodCode();
        $shippingCarrier = $addressInformation->getShippingCarrierCode();
        $fullShippingMethod = $shippingCarrier . '_' . $shippingMethod;

        // IMPORTANT: $addressInformation->getShippingAddress() is the in-memory
        // address from the API request — Magento has already finished saving by
        // the time this after-plugin runs, so mutating that object is a no-op.
        // To actually persist the change we must reload the quote, mutate its
        // shipping address, and save through the cart repository.
        try {
            $quote = $this->cartRepository->getActive((int) $cartId);
            $quoteShippingAddress = $quote->getShippingAddress();
            $needsSave = false;

            if ($fullShippingMethod === 'innoshipcargusgo_innoshipcargusgo_1') {
                if ((int) $this->_pudoID > 0) {
                    $quoteShippingAddress->setInnoshipPudoId($this->_pudoID);
                    $needsSave = true;
                }
            } else {
                // Switched away from locker — wipe the stored locker id so it
                // does not get copied onto the order at place-order time.
                if ((int) $quoteShippingAddress->getInnoshipPudoId() > 0) {
                    $quoteShippingAddress->setInnoshipPudoId(null);
                    $needsSave = true;
                }
            }

            if ((int) $this->_courierID > 0) {
                $quoteShippingAddress->setInnoshipCourierId($this->_courierID);
                $needsSave = true;
            }

            if ($needsSave) {
                $this->cartRepository->save($quote);
            }
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);
        }

        return $result;
    }
}
