<?php

namespace InnoShip\InnoShip\Model\Api\Rest;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
use InnoShip\InnoShip\Api\ServiceInterface;
use InnoShip\InnoShip\Logger\Logger;
use InnoShip\InnoShip\Model\Api\Exceptions\ApiKeyException;
use InnoShip\InnoShip\Model\Api\Exceptions\ApiRequestException;
use InnoShip\InnoShip\Model\Api\Exceptions\ApiVersionException;
use InnoShip\InnoShip\Model\Api\Exceptions\ApiEndPointException;
use InnoShip\InnoShip\Model\Config;

/**
 * Class Service
 * @package InnoShip\InnoShip\Model\Api\Rest
 */
class Service implements ServiceInterface
{
    /** @var string */
    const API_KEY_HEADER_KEY = 'X-Api-Key';

    /** @var string */
    const API_VERSION_HEADER_KEY = 'api-version';

    /** @var Logger */
    protected $logger;

    /** @var Config */
    protected $config;

    /** @var Client */
    protected $client;

    /** @var array */
    protected $headers;

    /**
     * Service constructor.
     *
     * @param Config $config
     * @param Logger $logger
     */
    public function __construct(Config $config, Logger $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->client = new Client();
    }

    /**
     * @inheritdoc
     */
    public function setHeader(string $header, ?string $value = null)
    {
        if (null !== $value) {
            unset($this->headers[$header]);
        }

        $this->headers[$header] = $value;
    }

    /**
     * @param string      $url
     * @param array       $body
     * @param string      $method
     * @param string|null $sink
     *
     * @return array|ResponseInterface
     * @throws \Exception
     */
    public function makeRequest(string $url, array $body = [], string $method = self::POST, ?string $sink = null)
    {
        $response = [
            'success' => false,
        ];

        try {
            if (is_null($this->config->getGatewayUrl())) {
                throw new ApiEndPointException("Invalid API EndPoint!");
            }

            // SECURITY FIX: Validate URL doesn't contain malicious characters
            if (!$this->isValidApiUrl($url)) {
                throw new ApiEndPointException("Invalid API URL format!");
            }

            $this->getInnoShipHeaders();

            $data = [
                RequestOptions::HEADERS => $this->headers,
                RequestOptions::JSON    => $body,
            ];

            // Save to file the body of response
            if (null !== $sink) {
                $data[RequestOptions::SINK] = $sink;
            }

            /** @var ResponseInterface $response */
            $response = $this->client->request($method, $this->config->getGatewayUrl() . $url, $data);

            // SECURITY FIX: Validate HTTP status code before processing
            $statusCode = $response->getStatusCode();
            if ($statusCode < 200 || $statusCode >= 600) {
                throw new ApiRequestException(__('Invalid HTTP status code: %1', $statusCode));
            }

            $response = $this->processResponse($response);

            // SECURITY FIX: Validate response structure
            if (!$this->validateResponseStructure($response)) {
                throw new ApiRequestException(__('Invalid API response structure'));
            }

            $response['success'] = true;
        } catch (BadResponseException $exception) {
            $this->logger->error('Bad Response: ' . $exception->getMessage());
            // SECURITY FIX: Don't log request body (may contain sensitive data)

            // SECURITY FIX: Ensure $response is an array before using it
            if (!is_array($response)) {
                $response = [
                    'success' => false,
                ];
            }

            $response['status_code']       = $exception->getCode();
            $response['status_message']    = $exception->getMessage();

            $response = $this->processResponse($response);

            if ($exception->hasResponse()) {
                $errorResponse = $exception->getResponse();
                $this->logger->error($errorResponse->getStatusCode() . ' ' . $errorResponse->getReasonPhrase());
            }
        } catch (\Exception $exception) {
            $this->logger->error('Exception: ' . $exception->getMessage());

            // SECURITY FIX: Ensure $response is an array before using it
            if (!is_array($response)) {
                $response = [
                    'success' => false,
                ];
            }

            $response['status_code']    = $exception->getCode();
            $response['status_message'] = $exception->getMessage();
        }

        $this->logRequestResponse($url, $body, $response);

        return $response;
    }

    /**
     * Validate API URL format
     *
     * @param string $url
     * @return bool
     */
    protected function isValidApiUrl($url)
    {
        // Check for null bytes, newlines, and other control characters
        if (preg_match('/[\x00-\x1F\x7F]/', $url)) {
            return false;
        }

        // URL must start with /api/
        if (strpos($url, '/api/') !== 0) {
            return false;
        }

        // Check for double slashes (except in protocol)
        if (preg_match('/\/\//', substr($url, 1))) {
            return false;
        }

        return true;
    }

    /**
     * Validate response structure
     *
     * @param mixed $response
     * @return bool
     */
    protected function validateResponseStructure($response)
    {
        // Response must be an array
        if (!is_array($response)) {
            return false;
        }

        // Response must contain status_code
        if (!isset($response['status_code'])) {
            return false;
        }

        // Status code must be numeric
        if (!is_numeric($response['status_code'])) {
            return false;
        }

        return true;
    }

    /**
     * @throws ApiKeyException
     * @throws ApiVersionException
     */
    protected function getInnoShipHeaders()
    {
        if (is_null($this->config->getApiKey())) {
            throw new ApiKeyException("Invalid API Key!");
        }

        if (is_null($this->config->getApiVersion())) {
            throw new ApiVersionException("Invalid API Version!");
        }

        $this->setHeader(self::API_KEY_HEADER_KEY, $this->config->getApiKey());
        $this->setHeader(self::API_VERSION_HEADER_KEY, $this->config->getApiVersion());
        $this->setHeader('User-Agent', 'InnoShipMagentoModule-v1.0');
    }

    /**
     * Process the response and return an array
     *
     * @param ResponseInterface|array $response
     *
     * @return array
     * @throws \Exception
     */
    protected function processResponse($response)
    {
        if (is_array($response)) {
            return $response;
        }

        // CRITICAL FIX: Read body only once to prevent memory exhaustion
        // Guzzle streams are read-once, reading twice causes issues
        $bodyContent = '';
        try {
            $bodyContent = (string) $response->getBody();
            $data = json_decode($bodyContent, true);
        } catch (\Exception $exception) {
            $data = [
                'exception' => $exception->getMessage(),
            ];
        }

        if ($response->getStatusCode() === 401) {
            throw new ApiRequestException(__($response->getReasonPhrase()));
        }

        if ($response->getStatusCode() === 400) {
            throw new ApiRequestException(__($response->getReasonPhrase()));
        }

        $data['response'] = [
            'headers' => $response->getHeaders(),
            'body'    => $bodyContent,  // Use already-read content
        ];

        if ( ! array_key_exists('status_code', $data)) {
            $data['status_code'] = $response->getStatusCode();
        }

        if ( ! array_key_exists('status_message', $data)) {
            $data['status_message'] = $response->getReasonPhrase();
        }

        return $data;
    }

    /**
     * @param $url
     * @param $request
     * @param $response
     */
    protected function logRequestResponse($url, $request, $response): void
    {
        if ( ! $this->config->getDebug()) {
            return;
        }

        // SECURITY: Sanitize headers to prevent API key exposure
        $sanitizedHeaders = $this->sanitizeHeaders($this->headers);

        $context = [
            'headers' => $sanitizedHeaders,
            'action'  => $url,
        ];

        $context['body'] = $this->sanitizeSensitiveData($request);
        $this->logger->debug('REQUEST', $context);

        $context['body'] = $this->sanitizeSensitiveData($response);
        $this->logger->debug('RESPONSE', $context);
    }

    /**
     * Sanitize headers to mask sensitive information
     *
     * @param array $headers
     * @return array
     */
    protected function sanitizeHeaders(array $headers): array
    {
        $sensitiveKeys = [
            self::API_KEY_HEADER_KEY,
            'X-Api-Key',
            'Authorization',
            'Auth-Token',
            'Access-Token'
        ];

        $sanitized = $headers;
        foreach ($sensitiveKeys as $key) {
            if (isset($sanitized[$key])) {
                // Show only first 4 characters for debugging purposes
                $value = $sanitized[$key];
                if (strlen($value) > 4) {
                    $sanitized[$key] = substr($value, 0, 4) . str_repeat('*', strlen($value) - 4);
                } else {
                    $sanitized[$key] = '***REDACTED***';
                }
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize sensitive data from request/response body
     *
     * @param mixed $data
     * @return mixed
     */
    protected function sanitizeSensitiveData($data)
    {
        if (!is_array($data)) {
            return $data;
        }

        $sensitiveFields = [
            'password',
            'apiKey',
            'api_key',
            'token',
            'access_token',
            'creditCard',
            'credit_card',
            'cvv',
            'ssn',
            'secret'
        ];

        $sanitized = $data;
        array_walk_recursive($sanitized, function (&$value, $key) use ($sensitiveFields) {
            foreach ($sensitiveFields as $sensitiveField) {
                if (stripos($key, $sensitiveField) !== false) {
                    $value = '***REDACTED***';
                    break;
                }
            }
        });

        return $sanitized;
    }
}
