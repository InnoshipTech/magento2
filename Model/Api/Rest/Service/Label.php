<?php

namespace InnoShip\InnoShip\Model\Api\Rest\Service;

use Psr\Http\Message\ResponseInterface;
use InnoShip\InnoShip\Api\ServiceInterface;
use InnoShip\InnoShip\Model\Api\Rest\Service;
use InnoShip\InnoShip\Model\Config;

/**
 * Class Label
 * @package InnoShip\InnoShip\Model\Api\Rest\Service
 */
class Label
{
    protected $service;
    protected $config;

    /**
     * Order constructor.
     *
     * @param Service $service
     * @param Config $config
     */
    public function __construct(Service $service,Config $config)
    {
        $this->service = $service;
        $this->config = $config;
    }

    /**
     * @param int    $courierId
     * @param string $awb
     * @param string $filePath
     *
     * @return array|ResponseInterface
     * @throws \Exception
     */
    public function get(int $courierId, string $awb, ?string $filePath = null)
    {

        return $this->service->makeRequest("/api/Label/by-courier/$courierId/awb/$awb?type=pdf&format=".(string)$this->config->getLabelformat(), [], ServiceInterface::GET, $filePath);
    }
}
