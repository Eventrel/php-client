<?php

namespace Eventrel\Client\Builders;

use Eventrel\Client\Builders\Concerns\{CanSchedule, CanIdempotentize};
use Eventrel\Client\EventrelClient;
use Eventrel\Client\Responses\EventResponse;
use Eventrel\Client\Services\EventService;

/**
 * Fluent builder for constructing and sending events.
 *
 * This builder provides a chainable API for configuring events
 * before sending them. It supports:
 * - Setting destination endpoints
 * - Building event payloads incrementally
 * - Adding tags for categorization/filtering
 * - Scheduling future delivery (via CanSchedule trait)
 * - Idempotent delivery (via CanIdempotentize trait)
 * 
 * Usage:
 * ```php
 * $response = Eventrel::event('payment.completed')
 *     ->to('identifier')
 *     ->with('amount', 100.00)
 *     ->with('currency', 'USD')
 *     ->tags(['production', 'high-priority'])
 *     ->idempotent('payment-123')
 *     ->send();
 * ```
 */
class EventBuilder
{
    use CanIdempotentize, CanSchedule;

    /**
     * The event service for API communication.
     */
    private EventService $service;

    /**
     * The event destination. It is the unique identifier for the event.
     */
    private ?string $destination = null;

    /**
     * The event payload containing business data.
     */
    private array $payload = [];

    /**
     * Tags for categorizing or filtering events.
     */
    private array $tags = [];

    /**
     * Create a new EventBuilder instance.
     * 
     * Initializes the builder with a client and event type,
     * setting up the service for eventual API communication.
     *
     * @param EventrelClient $client The Eventrel API client
     * @param string $eventType The event type identifier (e.g., "payment.completed")
     */
    public function __construct(
        private EventrelClient $client,
        private ?string $eventType = null
    ) {
        $this->service = new EventService($client);
    }

    /**
     * Set the event type.
     * 
     * Allows changing the event type after construction if needed.
     *
     * @param string $eventType The event type identifier
     * @return $this Fluent interface
     */
    public function eventType(string $eventType): self
    {
        $this->eventType = $eventType;

        return $this;
    }

    /**
     * Set the destination for the event.
     *
     * @param string $destination The event destination identifier
     * @return $this Fluent interface
     */
    public function to(string $destination): self
    {
        $this->destination = $destination;

        return $this;
    }

    /**
     * Set the entire event payload at once.
     * 
     * This replaces any existing payload data. Use withData() to merge
     * instead of replacing, or with() to add individual fields.
     *
     * @param array $payload Complete payload data
     * @return $this Fluent interface
     */
    public function payload(array $payload): self
    {
        $this->payload = $payload;

        return $this;
    }

    /**
     * Set tags for categorizing or filtering the event.
     * 
     * Tags can be used to:
     * - Filter events in the dashboard
     * - Route events to different handlers
     * - Group events for analytics
     *
     * @param array $tags Array of tag strings (e.g., ['production', 'priority:high'])
     * @return $this Fluent interface
     */
    public function tags(array $tags): self
    {
        $this->tags = $tags;

        return $this;
    }

    /**
     * Add a single field to the payload.
     * 
     * Useful for building payloads incrementally. Overwrites existing
     * values for the same key.
     *
     * @param string $key The payload field name
     * @param mixed $value The payload field value
     * @return $this Fluent interface
     */
    public function with(string $key, mixed $value): self
    {
        $this->payload[$key] = $value;

        return $this;
    }

    /**
     * Merge multiple fields into the payload.
     * 
     * Merges the provided data with existing payload, preserving
     * existing keys that aren't being overwritten.
     *
     * @param array $data Key-value pairs to merge into payload
     * @return $this Fluent interface
     */
    public function withData(array $data): self
    {
        $this->payload = array_merge($this->payload, $data);

        return $this;
    }

    /**
     * Get the current payload data.
     * 
     * Useful for debugging or conditional logic before sending.
     *
     * @return array The current payload array
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * Get the event type.
     * 
     * Returns the event type identifier set during construction.
     *
     * @return string The event type (e.g., "payment.completed")
     */
    public function getEventType(): string
    {
        return $this->eventType;
    }

    /**
     * Send the event immediately (or schedule for future delivery).
     * 
     * Dispatches the configured event to the Eventrel API for delivery.
     * If scheduledAt was set via the CanSchedule trait, the event will
     * be scheduled for future delivery rather than sent immediately.
     *
     * @return EventResponse The API response with event details and status
     * @throws \Exception If destination is not set or API request fails
     */
    public function send(): EventResponse
    {
        return $this->service->create(
            destination: $this->destination,
            eventType: $this->eventType,
            payload: $this->payload,
            tags: $this->tags,
            idempotencyKey: $this->idempotencyKey,
            scheduledAt: $this->scheduledAt
        );
    }

    /**
     * Convert the builder configuration to an array.
     * 
     * Useful for debugging, logging, or inspecting what will be sent
     * before actually calling send().
     *
     * @return array The builder configuration as an associative array
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
