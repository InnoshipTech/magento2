<?php

namespace InnoShip\InnoShip\Model\Api;

use InnoShip\InnoShip\Api\ApiOrderServiceInterface;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\DataObject;
use InnoShip\InnoShip\Model\Api\Rest\Service\Order as Service;

/**
 * Class Order
 * @package InnoShip\InnoShip\Model\Api
 */
class Order implements ApiOrderServiceInterface
{
    /** @var Service */
    protected $service;

    /** @var DataObjectFactory */
    protected $dataObjectFactory;

    /**
     * Order constructor.
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
    public function create(array $data): DataObject
    {
        $response = $this->service->create($data);

        return $this->dataObjectFactory->create(['data' => $response]);
    }

    /**
     * @inheritDoc
     */
    public function delete(int $courierId, string $awb): DataObject
    {
        $response = $this->service->delete($courierId, $awb);

        return $this->dataObjectFactory->create(['data' => $response]);
    }

    public function price(array $data): DataObject
    {
        $response = $this->service->create($data);

        return $this->dataObjectFactory->create(['data' => $response]);
    }
}
