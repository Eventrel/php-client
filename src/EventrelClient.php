<?php

namespace Eventrel\Client;

use Eventrel\Client\Builders\{EventBuilder, BatchEventBuilder};
use Eventrel\Client\Exceptions\EventrelException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;

class EventrelClient
{
    /**
     * The HTTP client instance.
     * @var Client
     */
    private Client $client;

    /**
     * Create a new Eventrel client instance.
     *
     * @param string $apiToken
     * @param string $baseUrl
     * @param string $apiVersion
     * @param int $timeout
     */
    public function __construct(
        protected string $apiToken,
        protected readonly string $baseUrl = 'https://api.eventrel.sh',
        protected string $apiVersion = 'v1',
        protected readonly int $timeout = 30
    ) {
        $this->client = new Client([
            'base_uri' => $this->buildUri(),
            'headers' => [
                'Authorization' => "Bearer {$apiToken}",
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => 'eventrel-php-client/' . $this->version(),
            ],
            'timeout' => $this->timeout,
        ]);
    }

    // public function __get($name)
    // {
    // // Allow dynamic access to services like $client->destinations
    // $serviceClass = __NAMESPACE__ . '\\Services\\' . ucfirst($name) . 'Service';

    // if (class_exists($serviceClass)) {
    //     return new $serviceClass($this);
    // }

    // throw new \InvalidArgumentException("Service {$name} does not exist.");
    // }

    /**
     * Create a new Event builder
     * @param string $eventType
     * @return EventBuilder
     */
    public function event(string $eventType): EventBuilder
    {
        return new EventBuilder($this, $eventType);
    }

    /**
     * Create a new Batch Event builder
     * @param string $eventType
     * @return BatchEventBuilder
     */
    public function batch(string $eventType): BatchEventBuilder
    {
        return new BatchEventBuilder($this, $eventType);
    }

    /**
     * Internal method for making HTTP requests
     *
     * @param string $method
     * @param string $path
     * @param array $options
     * @return ResponseInterface
     */
    public function makeRequest(string $method, string $path, array $options = []): ResponseInterface
    {
        try {
            return $this->client->request($method, $path, $options);
        } catch (RequestException $e) {
            // Log the error or handle it as needed

            // Get the idempotency key if it exists
            // $idempotencyKey = $options['headers']['Idempotency-Key'] ?? null;

            throw new EventrelException(
                message: "Request failed: " . $e->getMessage(),
                code: $e->getCode(),
                previous: $e
            );
        }
    }

    /**
     * Get HTTP client instance for advanced usage
     */
    public function getHttpClient(): Client
    {
        return $this->client;
    }

    /**
     * Get base URL
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Get API token
     */
    public function getApiToken(): string
    {
        return $this->apiToken;
    }


    /**
     * Get the current version of the Eventrel API.
     */
    public function version(): string
    {
        // TODO: Implement version retrieval
        return '1.0.0';
    }

    /**
     * Build the URI for a given API endpoint.
     *
     * @param string $path
     * @return string
     */
    protected function buildUri(string $path = ''): string
    {
        $base = rtrim($this->baseUrl, '/') . '/' . ltrim($this->apiVersion, '/');

        return $base . '/' . ltrim($path, '/');
    }

    /**
     * Generate a unique idempotency key.
     */
    protected function generateIdempotencyKey(): string
    {
        // TODO: Implement idempotency key generation logic

        return bin2hex(random_bytes(16));
    }
}
