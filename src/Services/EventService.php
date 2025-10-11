<?php

namespace Eventrel\Client\Services;

use Carbon\Carbon;
use Eventrel\Client\EventrelClient;
use Eventrel\Client\Exceptions\EventrelException;
use Eventrel\Client\Responses\{EventResponse, BatchEventResponse};

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
        $data = [
            'event_type' => $eventType,
            'payload' => $payload,
            'tags' => $tags,
        ];

        if ($destination) {
            $data['destination'] = $destination;
        }

        if ($scheduledAt) {
            $data['scheduled_at'] = $scheduledAt->toISOString();
        }

        $response = $this->client->makeRequest('POST', 'events', [
            'json' => $data,
            'headers' => [
                'X-Idempotency-Key' => $idempotencyKey ?? $this->client->generateIdempotencyKey(),
            ]
        ]);

        return new EventResponse($response);
    }

    /**
     * Send multiple events in a single batch request
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

        $data = [
            'event_type' => $eventType,
            'events' => $events,
            'tags' => $tags,
        ];

        if ($destination) {
            $data['destination'] = $destination;
        }

        if ($scheduledAt) {
            $data['scheduled_at'] = $scheduledAt->toISOString();
        }

        $response = $this->client->makeRequest('POST', 'events', [
            'json' => $data,
            'headers' => [
                'X-Idempotency-Key' => $idempotencyKey ?? $this->client->generateIdempotencyKey(),
            ]
        ]);

        return new BatchEventResponse($response);
    }

    // /**
    //  * Get a specific webhook by ID
    //  */
    // public function getWebhook(string $webhookId): EventResponse
    // {
    //     $response = $this->makeRequest('GET', "/api/v1/webhooks/{$webhookId}");
    //     return new EventResponse($response['data'] ?? []);
    // }

    // /**
    //  * List webhooks with pagination and filters
    //  */
    // public function getWebhooks(int $page = 1, array $filters = []): WebhookListResponse
    // {
    //     $query = array_merge(['page' => $page], $filters);

    //     $response = $this->makeRequest('GET', '/api/v1/webhooks', [
    //         'query' => $query,
    //     ]);

    //     return new WebhookListResponse(
    //         $response['data'] ?? [],
    //         $response['pagination'] ?? []
    //     );
    // }

}
