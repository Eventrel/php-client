<?php

namespace Eventrel\Client\Responses;

use Eventrel\Client\Entities\OutboundEvent;
use Eventrel\Client\Enums\EventStatus;
use GuzzleHttp\Psr7\Response;

class BatchEventResponse
{
    /**
     * The unique batch identifier.
     */
    private string $batchId;

    /**
     * Array of OutboundEvent objects for each event in the batch.
     * 
     * @var OutboundEvent[]
     */
    private array $outboundEvents;

    /**
     * Total number of events in the batch.
     */
    private int $totalEvents;

    /**
     * Human-readable message from the API response.
     */
    private string $message;

    /**
     * Array of validation or processing errors, if any.
     */
    private array $errors;

    /**
     * Whether the batch request was successful.
     */
    private bool $success;

    /**
     * HTTP status code returned by the API.
     */
    private int $statusCode;

    /**
     * HTTP headers from the response.
     */
    private array $headers;

    /**
     * Unique key for idempotent request handling.
     */
    private ?string $idempotencyKey;

    /**
     * Create a new BatchEventResponse instance.
     * 
     * Parses the Guzzle HTTP response and extracts all batch data,
     * converting each event into an OutboundEvent object.
     *
     * @param Response $response The Guzzle HTTP response object
     */
    public function __construct(
        private Response $response
    ) {
        $this->parseResponse();
    }

    /**
     * Parse and populate response data from the Guzzle HTTP response.
     * 
     * Extracts JSON content and converts the outbound_events array
     * into OutboundEvent objects for type-safe access.
     *
     * @return void
     */
    private function parseResponse(): void
    {
        $content = json_decode($this->response->getBody()->getContents(), true) ?? [];
        $data = $content['data'] ?? [];

        $this->batchId = $data['batch'] ?? '';
        $this->totalEvents = $data['total_events'] ?? 0;
        $this->message = $data['message'] ?? '';
        $this->errors = $content['errors'] ?? [];
        $this->success = $content['success'] ?? false;
        $this->statusCode = $content['status_code'] ?? 0;
        $this->headers = $this->response->getHeaders();

        // Check header first, then fallback to body
        $this->idempotencyKey = $this->response->hasHeader('x-idempotency-key')
            ? $this->response->getHeaderLine('x-idempotency-key')
            : ($data['idempotency_key'] ?? null);

        // Convert each event array into an OutboundEvent object
        $this->outboundEvents = array_map(
            fn(array $event) => OutboundEvent::from($event),
            $data['outbound_events'] ?? []
        );
    }

    /**
     * Get the unique batch identifier.
     * 
     * This ID can be used to track the batch status, query for
     * delivery attempts, or reference this batch in support requests.
     *
     * @return string The batch UUID
     */
    public function getBatchId(): string
    {
        return $this->batchId;
    }

    /**
     * Get the total number of events in the batch.
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
     * Get the human-readable response message.
     * 
     * Provides context about the batch result, useful for
     * logging or displaying feedback to users.
     *
     * @return string The response message
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Get all outbound events in the batch.
     * 
     * Returns an array of OutboundEvent objects, one for each
     * event in the batch, in the order they were submitted.
     *
     * @return OutboundEvent[] Array of OutboundEvent objects
     */
    public function getEvents(): array
    {
        return $this->outboundEvents;
    }

    /**
     * Get a specific event by its UUID.
     * 
     * Searches through all events in the batch to find one matching
     * the provided UUID. Useful for tracking specific events or
     * checking their delivery status.
     *
     * @param string $uuid The event UUID to find
     * @return OutboundEvent|null The matching event, or null if not found
     */
    public function getEvent(int|string $identifier): ?OutboundEvent
    {
        if (is_int($identifier)) {
            return $this->getEventByIndex($identifier);
        }

        foreach ($this->outboundEvents as $event) {
            if ($event->uuid === $identifier) {
                return $event;
            }
        }

        return null;
    }

    /**
     * Get a specific event by its index in the batch.
     * 
     * Events are indexed starting from 0 in the order they
     * were added to the batch.
     *
     * @param int $index The zero-based index of the event
     * @return OutboundEvent|null The event at the index, or null if not found
     */
    public function getEventByIndex(int $index): ?OutboundEvent
    {
        return $this->outboundEvents[$index] ?? null;
    }

    /**
     * Get the first event in the batch.
     * 
     * Convenience method for accessing the first event.
     *
     * @return OutboundEvent|null The first event, or null if batch is empty
     */
    public function first(): ?OutboundEvent
    {
        return $this->outboundEvents[0] ?? null;
    }

    /**
     * Get the last event in the batch.
     * 
     * Convenience method for accessing the last event.
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
     * Get events filtered by status.
     * 
     * Returns all events that match the specified status.
     * Useful for finding pending, delivered, or failed events.
     *
     * @param EventStatus|string $status The status to filter by
     * @return OutboundEvent[] Array of matching events
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
     * Get the count of events with a specific status.
     * 
     * Useful for generating summary statistics about the batch.
     *
     * @param EventStatus|string $status The status to count
     * @return int Number of events with that status
     */
    public function countByStatus(EventStatus|string $status): int
    {
        return count($this->getEventsByStatus($status));
    }

    /**
     * Get all events that are currently pending delivery.
     *
     * @return OutboundEvent[] Array of pending events
     */
    public function getPendingEvents(): array
    {
        return $this->getEventsByStatus(EventStatus::PENDING);
    }

    /**
     * Get all events that were successfully delivered.
     *
     * @return OutboundEvent[] Array of delivered events
     */
    public function getDeliveredEvents(): array
    {
        return $this->getEventsByStatus(EventStatus::DELIVERED);
    }

    /**
     * Get all events that failed delivery.
     *
     * @return OutboundEvent[] Array of failed events
     */
    public function getFailedEvents(): array
    {
        return $this->getEventsByStatus(EventStatus::FAILED);
    }

    /**
     * Check if all events in the batch were successfully delivered.
     * 
     * Note: For newly created batches, this will be false since
     * events start in "pending" status.
     *
     * @return bool True if all events are delivered
     */
    public function isAllDelivered(): bool
    {
        $deliveredCount = $this->countByStatus(EventStatus::DELIVERED);

        return $deliveredCount === $this->totalEvents && $this->totalEvents > 0;
    }

    /**
     * Check if any events in the batch failed delivery.
     *
     * @return bool True if at least one event failed
     */
    public function hasFailures(): bool
    {
        return $this->countByStatus(EventStatus::FAILED) > 0;
    }

    /**
     * Check if all events are still pending.
     * 
     * Common for newly created batches that haven't been
     * processed yet.
     *
     * @return bool True if all events are pending
     */
    public function isAllPending(): bool
    {
        $pendingCount = $this->countByStatus(EventStatus::PENDING);

        return $pendingCount === $this->totalEvents && $this->totalEvents > 0;
    }

    /**
     * Check if the API request was successful.
     * 
     * Returns true if the batch was accepted and queued.
     * Individual event delivery status should be checked separately.
     *
     * @return bool True if the request succeeded
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Get any validation or processing errors.
     * 
     * Returns an array of error details if the batch request failed.
     * Empty array if no errors.
     *
     * @return array Array of error messages/details
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get the HTTP status code from the API response.
     * 
     * Standard HTTP status codes: 201 for created, 4xx for client errors,
     * 5xx for server errors.
     *
     * @return int The HTTP status code (e.g., 201, 400, 500)
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Get all HTTP headers from the response.
     * 
     * Headers may contain rate limit info, request IDs, or other
     * metadata useful for debugging and monitoring.
     *
     * @return array Associative array of header name => value(s)
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Get the idempotency key for this request.
     * 
     * Idempotency keys prevent duplicate event deliveries when
     * a request is retried. Same key = same event won't be sent twice.
     * Returns null if no idempotency key was used.
     *
     * @return string|null The idempotency key, or null if not present
     */
    public function getIdempotencyKey(): ?string
    {
        return $this->idempotencyKey;
    }

    /**
     * Convert the batch response to an array representation.
     * 
     * Useful for logging, debugging, JSON serialization, or storing
     * the response data. Includes batch metadata and all events.
     *
     * @return array Array containing batch and response data
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
