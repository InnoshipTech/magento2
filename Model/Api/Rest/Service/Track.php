<?php

namespace InnoShip\InnoShip\Model\Api\Rest\Service;

use Psr\Http\Message\ResponseInterface;
use InnoShip\InnoShip\Api\ServiceInterface;
use InnoShip\InnoShip\Model\Api\Rest\Service;

/**
 * Class Track
 * @package InnoShip\InnoShip\Model\Api\Rest\Service
 */
class Track
{
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
    public function get(array $data)
    {
        return $this->service->makeRequest('/api/Track/by-awb/with-return', $data, ServiceInterface::POST);
    }
}
