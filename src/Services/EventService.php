<?php

namespace Eventrel\Client\Services;

use Carbon\Carbon;
use Eventrel\Client\EventrelClient;
use Eventrel\Client\Exceptions\EventrelException;
use Eventrel\Client\Responses\{EventResponse, BatchEventResponse};
use Psr\Http\Message\ResponseInterface;

class EventService
{
    /**
     * EventService constructor.
     * 
     * @param \Eventrel\Client\EventrelClient $client
     */
    public function __construct(
        private EventrelClient $client
    ) {
        // 
    }

    /**
     * Send an event directly (non-fluent method)
     *
     * @param string $eventType
     * @param array $payload
     * @param string|null $application
     * @param string|null $idempotencyKey
     * @param Carbon|null $scheduledAt
     * @return EventResponse
     */
    public function sendEvent(
        string $eventType,
        array $payload,
        array $tags = [],
        ?string $destination = null,
        ?string $idempotencyKey = null,
        ?Carbon $scheduledAt = null
    ): EventResponse {
        $data = $this->buildEventData($eventType, $payload, $tags, $destination, $scheduledAt);

        $response = $this->request('POST', 'events', $data, $idempotencyKey);

        return new EventResponse($response);
    }

    /**
     * Send multiple events in a single batch request
     * 
     * @param string $eventType The shared event type for all events in the batch
     * @param array $events The array of events, each with 'payload' and optional 'tags'
     * @param array $tags Optional batch-level tags applied to all events
     * @param string|null $destination Optional destination for all events in the batch
     * @param string|null $idempotencyKey Optional idempotency key for the batch
     * @param Carbon|null $scheduledAt Optional scheduled time for the batch
     * @return BatchEventResponse
     * @throws EventrelException if the events array is empty
     */
    public function sendBatchEvent(
        string $eventType,
        array $events,
        array $tags = [],
        ?string $destination = null,
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

    public function getEvent(string $uuid): EventResponse
    {
        dd('getEvent', $uuid);
    }

    public function listEvents(): array
    {
        dd('listEvents');
    }

    public function retryEvent(string $uuid): EventResponse
    {
        dd('retryEvent', $uuid);
    }

    public function retryEvents(array $uuids): array
    {
        dd('retryEvents', $uuids);
    }

    public function cancelEvent(string $uuid, string $reason): EventResponse
    {
        dd('cancelEvent', $uuid, $reason);
    }

    /**
     * Build event data payload.
     */
    private function buildEventData(
        string $eventType,
        array $data,
        array $tags,
        ?string $destination,
        ?Carbon $scheduledAt,
        bool $isBatch = false
    ): array {
        $data = [
            'event_type' => $eventType,
            $isBatch ? 'events' : 'payload' => $data,
            'tags' => $tags,
        ];

        if ($destination) {
            $data['destination'] = $destination;
        }

        if ($scheduledAt) {
            $data['scheduled_at'] = $scheduledAt->toISOString();
        }

        return $data;
    }

    /**
     * Make an HTTP request with consistent options
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
            $options['headers']['X-Idempotency-Key'] = $idempotencyKey ?? $this->client->generateIdempotencyKey();
        }

        return $this->client->makeRequest($method, $path, $options);
    }
}
