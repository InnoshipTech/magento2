<?php
declare(strict_types=1);

namespace InnoShip\InnoShip\Plugin\Magento\Quote\Model\Quote\Address;

use Magento\Quote\Api\Data\AddressInterface;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Psr\Log\LoggerInterface;

/**
 * Class ToOrderAddress
 *
 * Description class.
 */
class ToOrderAddress
{
    protected $logger;

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(
        LoggerInterface $logger
    )
    {
        $this->logger = $logger;
    }

    /**
     * Method afterConvert
     *
     * @param \Magento\Quote\Model\Quote\Address\ToOrderAddress $subject
     * @param $result
     * @param $object
     * @param $data
     *
     * @return mixed
     */
    public function afterConvert(
        \Magento\Quote\Model\Quote\Address\ToOrderAddress $subject,
                                                          $result,
                                                          $object,
                                                          $data = []
    ) {
        /** @var AddressInterface $object */
        /** @var OrderAddressInterface $result */

        $pudoId = $object->getInnoshipPudoId();
        $courierId = $object->getInnoshipCourierId();
        $result->setInnoshipPudoId(is_null($pudoId) ? NULL : $pudoId);
        $result->setInnoshipCourierId(is_null($courierId) ? NULL : $courierId);

        return $result;
    }
}
