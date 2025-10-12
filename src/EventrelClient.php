<?php

namespace Eventrel\Client;

use Composer\InstalledVersions;
use Eventrel\Client\Builders\{EventBuilder, BatchEventBuilder};
use Eventrel\Client\Exceptions\EventrelException;
use Eventrel\Client\Services\{EventService, DestinationService, IdempotencyService};
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;

/**
 * Eventrel API Client
 * 
 * Main client class for interacting with the Eventrel API.
 * Provides fluent builders for events and direct service access.
 * 
 * @property-read IdempotencyService $idempotency Access to idempotency key generation
 * @property-read EventService $events Access to event management operations
 * @property-read DestinationService $destinations Access to destination management operations
 * 
 * @package Eventrel\Client
 */
class EventrelClient
{
    /**
     * Map of service names to their class implementations
     * 
     * @var array<string, class-string>
     */
    private const SERVICE_MAP = [
        'idempotency' => IdempotencyService::class,
        'events' => EventService::class,
        'destinations' => DestinationService::class,
    ];

    /**
     * Guzzle HTTP client instance
     * 
     * @var Client
     */
    private Client $client;

    /**
     * Cached service instances for singleton pattern
     * 
     * @var array<string, object>
     */
    private array $serviceInstances = [];

    /**
     * Create a new Eventrel API client
     * 
     * @param string $apiToken Your Eventrel API token
     * @param string $apiVersion API version to use (default: 'v1')
     * @param string $baseUrl Base URL for the API (default: 'https://api.eventrel.sh')
     * @param int $timeout Request timeout in seconds (default: 30)
     */
    public function __construct(
        protected string $apiToken,
        protected string $apiVersion = 'v1',
        protected readonly string $baseUrl = 'https://api.eventrel.sh',
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

    /**
     * Dynamically access services via magic getter
     * 
     * Provides lazy-loaded singleton access to service instances.
     * Services are instantiated once and cached for subsequent calls.
     * 
     * @param string $name The service name (e.g., 'events', 'destinations')
     * @return object The service instance
     * @throws \InvalidArgumentException If the requested service doesn't exist
     * 
     * @example
     * $client->events->create(...);
     * $client->destinations->create(...);
     */
    public function __get(string $name)
    {
        if (!isset(self::SERVICE_MAP[$name])) {
            throw new \InvalidArgumentException(
                "Service '{$name}' does not exist. Available services: " .
                    implode(', ', array_keys(self::SERVICE_MAP))
            );
        }

        if (!isset($this->serviceInstances[$name])) {
            $serviceClass = self::SERVICE_MAP[$name];
            $this->serviceInstances[$name] = new $serviceClass($this);
        }

        return $this->serviceInstances[$name];
    }

    /**
     * Create a new Event builder for fluent API usage
     * 
     * @param string $eventType The type of event to send
     * @return EventBuilder Fluent builder instance
     * 
     * @example
     * $client->event('user.created')
     *     ->payload(['email' => 'user@example.com'])
     *     ->tag('signup')
     *     ->send();
     */
    public function event(string $eventType): EventBuilder
    {
        return new EventBuilder($this, $eventType);
    }

    /**
     * Create a new Batch Event builder for fluent API usage
     * 
     * @param string $eventType The shared event type for all events in the batch
     * @return BatchEventBuilder Fluent builder instance
     * 
     * @example
     * $client->eventBatch('user.activity')
     *     ->addEvent(['action' => 'login'])
     *     ->addEvent(['action' => 'logout'])
     *     ->send();
     */
    public function eventBatch(string $eventType): BatchEventBuilder
    {
        return new BatchEventBuilder($this, $eventType);
    }

    /**
     * Make an HTTP request to the Eventrel API
     * 
     * Internal method used by services to communicate with the API.
     * Handles response validation and error transformation.
     * 
     * @param string $method HTTP method (GET, POST, PUT, DELETE, etc.)
     * @param string $path API endpoint path (e.g., 'events', 'destinations')
     * @param array<string, mixed> $options Guzzle request options
     * @return ResponseInterface The HTTP response
     * @throws EventrelException If the request fails or returns an error
     */
    public function makeRequest(string $method, string $path, array $options = []): ResponseInterface
    {
        try {
            $response = $this->client->request($method, $path, $options);

            $this->validateResponse($response);

            return $response;
        } catch (RequestException $e) {
            $this->handleRequestException($e, $options);
        }
    }

    /**
     * Get the underlying Guzzle HTTP client
     * 
     * Provides access to the HTTP client for advanced usage scenarios.
     * Use with caution as direct client access bypasses error handling.
     * 
     * @return Client The Guzzle HTTP client instance
     */
    public function getHttpClient(): Client
    {
        return $this->client;
    }

    /**
     * Get the configured base URL
     * 
     * @return string The API base URL
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Get the configured API token
     * 
     * @return string The API token
     */
    public function getApiToken(): string
    {
        return $this->apiToken;
    }

    /**
     * Get the current client library version
     * 
     * @return string Version string (e.g., '1.0.0')
     */
    public function version(): string
    {
        try {
            $version = InstalledVersions::getVersion('eventrel/client');

            return $version ? ltrim($version, 'v') : 'dev-main';
        } catch (\Exception $e) {
            // Fallback for development or when package info unavailable
            return 'dev-main';
        }
    }

    /**
     * Build the full URI for an API endpoint
     * 
     * Combines base URL, API version, and path into a complete URI.
     * 
     * @param string $path The endpoint path (optional)
     * @return string The complete URI
     */
    protected function buildUri(string $path = ''): string
    {
        $base = rtrim($this->baseUrl, '/') . '/' . ltrim($this->apiVersion, '/');

        return $base . '/' . ltrim($path, '/');
    }

    /**
     * Validate an API response
     * 
     * Checks if the response status code indicates success (2xx range).
     * Extracts and throws structured errors for failed responses.
     * 
     * @param ResponseInterface $response The HTTP response to validate
     * @return void
     * @throws EventrelException If the response indicates an error
     */
    protected function validateResponse(ResponseInterface $response): void
    {
        $statusCode = $response->getStatusCode();

        if ($statusCode < 200 || $statusCode >= 300) {
            $body = json_decode($response->getBody()->getContents(), true);
            $message = $body['message'] ?? $body['error'] ?? 'Unknown error occurred';

            throw new EventrelException(
                "API returned error: {$message}",
                $statusCode
            );
        }
    }

    /**
     * Handle Guzzle request exceptions
     * 
     * Transforms Guzzle exceptions into structured EventrelException instances.
     * Extracts error messages from API responses when available and includes
     * idempotency key in error context for debugging.
     * 
     * @param RequestException $e The Guzzle request exception
     * @param array<string, mixed> $options The original request options
     * @return never This method always throws
     * @throws EventrelException Always thrown with structured error information
     */
    protected function handleRequestException(RequestException $e, array $options): never
    {
        $statusCode = $e->getCode();
        $message = $e->getMessage();

        if ($e->hasResponse()) {
            $response = $e->getResponse();
            $body = json_decode($response->getBody()->getContents(), true);
            $message = $body['message'] ?? $body['error'] ?? $message;
            $statusCode = $response->getStatusCode();
        }

        $idempotencyKey = $options['headers']['X-Idempotency-Key'] ?? null;

        if ($idempotencyKey) {
            $message .= " (Idempotency-Key: {$idempotencyKey})";
        }

        throw new EventrelException(
            "Request failed: {$message}",
            $statusCode,
            $e
        );
    }
}
