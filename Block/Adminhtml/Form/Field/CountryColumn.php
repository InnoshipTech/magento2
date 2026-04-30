<?php

namespace InnoShip\InnoShip\Block\Adminhtml\Form\Field;

use Magento\Framework\View\Element\Html\Select;
use Magento\Framework\View\Element\Context;
use Magento\Directory\Model\ResourceModel\Country\CollectionFactory as CountryCollectionFactory;

/**
 * Class CountryColumn
 * Renders country select column in dynamic rows
 * @package InnoShip\InnoShip\Block\Adminhtml\Form\Field
 */
class CountryColumn extends Select
{
    /**
     * @var CountryCollectionFactory
     */
    private $countryCollectionFactory;

    /**
     * CountryColumn constructor.
     *
     * @param Context $context
     * @param CountryCollectionFactory $countryCollectionFactory
     * @param array $data
     */
    public function __construct(
        Context $context,
        CountryCollectionFactory $countryCollectionFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->countryCollectionFactory = $countryCollectionFactory;
    }

    /**
     * Set "name" for <select> element
     *
     * @param string $value
     * @return $this
     */
    public function setInputName($value)
    {
        return $this->setName($value);
    }

    /**
     * Set "id" for <select> element
     *
     * @param string $value
     * @return $this
     */
    public function setInputId($value)
    {
        return $this->setId($value);
    }

    /**
     * Render block HTML
     *
     * @return string
     */
    public function _toHtml(): string
    {
        if (!$this->getOptions()) {
            $this->setOptions($this->getCountryOptions());
        }
        return parent::_toHtml();
    }

    /**
     * Get country options for select
     *
     * @return array
     */
    private function getCountryOptions(): array
    {
        $options = [];
        $countries = $this->countryCollectionFactory->create()->loadData()->toOptionArray(false);

        foreach ($countries as $country) {
            $options[$country['value']] = $country['label'];
        }

        return $options;
    }
}
