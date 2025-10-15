<?php

namespace Eventrel\Builders;

use Eventrel\Builders\Concerns\{CanSchedule, CanIdempotentize};
use Eventrel\EventrelClient;
use Eventrel\Responses\BatchEventResponse;
use Eventrel\Services\EventService;

/**
 * Fluent builder for sending multiple events in a single batch.
 * 
 * Batch sending is useful for:
 * - Reducing API overhead when sending multiple events
 * - Ensuring atomicity (all events succeed or fail together)
 * - Maintaining event order within a batch
 * - Improving throughput for bulk operations
 * 
 * All events in a batch share:
 * - The same event type
 * - The same destination endpoint
 * - Common batch-level tags (events can have additional tags)
 * 
 * Usage:
 * ```php
 * $response = Eventrel::batchEvent('user.created')
 *     ->to('identifier')
 *     ->tags(['bulk-import', 'production'])
 *     ->add(['user_id' => 1, 'email' => 'user1@example.com'], ['premium'])
 *     ->add(['user_id' => 2, 'email' => 'user2@example.com'])
 *     ->add(['user_id' => 3, 'email' => 'user3@example.com'], ['trial'])
 *     ->send();
 * ```
 */
class BatchEventBuilder
{
    use CanIdempotentize, CanSchedule;

    /**
     * The event service for API communication.
     */
    private EventService $service;

    /**
     * The event destination URL or endpoint identifier.
     * 
     * All events in the batch will be sent to this destination.
     */
    private ?string $destination = null;

    /**
     * Batch-level tags applied to all events.
     * 
     * These tags are shared across all events in the batch.
     * Individual events can have additional tags.
     */
    private array $tags = [];

    /**
     * The collection of events to send in this batch.
     * 
     * Each event contains:
     * - payload: The event data
     * - tags: Optional event-specific tags (in addition to batch tags)
     */
    private array $events = [];

    /**
     * Create a new BatchEventBuilder instance.
     * 
     * Initializes the builder with a client and shared event type
     * that will apply to all events in the batch.
     *
     * @param EventrelClient $client The Eventrel API client
     * @param string $eventType The event type for all events (e.g., "user.created")
     */
    public function __construct(
        private EventrelClient $client,
        private string $eventType
    ) {
        $this->service = new EventService($client);
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
     * Set batch-level tags applied to all events.
     * 
     * These tags are shared across all events in the batch.
     * Individual events added via add() can have additional tags.
     * 
     * Common use cases:
     * - Environment tags: ['production', 'staging']
     * - Source tags: ['bulk-import', 'api', 'manual']
     * - Priority tags: ['high-priority', 'low-priority']
     *
     * @param array $tags Array of tag strings
     * @return $this Fluent interface
     */
    public function tags(array $tags): self
    {
        $this->tags = $tags;

        return $this;
    }

    /**
     * Set all events at once, replacing any previously added events.
     * 
     * Each event should be an array with:
     * - 'payload': (required) The event data
     * - 'tags': (optional) Event-specific tags
     * 
     * Example:
     * ```php
     * ->events([
     *     ['payload' => ['user_id' => 1], 'tags' => ['premium']],
     *     ['payload' => ['user_id' => 2], 'tags' => ['trial']],
     *     ['payload' => ['user_id' => 3]],
     * ])
     * ```
     *
     * @param array $events Array of event configurations
     * @return $this Fluent interface
     */
    public function events(array $events): self
    {
        $this->events = $events;

        return $this;
    }

    /**
     * Add a single event to the batch.
     * 
     * Events are sent in the order they are added. Each event
     * can have its own payload and optional event-specific tags
     * (in addition to batch-level tags).
     * 
     * Example:
     * ```php
     * ->add(['user_id' => 1, 'name' => 'John'], ['premium', 'verified'])
     * ->add(['user_id' => 2, 'name' => 'Jane'])
     * ->add(['user_id' => 3, 'name' => 'Bob'], ['trial'])
     * ```
     *
     * @param array $payload The event payload data
     * @param array $tags Optional event-specific tags
     * @return $this Fluent interface
     */
    public function add(array $payload, array $tags = []): self
    {
        $event = ['payload' => $payload];

        if (!empty($tags)) {
            $event['tags'] = $tags;
        }

        $this->events[] = $event;

        return $this;
    }

    /**
     * Get the current batch of events.
     * 
     * Returns the array of all events added to the batch.
     * Useful for debugging or conditional logic before sending.
     *
     * @return array The events array
     */
    public function getEvents(): array
    {
        return $this->events;
    }

    /**
     * Get the event type for this batch.
     * 
     * All events in the batch share this event type.
     *
     * @return string The event type (e.g., "user.created")
     */
    public function getEventType(): string
    {
        return $this->eventType;
    }

    /**
     * Get the count of events in the batch.
     * 
     * Useful for validation or displaying progress information.
     *
     * @return int Number of events in the batch
     */
    public function count(): int
    {
        return count($this->events);
    }

    /**
     * Send all events in the batch.
     * 
     * Dispatches the entire batch to the Eventrel API for delivery.
     * All events will be sent to the same destination with the same
     * event type. If scheduledAt was set, the entire batch will be
     * scheduled for future delivery.
     *
     * @return BatchEventResponse The API response with batch details
     * @throws \Exception If destination is not set or API request fails
     */
    public function send(): BatchEventResponse
    {
        return $this->service->createMany(
            destination: $this->destination,
            eventType: $this->eventType,
            events: $this->events,
            tags: $this->tags,
            idempotencyKey: $this->idempotencyKey,
            scheduledAt: $this->scheduledAt
        );
    }

    /**
     * Convert the batch configuration to an array.
     * 
     * Returns the complete batch structure that will be sent to the API.
     * Useful for debugging, logging, or inspecting what will be sent
     * before actually calling send().
     *
     * @return array The complete batch configuration
     */
    public function toArray(): array
    {
        return [
            'event_type' => $this->eventType,
            'destination' => $this->destination,
            'tags' => $this->tags,
            'events' => $this->events,
            'idempotency_key' => $this->idempotencyKey,
            'scheduled_at' => $this->scheduledAt?->toISOString(),
        ];
    }
}
