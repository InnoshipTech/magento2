<?php
declare(strict_types=1);

namespace InnoShip\InnoShip\Model\Api;

use Magento\Framework\HTTP\ZendClient;
use Zend_Http_Client_Exception;
use Psr\Http\Message\ResponseInterface;
use InnoShip\InnoShip\Api\ServiceInterface;
use InnoShip\InnoShip\Model\Api\Rest\Service;

/**
 * Class GetPudo
 *
 * Add method to get list of pudos.
 */
class GetPudo
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
    public function getPudoList(array $data)
    {
        return $this->service->makeRequest('/api/Location/FixedLocations?ShowInactive=false', $data, ServiceInterface::GET);
    }
}
