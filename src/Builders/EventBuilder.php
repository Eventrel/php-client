<?php

namespace Eventrel\Client\Builders;

use Eventrel\Client\Builders\Concerns\{CanSchedule, CanIdempotentize};
use Eventrel\Client\EventrelClient;
use Eventrel\Client\Responses\EventResponse;

class EventBuilder
{
    use CanIdempotentize, CanSchedule;

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
     * The tags for the webhook.
     * 
     * @var array
     */
    private array $tags = [];

    /**
     * The idempotency key for the webhook.
     * 
     * @var string|null
     */
    private ?string $idempotencyKey = null;

    /**
     * EventBuilder constructor.
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
    public function to(string $destination): self
    {
        $this->destination = $destination;

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
     * Set the tags for the webhook
     *
     * @param array $tags
     * @return $this
     */
    public function tags(array $tags): self
    {
        $this->tags = $tags;

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
    public function send(): EventResponse
    {
        dd('Not implemented yet');
        // return $this->client->sendWebhook(
        //     destination: $this->destination,
        //     eventType: $this->eventType,
        //     payload: $this->payload,
        //     tags: $this->tags,
        //     idempotencyKey: $this->idempotencyKey,
        //     scheduledAt: $this->scheduledAt
        // );
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
            'tags' => $this->tags,
            'idempotency_key' => $this->idempotencyKey,
            'scheduled_at' => $this->scheduledAt?->toISOString(),
        ];
    }
}
