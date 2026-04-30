<?php

namespace InnoShip\InnoShip\Api;

/**
 * Interface ServiceInterface
 * @package InnoShip\InnoShip\Api
 */
interface ServiceInterface
{
    /**
     * The value for the HTTP POST request
     */
    const POST = 'post';

    /**
     * The value for the HTTP GET request
     */
    const GET = 'get';

    /**
     * The value for the HTTP DELETE request
     */
    const DELETE = 'delete';

    /**
     * Make API call
     *
     * @param string      $url
     * @param array       $body
     * @param string      $method HTTP request type
     * @param string|null $sink
     *
     * @return array Response body from API call
     */
    public function makeRequest(string $url, array $body = [], string $method = self::POST, ?string $sink = null);

    /**
     * @param string      $header
     * @param string|null $value
     *
     * @return mixed
     */
    public function setHeader(string $header, ?string $value = null);
}
