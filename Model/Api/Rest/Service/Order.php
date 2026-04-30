<?php

namespace InnoShip\InnoShip\Model\Api\Rest\Service;

use Psr\Http\Message\ResponseInterface;
use InnoShip\InnoShip\Api\ServiceInterface;
use InnoShip\InnoShip\Model\Api\Rest\Service;

/**
 * Class Order
 * @package InnoShip\InnoShip\Model\Api\Rest\Service
 */
class Order
{
    /** @var Service */
    protected $service;

    /**
     * Order constructor.
     *
     * @param Service $service
     */
    public function __construct(Service $service)
    {
        $this->service = $service;
    }

    /**
     * @param array $data
     *
     * @return array|ResponseInterface
     * @throws \Exception
     */
    public function create(array $data)
    {
        return $this->service->makeRequest('/api/Order', $data, ServiceInterface::POST);
    }

    /**
     * @param array $data
     *
     * @return array|ResponseInterface
     * @throws \Exception
     */
    public function price(array $data)
    {
        return $this->service->makeRequest('/api/Price', $data, ServiceInterface::POST);
    }

    /**
     * @param int    $courierId
     * @param string $awb
     *
     * @return array|ResponseInterface
     * @throws \Exception
     */
    public function delete(int $courierId, string $awb)
    {
        return $this->service->makeRequest("/api/Order/$courierId/awb/$awb", [], ServiceInterface::DELETE);
    }
}
