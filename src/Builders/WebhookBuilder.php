<?php

namespace Eventrel\Client\Builders;

use Eventrel\Client\Builders\Concerns\{CanSchedule, CanIdempotentize};
use Eventrel\Client\EventrelClient;
use Eventrel\Client\Responses\WebhookResponse;

class WebhookBuilder
{
    use CanIdempotentize, CanSchedule;

    /**
     * The application to send the webhook to.
     * 
     * @var string|null
     */
    private ?string $application = null;

    /**
     * The payload data for the webhook.
     * 
     * @var array
     */
    private array $payload = [];

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
     * @param array $payload
     * @return $this
     */
    public function payload(array $payload): self
    {
        $this->payload = $payload;

        return $this;
    }

    /**
     * Add a single key-value pair to the payload
     *
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    public function with(string $key, mixed $value): self
    {
        $this->payload[$key] = $value;

        return $this;
    }

    /**
     * Add multiple key-value pairs to the payload
     *
     * @param array $data
     * @return $this    
     */
    public function withData(array $data): self
    {
        $this->payload = array_merge($this->payload, $data);

        return $this;
    }

    /**
     * Get the current payload
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * Get the event type
     */
    public function getEventType(): string
    {
        return $this->eventType;
    }

    /**
     * Send the webhook immediately
     */
    public function send(): WebhookResponse
    {
        return $this->client->sendWebhook(
            application: $this->application,
            eventType: $this->eventType,
            payload: $this->payload,
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
