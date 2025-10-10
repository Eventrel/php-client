<?php

namespace Eventrel\Client\Builders;

use Carbon\Carbon;
use Eventrel\Client\EventrelClient;
use Eventrel\Client\Responses\WebhookResponse;

class WebhookBuilder
{
    /**
     * The destination to send the webhook to.
     * 
     * @var string|null
     */
    private ?string $destination = null;

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
     * The scheduled time for the webhook.
     * 
     * @var Carbon|null
     */
    private ?Carbon $scheduledAt = null;

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
     * Set the target destination for the webhook
     */
    public function to(string $destination): self
    {
        $this->destination = $destination;

        return $this;
    }

    /**
     * Set the entire payload at once
     */
    public function payload(array $payload): self
    {
        $this->payload = $payload;

        return $this;
    }

    /**
     * Add a single key-value pair to the payload
     */
    public function with(string $key, mixed $value): self
    {
        $this->payload[$key] = $value;

        return $this;
    }

    /**
     * Add multiple key-value pairs to the payload
     */
    public function withData(array $data): self
    {
        $this->payload = array_merge($this->payload, $data);

        return $this;
    }

    /**
     * Set idempotency key to prevent duplicate processing
     */
    public function idempotencyKey(string $key): self
    {
        $this->idempotencyKey = $key;

        return $this;
    }

    /**
     * Set unique idempotency
     */
    public function withUniqueKey(): self
    {
        //     $this->idempotencyKey = $this->generateUuid();

        return $this;
    }

    /**
     * Schedule webhook for specific time
     */
    public function scheduleAt(Carbon $scheduledAt): self
    {
        $this->scheduledAt = $scheduledAt;

        return $this;
    }

    /**
     * Schedule webhook for a number of seconds from now
     */
    public function scheduleIn(int $seconds): self
    {
        $this->scheduledAt = now()->addSeconds($seconds);

        return $this;
    }

    /**
     * Schedule webhook for a number of minutes from now
     */
    public function scheduleInMinutes(int $minutes): self
    {
        $this->scheduledAt = now()->addMinutes($minutes);

        return $this;
    }

    /**
     * Schedule webhook for a number of hours from now
     */
    public function scheduleInHours(int $hours): self
    {
        $this->scheduledAt = now()->addHours($hours);

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
     * Get the idempotency key
     */
    public function getIdempotencyKey(): ?string
    {
        return $this->idempotencyKey;
    }

    /**
     * Get the scheduled time
     */
    public function getScheduledAt(): ?Carbon
    {
        return $this->scheduledAt;
    }

    /**
     * Send the webhook immediately
     */
    public function send(): WebhookResponse
    {
        return $this->client->sendWebhook(
            destination: $this->destination,
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
            'destination' => $this->destination,
            'event_type' => $this->eventType,
            'payload' => $this->payload,
            'idempotency_key' => $this->idempotencyKey,
            'scheduled_at' => $this->scheduledAt?->toISOString(),
        ];
    }
}
