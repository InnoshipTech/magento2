<?php

namespace InnoShip\InnoShip\Block\Adminhtml\Form\Field;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;

/**
 * Class CountryFees
 * Dynamic rows for country-specific fees configuration
 * @package InnoShip\InnoShip\Block\Adminhtml\Form\Field
 */
class CountryFees extends AbstractFieldArray
{
    /**
     * @var CountryColumn
     */
    private $countryRenderer;

    /**
     * Prepare rendering the new field by adding all the needed columns
     */
    protected function _prepareToRender()
    {
        $this->addColumn(
            'country_id',
            [
                'label' => __('Country'),
                'renderer' => $this->getCountryRenderer(),
                'class' => 'required-entry'
            ]
        );

        $this->addColumn(
            'fee',
            [
                'label' => __('Handling Fee'),
                'class' => 'required-entry validate-number validate-zero-or-greater'
            ]
        );

        $this->addColumn(
            'free_shipping_threshold',
            [
                'label' => __('Free Shipping Threshold'),
                'class' => 'validate-number validate-zero-or-greater',
                'style' => 'width:120px'
            ]
        );

        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add Country');
    }

    /**
     * Obtain existing data from form element
     *
     * Each row will be instance of \Magento\Framework\DataObject
     *
     * @return array
     */
    public function getArrayRows()
    {
        $result = [];
        /** @var \Magento\Framework\Data\Form\Element\AbstractElement */
        $element = $this->getElement();
        if ($element->getValue() && is_array($element->getValue())) {
            foreach ($element->getValue() as $rowId => $row) {
                // Ensure row ID is prefixed with 'cf_row_' (cf = country fees) to avoid numeric IDs and conflicts
                $rowId = is_numeric($rowId) ? 'cf_row_' . $rowId : $rowId;

                $rowColumnValues = [];
                foreach ($row as $key => $value) {
                    $row[$key] = $this->escapeHtml($value);
                    $rowColumnValues[$this->_getCellInputElementId($rowId, $key)] = $row[$key];
                }
                $row['_id'] = $rowId;
                $row['column_values'] = $rowColumnValues;
                $result[$rowId] = new \Magento\Framework\DataObject($row);
                $this->_prepareArrayRow($result[$rowId]);
            }
        }
        return $result;
    }

    /**
     * Prepare existing row data object
     *
     * @param DataObject $row
     * @throws LocalizedException
     */
    protected function _prepareArrayRow(DataObject $row): void
    {
        $options = [];

        $country = $row->getCountryId();
        if ($country !== null) {
            $options['option_' . $this->getCountryRenderer()->calcOptionHash($country)] = 'selected="selected"';
        }

        $row->setData('option_extra_attrs', $options);
    }

    /**
     * Get country column renderer
     *
     * @return CountryColumn
     * @throws LocalizedException
     */
    private function getCountryRenderer()
    {
        if (!$this->countryRenderer) {
            $this->countryRenderer = $this->getLayout()->createBlock(
                CountryColumn::class,
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
        }
        return $this->countryRenderer;
    }
}
