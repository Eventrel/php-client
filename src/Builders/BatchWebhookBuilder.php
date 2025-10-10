<?php

namespace Eventrel\Client\Builders;

use Eventrel\Client\Builders\Concerns\{CanSchedule, CanIdempotentize};
use Eventrel\Client\EventrelClient;
use Eventrel\Client\Responses\BatchEventResponse;

class BatchWebhookBuilder
{
    use CanIdempotentize, CanSchedule;

    /**
     * The application to send the webhook to.
     * 
     * @var string|null
     */
    private ?string $application = null;

    /**
     * The events data for the webhook.
     * 
     * @var array
     */
    private array $events = [];

    /**
     * The idempotency key for the webhook.
     * 
     * @var string|null
     */
    private ?string $idempotencyKey = null;

    /**
     * WebhookBuilder constructor.
     * 
     * @param \Eventrel\Client\EventrelClient $client
     * @param string $eventType
     */
    public function __construct(
        private EventrelClient $client,
        private string $eventType
    ) {
        // 
    }

    /**
     * Set the target application for the webhook
     *
     * @param string $application
     * @return $this
     */
    public function to(string $application): self
    {
        $this->application = $application;

        return $this;
    }

    /**
     * Set the entire payload at once
     *
     * @param array $events
     * @return $this
     */
    public function events(array $events): self
    {
        $this->events = $events;

        return $this;
    }

    /**
     * Add a webhook to the batch
     *
     * @param array $payload
     * @param array $tags
     * @return $this
     */
    public function add(array $payload, array $tags = []): self
    {
        $this->events[] = [
            'payload' => $payload,
            'tags' => $tags,
        ];

        return $this;
    }

    /**
     * Send all webhooks in the batch
     */
    public function send(): BatchEventResponse
    {
        return $this->client->sendWebhookBatch(
            application: $this->application,
            eventType: $this->eventType,
            events: $this->events,
            idempotencyKey: $this->idempotencyKey,
            scheduledAt: $this->scheduledAt
        );
    }

    /**
     * Convert to array representation (useful for debugging)
     */
    public function toArray(): array
    {
        return [
            // 'application' => $this->application,
            // 'event_type' => $this->eventType,
            // 'payload' => $this->payload,
            // 'idempotency_key' => $this->idempotencyKey,
            // 'scheduled_at' => $this->scheduledAt?->toISOString(),
        ];
    }
}
