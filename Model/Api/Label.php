<?php

namespace InnoShip\InnoShip\Model\Api;

use Magento\Framework\DataObject;
use InnoShip\InnoShip\Api\ApiLabelServiceInterface;
use Magento\Framework\DataObjectFactory;
use InnoShip\InnoShip\Model\Api\Rest\Service\Label as Service;

/**
 * Class Label
 * @package InnoShip\InnoShip\Model\Api
 */
class Label implements ApiLabelServiceInterface
{
    /** @var Service */
    protected $service;

    /** @var DataObjectFactory */
    protected $dataObjectFactory;

    /**
     * Label constructor.
     *
     * @param Service           $service
     * @param DataObjectFactory $dataObjectFactory
     */
    public function __construct(Service $service, DataObjectFactory $dataObjectFactory)
    {
        $this->service           = $service;
        $this->dataObjectFactory = $dataObjectFactory;
    }

    /**
     * @inheritDoc
     */
    public function get(int $courierId, string $awb, ?string $filePath = null): DataObject
    {
        $response = $this->service->get($courierId, $awb, $filePath);
        return $this->dataObjectFactory->create(['data' => $response]);
    }
}
