<?php

namespace InnoShip\InnoShip\Model\Config\Backend;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * Class CountryFees
 * Backend model for country-specific fees
 * @package InnoShip\InnoShip\Model\Config\Backend
 */
class CountryFees extends Value
{
    /**
     * @var SerializerInterface
     */
    protected $serializer;

    /**
     * CountryFees constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param SerializerInterface $serializer
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        SerializerInterface $serializer,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->serializer = $serializer;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * Process data before save
     *
     * @return $this
     */
    public function beforeSave()
    {
        $value = $this->getValue();

        // If value is array, serialize it
        if (is_array($value)) {
            // First, normalize the keys (remove prefixes like 'row_', 'cf_row_', 'pudo_row_' if present)
            $normalizedValue = [];
            foreach ($value as $key => $row) {
                // If key starts with any row prefix, strip it
                if (is_string($key) && (
                    strpos($key, 'cf_row_') === 0 ||
                    strpos($key, 'pudo_row_') === 0 ||
                    strpos($key, 'row_') === 0
                )) {
                    $normalizedValue[] = $row;
                } elseif (!is_string($row)) {
                    // If it's not a string (template marker), keep it
                    $normalizedValue[] = $row;
                }
            }

            // Remove empty rows and __empty placeholder
            $normalizedValue = array_filter($normalizedValue, function($row) {
                // Skip if it's just the template row marker
                if (is_string($row)) {
                    return false;
                }
                return !empty($row['country_id']) && isset($row['fee']);
            });

            // Re-index array
            $normalizedValue = array_values($normalizedValue);

            // Serialize the value
            $serializedValue = $this->serializer->serialize($normalizedValue);
            $this->setValue($serializedValue);
        } elseif (empty($value)) {
            // Set empty array if value is null or empty
            $this->setValue($this->serializer->serialize([]));
        }

        return parent::beforeSave();
    }

    /**
     * Process data after load
     *
     * @return $this
     */
    protected function _afterLoad()
    {
        $value = $this->getValue();

        // If value is serialized string, unserialize it
        if (!empty($value) && is_string($value)) {
            try {
                $unserializedValue = $this->serializer->unserialize($value);
                // Ensure we have an array
                if (is_array($unserializedValue)) {
                    $this->setValue($unserializedValue);
                } else {
                    $this->setValue([]);
                }
            } catch (\Exception $e) {
                $this->setValue([]);
            }
        } elseif (empty($value)) {
            $this->setValue([]);
        }

        return parent::_afterLoad();
    }
}
