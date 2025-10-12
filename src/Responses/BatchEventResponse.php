<?php

namespace Eventrel\Client\Responses;

use Eventrel\Client\Entities\OutboundEvent;
use Eventrel\Client\Enums\EventStatus;
use GuzzleHttp\Psr7\Response;

/**
 * Response object for batch event submissions
 * 
 * Represents the API response when sending multiple events in a single batch request.
 * Provides convenient methods to access batch metadata, query events by status or tag,
 * and check overall batch delivery status.
 * 
 * @package Eventrel\Client\Responses
 */
class BatchEventResponse extends BaseResponse
{
    /**
     * The unique batch identifier
     * 
     * @var string
     */
    private string $batchId;

    /**
     * Array of OutboundEvent objects for each event in the batch
     * 
     * @var OutboundEvent[]
     */
    private array $outboundEvents;

    /**
     * Total number of events in the batch
     * 
     * @var int
     */
    private int $totalEvents;

    /**
     * Unique key for idempotent request handling
     * 
     * @var string|null
     */
    private ?string $idempotencyKey;

    /**
     * Create a new BatchEventResponse instance
     * 
     * Parses the Guzzle HTTP response and extracts all batch data,
     * converting each event into an OutboundEvent object for type-safe access.
     *
     * @param Response $response The Guzzle HTTP response object from the API
     */
    public function __construct(Response $response)
    {
        parent::__construct($response);
    }

    /**
     * Parse and populate response data from the HTTP response
     * 
     * Extracts JSON content and converts the outbound_events array
     * into OutboundEvent objects. Handles both header and body sources
     * for the idempotency key with header taking precedence.
     *
     * @return void
     */
    protected function parseResponse(): void
    {
        parent::parseResponse(); // Parse common fields first

        $this->batchId = $this->data['batch'] ?? '';
        $this->totalEvents = $this->data['total_events'] ?? 0;

        // Check header first, then fallback to body
        $this->idempotencyKey = $this->response->hasHeader('x-idempotency-key')
            ? $this->response->getHeaderLine('x-idempotency-key')
            : ($this->data['idempotency_key'] ?? null);

        // Convert each event array into an OutboundEvent object
        $this->outboundEvents = array_map(
            fn(array $event) => OutboundEvent::from($event),
            $this->data['outbound_events'] ?? []
        );
    }

    /**
     * Get the unique batch identifier
     * 
     * This ID can be used to track batch status, query for delivery attempts,
     * or reference this batch in support requests.
     *
     * @return string The batch UUID (e.g., 'batch_a1b2c3d4...')
     * 
     * @example
     * $batchId = $response->getBatchId();
     * // Store for later status checks
     */
    public function getBatchId(): string
    {
        return $this->batchId;
    }

    /**
     * Get the total number of events in the batch
     * 
     * This is the count of all events that were submitted,
     * regardless of their individual success or failure status.
     *
     * @return int Total event count
     */
    public function getTotalEvents(): int
    {
        return $this->totalEvents;
    }

    /**
     * Get all unique tags across all events in the batch
     * 
     * Aggregates and de-duplicates tags from all events, useful for
     * getting a consolidated view of all tags used in the batch
     * for filtering or analytics purposes.
     *
     * @return array<int, string> Array of unique tag strings
     * 
     * @example
     * $allTags = $response->getAllTags();
     * // ['signup', 'premium', 'email-verified']
     */
    public function getAllTags(): array
    {
        $allTags = [];

        foreach ($this->outboundEvents as $event) {
            $allTags = array_merge($allTags, $event->tags ?? []);
        }

        return array_values(array_unique($allTags));
    }

    /**
     * Get events that have a specific tag
     * 
     * Filters the batch to return only events tagged with the specified tag.
     *
     * @param string $tag The tag to filter by
     * @return OutboundEvent[] Array of events with that tag
     * 
     * @example
     * $premiumEvents = $response->getByTag('premium');
     * foreach ($premiumEvents as $event) {
     *     echo $event->uuid;
     * }
     */
    public function getByTag(string $tag): array
    {
        return array_filter(
            $this->outboundEvents,
            fn(OutboundEvent $event) => in_array($tag, $event->tags ?? [])
        );
    }

    /**
     * Get all outbound events in the batch
     * 
     * Returns an array of OutboundEvent objects, one for each
     * event in the batch, in the order they were submitted.
     *
     * @return OutboundEvent[] Array of OutboundEvent objects
     * 
     * @example
     * foreach ($response->all() as $event) {
     *     echo "Event {$event->uuid}: {$event->status->value}\n";
     * }
     */
    public function all(): array
    {
        return $this->outboundEvents;
    }

    /**
     * Get a specific event by its UUID or array index
     * 
     * Provides flexible access to individual events within the batch.
     * Pass an integer to get by index (0-based), or a string UUID to find
     * by unique identifier.
     *
     * @param int|string $identifier Either an integer index or event UUID string
     * @return OutboundEvent|null The matching event, or null if not found
     * 
     * @example
     * // Get by index
     * $firstEvent = $response->get(0);
     * 
     * // Get by UUID
     * $event = $response->get('evt_abc123');
     */
    public function get(int|string $identifier): ?OutboundEvent
    {
        if (is_int($identifier)) {
            return $this->getByIndex($identifier);
        }

        foreach ($this->outboundEvents as $event) {
            if ($event->uuid === $identifier) {
                return $event;
            }
        }

        return null;
    }

    /**
     * Get a specific event by its index in the batch
     * 
     * Events are indexed starting from 0 in the order they
     * were added to the batch.
     *
     * @param int $index The zero-based index of the event
     * @return OutboundEvent|null The event at the index, or null if out of bounds
     * 
     * @example
     * $secondEvent = $response->getByIndex(1);
     */
    public function getByIndex(int $index): ?OutboundEvent
    {
        return $this->outboundEvents[$index] ?? null;
    }

    /**
     * Get the first event in the batch
     * 
     * Convenience method for accessing the first event without
     * needing to use array access or indexing.
     *
     * @return OutboundEvent|null The first event, or null if batch is empty
     */
    public function first(): ?OutboundEvent
    {
        return $this->outboundEvents[0] ?? null;
    }

    /**
     * Get the last event in the batch
     * 
     * Convenience method for accessing the last event without
     * needing to calculate the array length.
     *
     * @return OutboundEvent|null The last event, or null if batch is empty
     */
    public function last(): ?OutboundEvent
    {
        return !empty($this->outboundEvents)
            ? end($this->outboundEvents)
            : null;
    }

    /**
     * Get events filtered by status
     * 
     * Returns all events that match the specified status.
     * Useful for finding pending, delivered, or failed events.
     * Accepts either an EventStatus enum or status string.
     *
     * @param EventStatus|string $status The status to filter by (e.g., EventStatus::PENDING or 'pending')
     * @return OutboundEvent[] Array of matching events
     * 
     * @example
     * // Using enum
     * $pending = $response->getByStatus(EventStatus::PENDING);
     * 
     * // Using string
     * $failed = $response->getByStatus('failed');
     */
    public function getByStatus(EventStatus|string $status): array
    {
        if (is_string($status)) {
            $status = EventStatus::tryFrom($status);

            if (!$status) {
                return [];
            }
        }

        return array_filter(
            $this->outboundEvents,
            fn(OutboundEvent $event) => $event->status === $status
        );
    }

    /**
     * Get the count of events with a specific status
     * 
     * Useful for generating summary statistics about the batch
     * without needing to iterate through the full array.
     *
     * @param EventStatus|string $status The status to count
     * @return int Number of events with that status
     * 
     * @example
     * $failedCount = $response->countByStatus(EventStatus::FAILED);
     * echo "Failed events: {$failedCount} / {$response->getTotalEvents()}";
     */
    public function countByStatus(EventStatus|string $status): int
    {
        return count($this->getByStatus($status));
    }

    /**
     * Get all events that are currently pending delivery
     * 
     * Shorthand for getByStatus(EventStatus::PENDING).
     *
     * @return OutboundEvent[] Array of pending events
     */
    public function getPendingEvents(): array
    {
        return $this->getByStatus(EventStatus::PENDING);
    }

    /**
     * Get all events that were successfully delivered
     * 
     * Shorthand for getByStatus(EventStatus::DELIVERED).
     *
     * @return OutboundEvent[] Array of delivered events
     */
    public function getDeliveredEvents(): array
    {
        return $this->getByStatus(EventStatus::DELIVERED);
    }

    /**
     * Get all events that failed delivery
     * 
     * Shorthand for getByStatus(EventStatus::FAILED).
     *
     * @return OutboundEvent[] Array of failed events
     */
    public function getFailedEvents(): array
    {
        return $this->getByStatus(EventStatus::FAILED);
    }

    /**
     * Check if all events in the batch were successfully delivered
     * 
     * Note: For newly created batches, this will typically return false
     * since events start in "pending" status and are processed asynchronously.
     *
     * @return bool True if all events are delivered, false otherwise
     * 
     * @example
     * if ($response->isAllDelivered()) {
     *     echo "All events delivered successfully!";
     * }
     */
    public function isAllDelivered(): bool
    {
        $deliveredCount = $this->countByStatus(EventStatus::DELIVERED);

        return $deliveredCount === $this->totalEvents && $this->totalEvents > 0;
    }

    /**
     * Check if any events in the batch failed delivery
     *
     * @return bool True if at least one event failed, false if none failed
     * 
     * @example
     * if ($response->hasFailures()) {
     *     $failed = $response->getFailedEvents();
     *     // Handle failed events
     * }
     */
    public function hasFailures(): bool
    {
        return $this->countByStatus(EventStatus::FAILED) > 0;
    }

    /**
     * Check if all events are still pending
     * 
     * Common for newly created batches that haven't been
     * processed yet by the event delivery system.
     *
     * @return bool True if all events are pending, false otherwise
     */
    public function isAllPending(): bool
    {
        $pendingCount = $this->countByStatus(EventStatus::PENDING);

        return $pendingCount === $this->totalEvents && $this->totalEvents > 0;
    }

    /**
     * Get the idempotency key for this request
     * 
     * Idempotency keys prevent duplicate event deliveries when
     * a request is retried. Same key = same batch won't be created twice.
     * Returns null if no idempotency key was used for this request.
     *
     * @return string|null The idempotency key, or null if not present
     * 
     * @example
     * $key = $response->getIdempotencyKey();
     * if ($key) {
     *     // Store for retry logic or debugging
     * }
     */
    public function getIdempotencyKey(): ?string
    {
        return $this->idempotencyKey;
    }

    /**
     * Convert the batch response to an array representation
     * 
     * Useful for logging, debugging, JSON serialization, or storing
     * the response data in a database. Includes batch metadata and
     * all events with their full details.
     *
     * @return array<string, mixed> Array containing batch and response data
     * 
     * @example
     * $data = $response->toArray();
     * Log::info('Batch created', $data);
     * 
     * // Or convert to JSON
     * $json = json_encode($response->toArray());
     */
    public function toArray(): array
    {
        return [
            'batch_id' => $this->batchId,
            'total_events' => $this->totalEvents,
            'events' => array_map(
                fn(OutboundEvent $event) => [
                    'uuid' => $event->uuid,
                    'event_type' => $event->eventType,
                    'status' => $event->status->value,
                    'payload' => $event->payload,
                    'tags' => $event->tags,
                    'scheduled_at' => $event->scheduledAt,
                    'created_at' => $event->createdAt,
                ],
                $this->outboundEvents
            ),
            'message' => $this->message,
            'success' => $this->success,
            'status_code' => $this->statusCode,
            'errors' => $this->errors,
        ];
    }
}
