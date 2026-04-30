<?php

namespace InnoShip\InnoShip\Block\Adminhtml\Awb\Edit;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Form\Generic;
use Magento\Framework\Data\Form as MagentoForm;
use Magento\Framework\Data\Form\Element\Fieldset;
use Magento\Framework\Data\FormFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Registry;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\ShipmentInterface;
use InnoShip\InnoShip\Block\Adminhtml\Form\Renderer\Fieldset\Contents;
use InnoShip\InnoShip\Model\Config\Source\Payment;
use InnoShip\InnoShip\Model\Config;
use Magento\Config\Model\Config\Source\Yesno;
use Magento\Framework\Stdlib\DateTime\DateTime;

/**
 * Class Form
 * @package InnoShip\InnoShip\Block\Adminhtml\Awb\Edit
 */
class Form extends Generic
{
    /** @var Config */
    protected $config;

    /** @var Payment */
    protected $paymentSource;

    /** @var Yesno */
    protected $yesNoSource;

    /** @var DateTime */
    protected $dateTime;

    /**
     * Form constructor.
     *
     * @param Context     $context
     * @param Registry    $registry
     * @param FormFactory $formFactory
     * @param Config      $config
     * @param Payment     $paymentSource
     * @param Yesno       $yesNoSource
     * @param DateTime    $dateTime
     * @param array       $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        FormFactory $formFactory,
        Config $config,
        Payment $paymentSource,
        Yesno $yesNoSource,
        DateTime $dateTime,
        array $data = []
    ) {
        $this->config        = $config;
        $this->paymentSource = $paymentSource;
        $this->yesNoSource   = $yesNoSource;
        $this->dateTime      = $dateTime;

        parent::__construct($context, $registry, $formFactory, $data);
    }

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        parent::_construct();

        $this->setId('awb_edit_form');
        $this->setTitle(__('Create InnoShip AWB'));
    }

    /**
     * @return Generic
     * @throws LocalizedException
     */
    protected function _prepareForm()
    {
        /** @var MagentoForm $form */
        $form = $this->_formFactory->create(
            [
                'data' =>
                    [
                        'id'     => 'edit_form',
                        'action' => $this->getData('action'),
                        'method' => 'post',
                    ],
            ]
        );

        $this->initDetailsFields(
            $form->addFieldset(
                'awb_details_fieldset',
                [
                    'legend' => __('General Details'),
                ]
            )
        );

        $this->initContentsFields(
            $form->addFieldset(
                'awb_contents_fieldset',
                [
                    'legend' => __('Details of your Package'),
                ]
            )
        );

        $this->initOptionsFields(
            $form->addFieldset(
                'awb_defaults_fieldset',
                [
                    'legend' => __('Other Options'),
                ]
            )
        );

        if ($this->_backendSession->getData('form_data')) {
            $form->setValues($this->_backendSession->getData('form_data'));
            $this->_backendSession->setData('form_data', null);
        } else {
            $items = $this->getOrder()->getAllVisibleItems();

            $weight = 0;
            foreach($items as $item) {
                $weight = $weight + $item->getData('weight') * $item->getData('qty_ordered');
            }

            if($weight < 1){
                $weight = 1;
            }

            // SECURITY: Sanitize form values to prevent XSS
            $form->setValues(
                [
                    'order_id'          => (int)$this->getOrder()->getEntityId(),
                    'shipment_id'       => (int)$this->getShipment()->getEntityId(),
                    'content'           => $this->escapeHtml($this->config->getDefaultOrderContent()),
                    'order_reference'   => $this->escapeHtml(__('Order ID') . ': ' . $this->getOrder()->getIncrementId()),
                    'externalid'        => 1,
                    'insurance_amount'  => $this->getInsuranceAmount(),
                    'payment'           => $this->config->getPayment(),
                    'open_package'      => $this->config->getOpenPackage(),
                    'saturday_delivery' => $this->config->getSaturdayDelivery(),
                    'shipment_date'     => $this->dateTime->gmtDate('Y-m-d'),
                    'envelope_count'    => 0,
                    'parcel_count'      => 1,
                    'pallet_count'      => 0,
                    'total_weight'      => round($weight,1)
                ]
            );
        }

        $form->setUseContainer(true);
        $this->setForm($form);

        return parent::_prepareForm();
    }

    /**
     * @param Fieldset $fieldset
     */
    protected function initDetailsFields(Fieldset $fieldset)
    {
        $fieldset->addField(
            'order_id',
            'hidden',
            [
                'name' => 'order_id',
            ]
        );

        $fieldset->addField(
            'shipment_id',
            'hidden',
            [
                'name' => 'shipment_id',
            ]
        );

        // External location ID
        // SECURITY: Validate and sanitize external IDs to prevent XSS
        $allExternalIds = explode(",",(string)$this->config->getExternalIdSend());
        $showValueList = array();
        foreach($allExternalIds as $extId){
            $extId = trim($extId);
            // Validate external ID format (alphanumeric, spaces, dashes, underscores)
            if (!empty($extId) && preg_match('/^[a-zA-Z0-9 _-]+$/', $extId) && strlen($extId) <= 255) {
                // Escaper is automatically applied by Magento form elements, but we validate format
                $showValueList[$extId] = $extId;
            }
        }

        // Use first valid external ID or empty string
        $defaultExternalId = !empty($showValueList) ? array_key_first($showValueList) : '';

        $fieldset->addField(
            'externalid',
            'select',
            [
                'name'        => 'externalid',
                'label'       => __('Pick up location'),
                'title'       => __('Pick up location'),
                'placeholder' => __('Pick up location'),
                'note'        => __('Pick up location'),
                'required'    => true,
                'value'       => $defaultExternalId,
                'values'      => $showValueList
            ]
        );

        // Content
        $fieldset->addField(
            'content',
            'text',
            [
                'name'        => 'content',
                'label'       => __('Content'),
                'title'       => __('Content'),
                'placeholder' => __('Description of shipment content'),
                'note'        => __('Description of shipment content'),
                'required'    => true,
            ]
        );

        // Order reference
        $fieldset->addField(
            'order_reference',
            'text',
            [
                'name'        => 'order_reference',
                'label'       => __('Order reference'),
                'title'       => __('Order reference'),
                'placeholder' => __('Order reference'),
                'note'        => __('Order reference (default increment oder ID)'),
                'required'    => true,
                'style'       => 'width: 74%',
            ]
        );

        // Insurance amount
        if ($this->config->getInsuranceIncluded()) {
            $fieldset->addField(
                'insurance_amount',
                'text',
                [
                    'name'        => 'insurance_amount',
                    'label'       => __('Insurance Amount'),
                    'title'       => __('Insurance Amount'),
                    'placeholder' => __('Enter the amount to be insured'),
                    'note'        => __('Amount to be insured'),
                    'required'    => true,
                    'style'       => 'width: 74%',
                ]
            );
        }

        // Shipment Date
        $fieldset->addField(
            'shipment_date',
            'date',
            [
                'name'        => 'shipment_date',
                'date_format' => 'yyyy-MM-dd',
                'label'       => __('Shipment Date'),
                'title'       => __('Shipment Date'),
                'required'    => true,
                'style'       => 'width: 74%',
            ]
        );
    }

    /**
     * @param Fieldset $fieldset
     *
     * @throws LocalizedException
     */
    protected function initContentsFields(Fieldset $fieldset)
    {
        $formData = (array) $this->_backendSession->getData('form_data');
        $packages = array_key_exists('packages', $formData) ? $formData['packages'] : [];

        $packagesData = [
            Config\Source\ParcelType::TYPE_PARCEL => [],
            Config\Source\ParcelType::TYPE_PALLET => [],
        ];

        foreach ($packages as $key => $package) {
            $packagesData[$package['type']][] = $package;
        }

        // Envelope count
        $fieldset->addField(
            'envelope_count',
            'text',
            [
                'name'        => 'envelope_count',
                'label'       => __('Envelope Count'),
                'title'       => __('Envelope Count'),
                'placeholder' => __('Please enter the number of envelopes'),
                'note'        => __('Please enter the number of envelopes'),
                'required'    => true,
                'class'       => 'validate-digits js-input-number',
                'style'       => 'width: 74%',
            ]
        );

        // Parcel count
        $field = $fieldset->addField(
            'parcel_count',
            'text',
            [
                'name'        => 'parcel_count',
                'label'       => __('Parcel Count'),
                'title'       => __('Parcel Count'),
                'placeholder' => __('Please enter the number of parcels'),
                'note'        => __('Please enter the number of parcels'),
                'required'    => true,
                'style'       => 'width: 74%',
                'class'       => 'validate-digits js-input-count js-input-number',
            ]
        );

        $field->setAfterElementHtml(
            $this->getLayout()->createBlock(Contents::class)
                ->setData(
                    [
                        'type'     => Config\Source\ParcelType::TYPE_PARCEL,
                        'packages' => $packagesData[Config\Source\ParcelType::TYPE_PARCEL],
                    ]
                )
                ->toHtml()
        );

        // Pallet count
        $field = $fieldset->addField(
            'pallet_count',
            'text',
            [
                'name'        => 'pallet_count',
                'label'       => __('Pallet Count'),
                'title'       => __('Pallet Count'),
                'placeholder' => __('Please enter the number of pallets'),
                'note'        => __('Please enter the number of pallets'),
                'required'    => true,
                'style'       => 'width: 74%',
                'class'       => 'validate-digits js-input-count js-input-number',
            ]
        );

        $field->setAfterElementHtml(
            $this->getLayout()->createBlock(Contents::class)
                ->setData(
                    [
                        'type'     => Config\Source\ParcelType::TYPE_PALLET,
                        'packages' => $packagesData[Config\Source\ParcelType::TYPE_PALLET],
                    ]
                )
                ->toHtml()
        );

        // Totla weight
        $fieldset->addField(
            'total_weight',
            'text',
            [
                'name'        => 'total_weight',
                'label'       => __('Total Weight'),
                'title'       => __('Total Weight'),
                'placeholder' => __('Please enter the total weight (kg)'),
                'note'        => __('Please enter the total weight (kg)'),
                'required'    => true,
                'style'       => 'width: 74%',
                'class'       => 'js-input-weight validate-greater-than-zero js-input-number',
                'step'        => '0.1'
            ]
        );
    }

    /**
     * @param Fieldset $fieldset
     */
    protected function initOptionsFields(Fieldset $fieldset)
    {
        // Payment
        $fieldset->addField(
            'payment',
            'select',
            [
                'name'     => 'payment',
                'label'    => __('Transport payment'),
                'title'    => __('Who is paying'),
                'note'     => __('Who is paying the delivery tax'),
                'values'   => $this->paymentSource->toOptionArray(),
                'required' => true,
                'style'    => 'width: 74%',
            ]
        );

        // Open package
        $fieldset->addField(
            'open_package',
            'select',
            [
                'name'     => 'open_package',
                'label'    => __('Open Package'),
                'title'    => __('Open Package'),
                'note'     => __('Open package on delivery'),
                'values'   => $this->yesNoSource->toOptionArray(),
                'required' => true,
                'style'    => 'width: 74%',
            ]
        );

        // Saturday delivery
        $fieldset->addField(
            'saturday_delivery',
            'select',
            [
                'name'     => 'saturday_delivery',
                'label'    => __('Saturday Delivery'),
                'title'    => __('Saturday Delivery'),
                'note'     => __('Delivery the package on Sunday'),
                'values'   => $this->yesNoSource->toOptionArray(),
                'required' => true,
                'style'    => 'width: 74%',
            ]
        );
    }

    /**
     * @return OrderInterface
     */
    protected function getOrder(): OrderInterface
    {
        return $this->_coreRegistry->registry('current_order');
    }

    /**
     * @return ShipmentInterface
     */
    protected function getShipment(): ShipmentInterface
    {
        return $this->_coreRegistry->registry('current_shipment');
    }

    /**
     * @return string
     */
    protected function getOrderContentList(): string
    {
        $productNames = [];

        foreach ($this->getOrder()->getAllVisibleItems() as $item) {
            $productNames[] = $item->getName();
        }

        return implode(', ', $productNames);
    }

    /**
     * @return float
     */
    protected function getInsuranceAmount(): float
    {
        $amount = 0.0;

        if ($this->config->getInsuranceIncluded()) {
            $amount = $this->getOrder()->getGrandTotal() - $this->getOrder()->getShippingAmount();
        }

        return $amount;
    }
}
