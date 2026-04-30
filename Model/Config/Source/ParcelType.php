<?php

namespace InnoShip\InnoShip\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Class ParcelType
 * @package InnoShip\InnoShip\Model\Config\Source
 */
class ParcelType implements OptionSourceInterface
{
    /** @var string */
    const TYPE_ENVELOPE = 'Envelope';

    /** @var string */
    const TYPE_PARCEL = 'Parcel';

    /** @var string */
    const TYPE_PALLET = 'Pallet';

    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => self::TYPE_ENVELOPE,
                'label' => __('Envelope'),
            ],
            [
                'value' => self::TYPE_PALLET,
                'label' => __('Parcel'),
            ],
            [
                'value' => self::TYPE_PALLET,
                'label' => __('Pallet'),
            ],
        ];
    }
}
