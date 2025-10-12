<?php

namespace Eventrel\Client\Responses;

use Eventrel\Client\Entities\OutboundEvent;
use Eventrel\Client\Enums\EventStatus;
use GuzzleHttp\Psr7\Response;

/**
 * Response object for bulk event retry operations
 * 
 * Represents the API response when retrying multiple events in a single request.
 * Contains the full event data for all retried events along with retry statistics
 * and status information.
 * 
 * @package Eventrel\Client\Responses
 */
class BulkRetryResponse extends BaseResponse
{
    /**
     * Array of OutboundEvent objects for each retried event
     * 
     * @var OutboundEvent[]
     */
    private array $outboundEvents;

    /**
     * Number of events successfully retried
     * 
     * @var int
     */
    private int $retriedCount;

    /**
     * Response status string
     * 
     * @var string
     */
    private string $status;

    /**
     * Create a new BulkRetryResponse instance
     * 
     * Parses the Guzzle HTTP response and extracts all retry data,
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
     * into OutboundEvent objects for easier manipulation and type safety.
     *
     * @return void
     */
    protected function parseResponse(): void
    {
        parent::parseResponse(); // Parse common fields first

        $this->retriedCount = $this->data['retried_count'] ?? 0;
        $this->status = $this->content['status'] ?? 'unknown';

        // Convert each event array into an OutboundEvent object
        $this->outboundEvents = array_map(
            fn(array $event) => OutboundEvent::from($event),
            $this->data['outbound_events'] ?? []
        );
    }

    /**
     * Get the count of events that were retried
     * 
     * This is the total number of events that the API attempted to retry,
     * regardless of their current delivery status.
     *
     * @return int Number of events retried
     * 
     * @example
     * $count = $response->getRetriedCount();
     * echo "Retried {$count} events";
     */
    public function getRetriedCount(): int
    {
        return $this->retriedCount;
    }

    /**
     * Get all retried events
     * 
     * Returns an array of OutboundEvent objects with their current state
     * after the retry operation, including updated retry counts and timestamps.
     *
     * @return OutboundEvent[] Array of OutboundEvent objects
     * 
     * @example
     * foreach ($response->getEvents() as $event) {
     *     echo "Event {$event->uuid}: {$event->status->value} (Retry #{$event->retryCount})\n";
     * }
     */
    public function getEvents(): array
    {
        return $this->outboundEvents;
    }

    /**
     * Get a specific event by its UUID
     * 
     * Searches through all retried events to find one matching
     * the provided UUID.
     *
     * @param string $uuid The event UUID to find
     * @return OutboundEvent|null The matching event, or null if not found
     * 
     * @example
     * $event = $response->getEventByUuid('evt_abc123');
     * if ($event) {
     *     echo "Event retry count: {$event->retryCount}";
     * }
     */
    public function getEventByUuid(string $uuid): ?OutboundEvent
    {
        foreach ($this->outboundEvents as $event) {
            if ($event->uuid === $uuid) {
                return $event;
            }
        }

        return null;
    }

    /**
     * Get events filtered by status
     * 
     * Returns all events that match the specified status after the retry.
     * Useful for checking which events are now pending, delivered, or still failed.
     *
     * @param EventStatus|string $status The status to filter by (e.g., EventStatus::PENDING or 'pending')
     * @return OutboundEvent[] Array of matching events
     * 
     * @example
     * // Check which events are still pending after retry
     * $stillPending = $response->getEventsByStatus(EventStatus::PENDING);
     * 
     * // Check if any are now delivered
     * $delivered = $response->getEventsByStatus(EventStatus::DELIVERED);
     */
    public function getEventsByStatus(EventStatus|string $status): array
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
     * Useful for generating summary statistics about the retry operation.
     *
     * @param EventStatus|string $status The status to count
     * @return int Number of events with that status
     * 
     * @example
     * $pendingCount = $response->countByStatus(EventStatus::PENDING);
     * $failedCount = $response->countByStatus(EventStatus::FAILED);
     * echo "Pending: {$pendingCount}, Failed: {$failedCount}";
     */
    public function countByStatus(EventStatus|string $status): int
    {
        return count($this->getEventsByStatus($status));
    }

    /**
     * Get events that are still pending after retry
     * 
     * These events have been queued for redelivery and are awaiting processing.
     *
     * @return OutboundEvent[] Array of pending events
     */
    public function getPendingEvents(): array
    {
        return $this->getEventsByStatus(EventStatus::PENDING);
    }

    /**
     * Get events that are still failed after retry
     * 
     * These events failed the retry attempt and may need further investigation.
     *
     * @return OutboundEvent[] Array of failed events
     */
    public function getFailedEvents(): array
    {
        return $this->getEventsByStatus(EventStatus::FAILED);
    }

    /**
     * Get events that were successfully delivered after retry
     * 
     * Note: This may be empty immediately after retry as events are processed asynchronously.
     *
     * @return OutboundEvent[] Array of delivered events
     */
    public function getDeliveredEvents(): array
    {
        return $this->getEventsByStatus(EventStatus::DELIVERED);
    }

    /**
     * Get the response status string
     * 
     * @return string Status string (e.g., 'success', 'error')
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * Check if all retried events are now pending
     * 
     * Common immediately after retry as events are queued for reprocessing.
     *
     * @return bool True if all events are pending
     */
    public function isAllPending(): bool
    {
        $pendingCount = $this->countByStatus(EventStatus::PENDING);

        return $pendingCount === $this->retriedCount && $this->retriedCount > 0;
    }

    /**
     * Check if any events are still failing
     * 
     * Use this to determine if additional retry attempts or investigation are needed.
     *
     * @return bool True if at least one event is still failed
     */
    public function hasFailures(): bool
    {
        return $this->countByStatus(EventStatus::FAILED) > 0;
    }

    /**
     * Convert the retry response to an array representation
     * 
     * Useful for logging, debugging, JSON serialization, or storing
     * the response data in a database. Includes retry metadata and
     * all events with their full details.
     *
     * @return array<string, mixed> Array containing retry and response data
     * 
     * @example
     * $data = $response->toArray();
     * Log::info('Bulk retry completed', $data);
     * 
     * // Or convert to JSON
     * $json = json_encode($response->toArray());
     */
    public function toArray(): array
    {
        return [
            'retried_count' => $this->retriedCount,
            'message' => $this->message,
            'events' => array_map(
                fn(OutboundEvent $event) => [
                    'uuid' => $event->uuid,
                    'event_type' => $event->eventType,
                    'status' => $event->status->value,
                    'retry_count' => $event->retryCount,
                    'failure_reason' => $event->failureReason,
                    'last_attempted_at' => $event->lastAttemptedAt,
                    'payload' => $event->payload,
                    'tags' => $event->tags,
                ],
                $this->outboundEvents
            ),
            'success' => $this->success,
            'status' => $this->status,
            'status_code' => $this->statusCode,
            'errors' => $this->errors,
        ];
    }
}
