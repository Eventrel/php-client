<?php

namespace Eventrel\Services;

use Eventrel\Builders\DestinationBuilder;
use Eventrel\Entities\Destination;
use Eventrel\Enums\WebhookMode;
use Eventrel\EventrelClient;
use Eventrel\Exceptions\EventrelException;
use Eventrel\Responses\{DestinationResponse, DestinationListResponse};
use Psr\Http\Message\ResponseInterface;

/**
 * Service for managing destinations via the Eventrel API.
 * 
 * Provides methods to create, retrieve, update, delete, and list webhook
 * destinations where events are delivered. Supports configuration of
 * webhook settings, rate limiting, retries, and event filtering.
 * 
 * @package Eventrel\Services
 */
class DestinationService
{
    /**
     * DestinationService constructor.
     * 
     * @param EventrelClient $client The Eventrel client instance
     */
    public function __construct(
        private EventrelClient $client
    ) {
        //
    }

    /**
     * Get a new DestinationBuilder instance for fluent destination construction.
     * 
     * @return DestinationBuilder
     */
    public function builder(): DestinationBuilder
    {
        return new DestinationBuilder($this->client);
    }

    /**
     * Create a new destination
     * 
     * Creates a webhook endpoint where events will be delivered.
     * The destination can be configured with webhook mode, delivery settings,
     * rate limiting, and custom configuration.
     * 
     * @param string $name Human-readable name for the destination
     * @param string $webhookUrl The webhook URL where events will be sent
     * @param WebhookMode|string $webhookMode Webhook mode: 'bidirectional', 'outbound', or 'inbound'
     * @param string|null $description Optional description of the destination
     * @param array<string, string> $headers Custom headers to include with every request
     * @param array<string, mixed> $metadata Additional metadata for the destination
     * @param array<string, mixed> $webhookConfig Webhook delivery configuration
     * @param int|null $timeout Request timeout in seconds (default: 30)
     * @param int|null $retryLimit Maximum retry attempts (default: 3)
     * @param int|null $rateLimitPerMinute Max requests per minute (null = unlimited)
     * @param int|null $rateLimitPerHour Max requests per hour (null = unlimited)
     * @param int|null $rateLimitPerDay Max requests per day (null = unlimited)
     * @param bool $isActive Whether the destination is active (default: true)
     * @return DestinationResponse
     * @throws EventrelException
     * 
     * @example
     * // Simple outbound destination
     * $destination = $client->destinations->create(
     *     name: 'Production API Webhook',
     *     webhookUrl: 'https://api.example.com/webhooks',
     *     webhookMode: 'outbound'
     * );
     * 
     * @example
     * // Bidirectional with full configuration
     * $destination = $client->destinations->create(
     *     name: 'Analytics Dashboard',
     *     webhookUrl: 'https://analytics.example.com/webhooks',
     *     webhookMode: 'bidirectional',
     *     description: 'Main analytics webhook endpoint',
     *     headers: [
     *         'Authorization' => 'Bearer secret_token',
     *         'X-API-Version' => 'v1'
     *     ],
     *     metadata: [
     *         'environment' => 'production',
     *         'team' => 'platform',
     *         'business_critical' => true
     *     ],
     *     webhookConfig: [
     *         'batch_size' => 50,
     *         'verify_ssl' => true,
     *         'event_filtering' => [
     *             'enabled' => true,
     *             'allowed_events' => ['user.created', 'user.updated']
     *         ],
     *         'delivery_strategy' => 'batched',
     *         'signature_algorithm' => 'sha256',
     *         'dead_letter_queue' => true,
     *         'timestamp_tolerance' => 300
     *     ],
     *     timeout: 45,
     *     retryLimit: 5,
     *     rateLimitPerMinute: 1000,
     *     rateLimitPerHour: 50000,
     *     isActive: true
     * );
     * 
     * @example
     * // With event filtering
     * $destination = $client->destinations->create(
     *     name: 'Payment Events Only',
     *     webhookUrl: 'https://payments.example.com/webhooks',
     *     webhookMode: 'outbound',
     *     webhookConfig: [
     *         'event_filtering' => [
     *             'enabled' => true,
     *             'allowed_events' => [
     *                 'payment.completed',
     *                 'payment.failed',
     *                 'payment.refunded'
     *             ]
     *         ]
     *     ]
     * );
     * 
     * echo "Destination created: {$destination->getId()}";
     * echo "Webhook secret: {$destination->getDestination()->webhookSecret}";
     */
    public function create(
        string $name,
        string $webhookUrl,
        WebhookMode|string $webhookMode = 'outbound',
        ?string $description = null,
        array $headers = [],
        array $metadata = [],
        array $webhookConfig = [],
        ?int $timeout = null,
        ?int $retryLimit = null,
        ?int $rateLimitPerMinute = null,
        ?int $rateLimitPerHour = null,
        ?int $rateLimitPerDay = null,
        bool $isActive = true,
    ): DestinationResponse {
        $webhookMode = $webhookMode instanceof WebhookMode
            ? $webhookMode->value
            : strtolower($webhookMode);

        $data = array_filter([
            'name' => $name,
            'webhook_url' => $webhookUrl,
            'webhook_mode' => $webhookMode,
            'description' => $description,
            'headers' => !empty($headers) ? $headers : null,
            'metadata' => !empty($metadata) ? $metadata : null,
            'webhook_config' => !empty($webhookConfig) ? $webhookConfig : null,
            'timeout' => $timeout,
            'retry_limit' => $retryLimit,
            'rate_limit_per_minute' => $rateLimitPerMinute,
            'rate_limit_per_hour' => $rateLimitPerHour,
            'rate_limit_per_day' => $rateLimitPerDay,
            'is_active' => $isActive,
        ], fn($value) => $value !== null);

        $response = $this->request('POST', 'destinations', $data);

        return new DestinationResponse($response);
    }

    /**
     * Get a single destination by UUID
     * 
     * Retrieves the full details of a destination including its configuration,
     * delivery statistics, and current status.
     * 
     * @param string $uuid The destination UUID
     * @param bool $asDestination Whether to return as a Destination entity (default: false)
     * @return DestinationResponse|Destination
     * @throws EventrelException
     * 
     * @example
     * $destination = $client->destinations->get('dest_abc123');
     * 
     * echo "Destination: {$destination->getName()}";
     * echo "URL: {$destination->getUrl()}";
     * echo "Status: " . ($destination->isEnabled() ? 'Active' : 'Disabled');
     */
    public function get(string $uuid, bool $asDestination = false): DestinationResponse|Destination
    {
        $response = $this->request('GET', "destinations/{$uuid}");

        $destinationResponse = new DestinationResponse($response, true);

        if ($asDestination) {
            return $destinationResponse->getDetails();
        }

        return $destinationResponse;
    }

    /**
     * Update an existing destination
     * 
     * Updates one or more properties of a destination. Only the fields
     * you provide will be updated; others remain unchanged.
     * 
     * @param string $uuid The destination UUID to update
     * @param array<string, mixed> $data The fields to update
     *   - url: string - New webhook URL
     *   - name: string - New name
     *   - description: string|null - New description
     *   - headers: array - Custom headers
     *   - enabled: bool - Enable/disable the destination
     *   - metadata: array - Additional metadata
     * @param bool $asDestination Whether to return as a Destination entity (default: false)
     * @return DestinationResponse|Destination
     * @throws EventrelException
     * 
     * @example
     * // Update URL only
     * $destination = $client->destinations->update(
     *     uuid: 'dest_abc123',
     *     data: ['url' => 'https://new-api.example.com/webhooks']
     * );
     * 
     * @example
     * // Disable a destination
     * $destination = $client->destinations->update(
     *     uuid: 'dest_abc123',
     *     data: ['enabled' => false]
     * );
     * 
     * @example
     * // Update multiple fields
     * $destination = $client->destinations->update(
     *     uuid: 'dest_abc123',
     *     data: [
     *         'name' => 'Updated Name',
     *         'description' => 'Updated description',
     *         'headers' => [
     *             'Authorization' => 'Bearer new_token'
     *         ]
     *     ]
     * );
     */
    public function update(string $uuid, array $data, bool $asDestination = false): DestinationResponse|Destination
    {
        $response = $this->request('PATCH', "destinations/{$uuid}", $data);

        $destinationResponse = new DestinationResponse($response, true);

        if ($asDestination) {
            return $destinationResponse->getDetails();
        }

        return $destinationResponse;
    }

    /**
     * Delete a destination
     * 
     * Permanently deletes a destination. Events targeting this destination
     * will fail after deletion. This action cannot be undone.
     * 
     * @param string $uuid The destination UUID to delete
     * @return DestinationResponse The deleted destination details
     * @throws EventrelException
     * 
     * @example
     * $client->destinations->delete('dest_abc123');
     * echo "Destination deleted successfully";
     * 
     * @example
     * // Get details before deletion
     * $destination = $client->destinations->delete('dest_abc123');
     * echo "Deleted destination: {$destination->getName()}";
     */
    public function delete(string $uuid): DestinationResponse
    {
        $response = $this->request('DELETE', "destinations/{$uuid}");

        return new DestinationResponse($response);
    }

    /**
     * List all destinations with optional filters and pagination
     * 
     * Retrieves a paginated list of destinations with optional filtering
     * by status, name, or other criteria.
     * 
     * @param int $page Page number (default: 1)
     * @param int $perPage Items per page (default: 15)
     * @param bool|null $enabled Filter by enabled status (null = all)
     * @param array<string, mixed> $additionalFilters Additional query filters
     *   - search: string - Search by name or URL
     *   - sort_by: string - Sort field (name, created_at, etc.)
     *   - sort_order: string - Sort direction (asc, desc)
     * @return DestinationListResponse
     * @throws EventrelException
     * 
     * @example
     * // Simple list - first page
     * $response = $client->destinations->list();
     * 
     * foreach ($response->get() as $destination) {
     *     echo "{$destination->name}: {$destination->url}\n";
     * }
     * 
     * @example
     * // With pagination
     * $response = $client->destinations->list(page: 2, perPage: 50);
     * 
     * @example
     * // Filter by status
     * $activeDestinations = $client->destinations->list(enabled: true);
     * $disabledDestinations = $client->destinations->list(enabled: false);
     * 
     * @example
     * // With search and sorting
     * $response = $client->destinations->list(
     *     perPage: 25,
     *     additionalFilters: [
     *         'search' => 'production',
     *         'sort_by' => 'created_at',
     *         'sort_order' => 'desc'
     *     ]
     * );
     * 
     * @example
     * // Paginate through all destinations
     * $page = 1;
     * do {
     *     $response = $client->destinations->list(page: $page);
     *     
     *     foreach ($response->getDestinations() as $destination) {
     *         // Process destination
     *     }
     *     
     *     $page++;
     * } while ($response->hasMorePages());
     */
    public function list(
        int $page = 1,
        int $perPage = 15,
        ?bool $enabled = null,
        array $additionalFilters = []
    ): DestinationListResponse {
        $filters = array_merge(
            array_filter([
                'page' => $page,
                'per_page' => $perPage,
                'enabled' => $enabled,
            ], fn($value) => $value !== null),
            $additionalFilters
        );

        $response = $this->request('GET', 'destinations', query: $filters);

        return new DestinationListResponse($response);
    }

    /**
     * Make an HTTP request with consistent options
     * 
     * Internal wrapper around EventrelClient::makeRequest() that standardizes
     * how requests are made across the service.
     * 
     * @param string $method HTTP method (GET, POST, PATCH, DELETE)
     * @param string $path API endpoint path relative to base URL
     * @param array<string, mixed> $data Request body data (automatically JSON-encoded)
     * @param array<string, mixed> $query Optional query string parameters
     * @return ResponseInterface The HTTP response from the API
     * @throws EventrelException If the request fails or API returns an error
     */
    private function request(
        string $method,
        string $path,
        array $data = [],
        array $query = []
    ): ResponseInterface {
        $options = [];

        if (!empty($data)) {
            $options['json'] = $data;
        }

        if (!empty($query)) {
            $options['query'] = $query;
        }

        return $this->client->makeRequest($method, $path, $options);
    }
}
