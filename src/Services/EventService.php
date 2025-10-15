<?php

namespace Eventrel\Services;

use Carbon\Carbon;
use Eventrel\Builders\EventBuilder;
use Eventrel\Entities\OutboundEvent;
use Eventrel\EventrelClient;
use Eventrel\Exceptions\EventrelException;
use Eventrel\Responses\{EventResponse, BatchEventResponse, BulkRetryResponse, EventListResponse};
use Psr\Http\Message\ResponseInterface;

/**
 * Service for managing events via the Eventrel API.
 * 
 * Provides methods to create, retrieve, list, retry, and cancel events.
 * Supports both single event operations and batch processing.
 * 
 * @package Eventrel\Services
 */
class EventService
{
    /**
     * EventService constructor.
     * 
     * @param \Eventrel\EventrelClient $client
     */
    public function __construct(
        private EventrelClient $client
    ) {
        // 
    }

    /**
     * Get a new EventBuilder instance for fluent event construction.
     * 
     * @return EventBuilder
     */
    public function builder(): EventBuilder
    {
        return new EventBuilder($this->client);
    }

    /**
     * Create and send a single event
     * 
     * Creates a new event with the specified type, payload, and destination.
     * The event will be queued for delivery to the specified destination endpoint.
     * Optionally supports scheduling for future delivery and idempotency keys to
     * prevent duplicate event creation.
     * 
     * @param string $eventType The event type identifier (e.g., 'user.created', 'order.completed')
     * @param array<string, mixed> $payload The event payload data
     * @param string $destination The destination UUID or identifier where the event should be delivered
     * @param array<int, string> $tags Optional tags for categorizing and filtering events (default: [])
     * @param string|null $idempotencyKey Optional idempotency key to prevent duplicate events (auto-generated if null)
     * @param Carbon|null $scheduledAt Optional scheduled delivery time (default: immediate delivery)
     * @param bool $asOutboundEvent If true, returns OutboundEvent entity instead of EventResponse (default: false)
     * @return EventResponse|OutboundEvent The event creation response containing event details and status
     * @throws EventrelException If the request fails or API returns an error
     * 
     * @example
     * // Simple event creation
     * $response = $client->events->create(
     *     eventType: 'user.created',
     *     payload: ['email' => 'user@example.com', 'name' => 'John Doe'],
     *     destination: 'dest_abc123'
     * );
     * 
     * @example
     * // With tags for filtering
     * $response = $client->events->create(
     *     eventType: 'order.completed',
     *     payload: ['order_id' => 12345, 'amount' => 99.99],
     *     destination: 'dest_abc123',
     *     tags: ['premium', 'high-value']
     * );
     * 
     * @example
     * // Scheduled event with idempotency
     * $response = $client->events->create(
     *     eventType: 'reminder.email',
     *     payload: ['user_id' => 456, 'template' => 'weekly-summary'],
     *     destination: 'dest_email_service',
     *     tags: ['reminder'],
     *     idempotencyKey: 'weekly-reminder-user-456-2025-10-19',
     *     scheduledAt: Carbon::parse('2025-10-19 09:00:00')
     * );
     * 
     * @example
     * // Content-based idempotency for payment events
     * $paymentData = [
     *     'amount' => 10000,
     *     'currency' => 'USD',
     *     'customer_id' => 'cust_123'
     * ];
     * 
     * $response = $client->events->create(
     *     eventType: 'payment.charge',
     *     payload: $paymentData,
     *     destination: 'dest_payment_processor',
     *     tags: ['payment', 'critical'],
     *     idempotencyKey: $client->generateContentBasedIdempotencyKey($paymentData, 5000)
     * );
     * 
     * echo "Event created: {$response->getUuid()}";
     * echo "Status: {$response->getStatus()->value}";
     */
    public function create(
        string $eventType,
        array $payload,
        string $destination,
        array $tags = [],
        ?string $idempotencyKey = null,
        ?Carbon $scheduledAt = null,
        bool $asOutboundEvent = false
    ): EventResponse|OutboundEvent {
        $data = $this->buildEventData($eventType, $payload, $tags, $destination, $scheduledAt);

        if (!$idempotencyKey) {
            $idempotencyKey = $this->client->idempotency->generateTimeBound($data, 'event_creation');
        }

        $response = $this->request('POST', 'events', $data, $idempotencyKey);

        $eventResponse = new EventResponse($response);

        if ($asOutboundEvent) {
            return $eventResponse->getDetails();
        }

        return $eventResponse;
    }

    /**
     * Send multiple events in a single batch request
     * 
     * @param string $eventType The shared event type for all events in the batch
     * @param array $events The array of events, each with 'payload' and optional 'tags'
     * @param string $destination The destination for all events in the batch
     * @param array $tags Optional batch-level tags applied to all events
     * @param string|null $idempotencyKey Optional idempotency key for the batch
     * @param Carbon|null $scheduledAt Optional scheduled time for the batch
     * @return BatchEventResponse
     * @throws EventrelException if the events array is empty
     */
    public function createMany(
        string $eventType,
        array $events,
        string $destination,
        array $tags = [],
        ?string $idempotencyKey = null,
        ?Carbon $scheduledAt = null
    ): BatchEventResponse {
        if (empty($events)) {
            throw new EventrelException('Cannot send empty batch. Provide at least one event.');
        }

        $data = $this->buildEventData($eventType, $events, $tags, $destination, $scheduledAt, isBatch: true);

        $response = $this->request('POST', 'events', $data, $idempotencyKey);

        return new BatchEventResponse($response);
    }

    /**
     * Get a single event by UUID
     * 
     * @param string $uuid The event UUID
     * @return EventResponse|OutboundEvent
     * @throws EventrelException
     */
    public function get(string $uuid, bool $asOutboundEvent = false): EventResponse|OutboundEvent
    {
        $response = $this->request('GET', "events/{$uuid}");

        $eventResponse = new EventResponse($response);

        if ($asOutboundEvent) {
            return $eventResponse->getDetails();
        }

        return $eventResponse;
    }

    /**
     * List events with optional filters and pagination
     * 
     * Provides flexible filtering through both explicit parameters (for common filters)
     * and an array parameter (for additional filters).
     * 
     * @param int $page Page number (default: 1)
     * @param int $perPage Items per page (default: 15)
     * @param string|null $status Filter by status ('pending', 'delivered', 'failed', 'cancelled')
     * @param string|null $eventType Filter by event type
     * @param array<string, mixed> $additionalFilters Additional query filters
     *   - tags: string|array - Filter by tags
     *   - from_date: string - Filter events created after this date
     *   - to_date: string - Filter events created before this date
     *   - destination: string - Filter by destination UUID
     *   - idempotency_key: string - Filter by idempotency key
     * @return EventListResponse
     * @throws EventrelException
     * 
     * @example
     * // Simple pagination
     * $response = $client->events->list(page: 2, perPage: 50);
     * 
     * // With status filter
     * $response = $client->events->list(status: 'failed');
     * 
     * // Complex filters
     * $response = $client->events->list(
     *     page: 1,
     *     perPage: 25,
     *     status: 'delivered',
     *     eventType: 'user.created',
     *     additionalFilters: [
     *         'tags' => ['important', 'user'],
     *         'from_date' => '2025-10-01T00:00:00Z',
     *         'destination' => 'dest_abc123'
     *     ]
     * );
     */
    public function list(
        int $page = 1,
        int $perPage = 15,
        ?string $status = null,
        ?string $eventType = null,
        array $additionalFilters = []
    ): EventListResponse {
        $filters = array_merge(
            array_filter([
                'page' => $page,
                'per_page' => $perPage,
                'status' => $status,
                'event_type' => $eventType,
            ], fn($value) => $value !== null),
            $additionalFilters
        );

        $response = $this->request('GET', 'events', query: $filters);

        return new EventListResponse($response);
    }

    /**
     * Retry a failed event
     * 
     * @param string $uuid The event UUID to retry
     * @return EventResponse|OutboundEvent
     * @throws EventrelException
     */
    public function retry(string $uuid, bool $asOutboundEvent = false): EventResponse|OutboundEvent
    {
        $response = $this->request('POST', "events/{$uuid}/retry");

        $eventResponse = new EventResponse($response);

        if ($asOutboundEvent) {
            return $eventResponse->getDetails();
        }

        return $eventResponse;
    }

    /**
     * Retry multiple events in bulk
     * 
     * @param array<string> $uuids Array of event UUIDs to retry
     * @return BulkRetryResponse
     * @throws EventrelException
     * 
     * @example
     * $result = $client->events->retryMany(['evt_123', 'evt_456', 'evt_789']);
     */
    public function retryMany(array $uuids): BulkRetryResponse
    {
        $response = $this->request('POST', 'events/retry', ['events' => $uuids]);

        return new BulkRetryResponse($response);
    }

    /**
     * Cancel a scheduled event
     * 
     * @param string $uuid The event UUID to cancel
     * @param string $reason Cancellation reason
     * @return EventResponse|OutboundEvent
     * @throws EventrelException
     */
    public function cancel(string $uuid, string $reason = 'Cancelled via client.', ?string $idempotencyKey = null, bool $asOutboundEvent = false): EventResponse|OutboundEvent
    {
        $response = $this->request('POST', "events/{$uuid}/cancel", ['reason' => $reason], $idempotencyKey);

        $eventResponse = new EventResponse($response);

        if ($asOutboundEvent) {
            return $eventResponse->getDetails();
        }

        return $eventResponse;
    }

    /**
     * Build event data payload for API requests
     * 
     * Constructs the request body structure for both single events and batch events.
     * Conditionally includes optional fields (destination, scheduled_at) only when provided.
     * 
     * @param string $eventType The event type identifier
     * @param array<string, mixed> $data Either event payload (single) or array of events (batch)
     * @param array<string> $tags Event tags for filtering/categorization
     * @param string|null $destination Optional destination identifier
     * @param Carbon|null $scheduledAt Optional scheduled execution time
     * @param bool $isBatch Whether this is a batch request (changes 'payload' key to 'events')
     * @return array<string, mixed> The structured request payload ready for JSON encoding
     */
    private function buildEventData(
        string $eventType,
        array $data,
        array $tags,
        ?string $destination,
        ?Carbon $scheduledAt,
        bool $isBatch = false
    ): array {
        $payload = [
            'event_type' => $eventType,
            $isBatch ? 'events' : 'payload' => $data,
            'tags' => $tags,
        ];

        if ($destination) {
            $payload['destination'] = $destination;
        }

        if ($scheduledAt) {
            $payload['scheduled_at'] = $scheduledAt->toISOString();
        }

        return $payload;
    }

    /**
     * Make an HTTP request with consistent options and automatic idempotency handling
     * 
     * Internal wrapper around EventrelClient::makeRequest() that standardizes how
     * requests are made across the service. Handles JSON body encoding, query parameters,
     * and idempotency key injection for mutating operations.
     * 
     * Note: Only includes idempotency key if explicitly provided. The caller is responsible
     * for determining when idempotency is needed.
     * 
     * @param string $method HTTP method (GET, POST, PUT, DELETE, etc.)
     * @param string $path API endpoint path relative to base URL
     * @param array<string, mixed> $data Request body data (automatically JSON-encoded)
     * @param string|null $idempotencyKey Optional idempotency key for request deduplication
     * @param array<string, mixed> $query Optional query string parameters
     * @return ResponseInterface The HTTP response from the API
     * @throws EventrelException If the request fails or API returns an error
     */
    private function request(
        string $method,
        string $path,
        array $data = [],
        ?string $idempotencyKey = null,
        array $query = []
    ): ResponseInterface {
        $options = [];

        if (!empty($data)) {
            $options['json'] = $data;
        }

        if (!empty($query)) {
            $options['query'] = $query;
        }

        if ($idempotencyKey) {
            $options['headers']['X-Idempotency-Key'] = $idempotencyKey;
        }

        return $this->client->makeRequest($method, $path, $options);
    }
}
