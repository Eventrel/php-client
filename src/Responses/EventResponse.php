<?php

namespace Eventrel\Client\Responses;

use Eventrel\Client\Entities\OutboundEvent;
use Eventrel\Client\Enums\EventStatus;
use GuzzleHttp\Psr7\Response;

/**
 * Represents a response from the Eventrel Events API.
 * 
 * This class wraps the HTTP response and provides convenient access to:
 * - Outbound event details (ID, type, payload, status)
 * - Response metadata (success status, errors, status code)
 * - Request tracking (idempotency key, headers)
 */
class EventResponse extends BaseResponse
{
    /**
     * The outbound event entity containing event details.
     */
    private OutboundEvent $outboundEvent;

    /**
     * Unique key for idempotent request handling.
     */
    private ?string $idempotencyKey;

    /**
     * Create a new EventResponse instance.
     * 
     * Parses the Guzzle HTTP response and extracts all relevant data
     * including the outbound event, metadata, and headers.
     *
     * @param Response $response The Guzzle HTTP response object
     */
    public function __construct(Response $response)
    {
        parent::__construct($response);
    }

    /**
     * Parse and populate response data from the Guzzle HTTP response.
     * 
     * Extracts JSON content, headers, and idempotency information,
     * populating all class properties for convenient access.
     *
     * @return void
     */
    protected function parseResponse(): void
    {
        parent::parseResponse(); // Parse common fields first

        $this->outboundEvent = OutboundEvent::from($this->data['outbound_event'] ?? []);

        // Check header first, then fallback to body
        $this->idempotencyKey = $this->response->hasHeader('x-idempotency-key')
            ? $this->response->getHeaderLine('x-idempotency-key')
            : ($this->data['idempotency_key'] ?? null);
    }

    /**
     * Get the unique identifier (UUID) of the outbound event.
     * 
     * This ID can be used to track the event's delivery status,
     * query for delivery attempts, or reference this event in support requests.
     *
     * @return string The event UUID
     */
    public function getId(): string
    {
        return $this->outboundEvent->uuid;
    }

    /**
     * Get the event type identifier.
     * 
     * Event types categorize events (e.g., "payment.completed", 
     * "user.registered") and help receiving systems route or handle them.
     *
     * @return string The event type string
     */
    public function getEventType(): string
    {
        return $this->outboundEvent->eventType;
    }

    /**
     * Get the current status of the outbound event.
     * 
     * Status indicates the delivery state: pending, delivered, failed, etc.
     * Use this to determine if further action is needed.
     *
     * @return EventStatus The event status enum
     */
    public function getStatus(): EventStatus
    {
        return $this->outboundEvent->status;
    }

    /**
     * Get the event payload data.
     * 
     * This is the actual data sent to the event endpoint,
     * containing the business information for this event.
     *
     * @return array The event payload as an associative array
     */
    public function getPayload(): array
    {
        return $this->outboundEvent->payload ?? [];
    }

    /**
     * Get the tags associated with this event.
     * 
     * Tags can be used for categorization, filtering, or routing.
     * Includes both batch-level tags (if sent in a batch) and
     * event-specific tags.
     *
     * @return array Array of tag strings
     */
    public function getTags(): array
    {
        return $this->outboundEvent->tags ?? [];
    }

    /**
     * Get the complete outbound event entity.
     * 
     * Returns the full OutboundEvent object with all properties
     * including timestamps, retry count, and delivery metadata.
     *
     * @return OutboundEvent The complete event entity
     */
    public function getDetails(): OutboundEvent
    {
        return $this->outboundEvent;
    }

    /**
     * Get the reason why the event delivery failed.
     * 
     * Returns detailed error information when delivery attempts fail,
     * including HTTP errors, timeouts, or endpoint issues.
     *
     * @return string|null The failure reason, or null if not failed
     */
    public function getFailureReason(): ?string
    {
        return $this->outboundEvent->failureReason;
    }

    /**
     * Get the reason why the event was cancelled.
     * 
     * Returns the cancellation reason if the event delivery
     * was manually cancelled or stopped by the system.
     *
     * @return string|null The cancellation reason, or null if not cancelled
     */
    public function getCancellationReason(): ?string
    {
        return $this->outboundEvent->cancelReason;
    }

    /**
     * Get the number of delivery attempts made.
     * 
     * Tracks how many times the system has tried to deliver this event.
     * Useful for monitoring reliability and identifying problematic endpoints.
     *
     * @return int The number of retry attempts
     */
    public function getRetryCount(): int
    {
        return $this->outboundEvent->retryCount ?? 0;
    }

    /**
     * Get the scheduled delivery time.
     * 
     * Returns the timestamp when this event is scheduled to be sent.
     * Null for immediate delivery events.
     *
     * @return string|null ISO 8601 timestamp, or null if not scheduled
     */
    public function getScheduledAt(): ?string
    {
        return $this->outboundEvent->scheduledAt;
    }

    /**
     * Get the timestamp of the last delivery attempt.
     * 
     * Shows when the system last tried to deliver this event,
     * regardless of success or failure.
     *
     * @return string|null ISO 8601 timestamp, or null if not attempted yet
     */
    public function getLastAttemptedAt(): ?string
    {
        return $this->outboundEvent->lastAttemptedAt;
    }

    /**
     * Get the timestamp when the event was successfully delivered.
     * 
     * Returns the exact time the receiving endpoint accepted the event
     * with a successful HTTP response.
     *
     * @return string|null ISO 8601 timestamp, or null if not delivered
     */
    public function getDeliveredAt(): ?string
    {
        return $this->outboundEvent->deliveredAt;
    }

    /**
     * Get the timestamp when the event was cancelled.
     * 
     * Returns when the delivery was cancelled, either manually
     * or automatically by the system.
     *
     * @return string|null ISO 8601 timestamp, or null if not cancelled
     */
    public function getCancelledAt(): ?string
    {
        return $this->outboundEvent->cancelledAt;
    }

    /**
     * Get the timestamp when the event was created.
     * 
     * Shows when this event was first registered in the system,
     * before any delivery attempts.
     *
     * @return string ISO 8601 timestamp
     */
    public function getCreatedAt(): string
    {
        return $this->outboundEvent->createdAt;
    }

    /**
     * Get the timestamp when the event was last updated.
     * 
     * Tracks the last modification to the event record, including
     * status changes, delivery attempts, or metadata updates.
     *
     * @return string ISO 8601 timestamp
     */
    public function getUpdatedAt(): string
    {
        return $this->outboundEvent->updatedAt;
    }

    /**
     * Check if the event is scheduled for future delivery.
     * 
     * Scheduled events will be delivered at a specified time
     * rather than immediately.
     *
     * @return bool True if the event has a scheduled delivery time
     */
    public function isScheduled(): bool
    {
        return $this->outboundEvent->scheduledAt !== null;
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
     * Convert the response to an array representation.
     * 
     * Useful for logging, debugging, JSON serialization, or storing
     * the response data in a database. Includes all key information
     * about the event and the API response.
     *
     * @return array Array containing event and response data
     */
    public function toArray(): array
    {
        return [
            'uuid' => $this->getId(),
            'event_type' => $this->getEventType(),
            'status' => $this->getStatus()->value,
            'payload' => $this->getPayload(),
            'tags' => $this->getTags(),
            'failure_reason' => $this->getFailureReason(),
            'cancellation_reason' => $this->getCancellationReason(),
            'retry_count' => $this->getRetryCount(),
            'scheduled_at' => $this->getScheduledAt(),
            'last_attempted_at' => $this->getLastAttemptedAt(),
            'delivered_at' => $this->getDeliveredAt(),
            'cancelled_at' => $this->getCancelledAt(),
            'created_at' => $this->getCreatedAt(),
            'updated_at' => $this->getUpdatedAt(),
            'message' => $this->getMessage(),
            'success' => $this->isSuccess(),
            'status_code' => $this->getStatusCode(),
            'errors' => $this->getErrors(),
            'idempotency_key' => $this->getIdempotencyKey(),
        ];
    }
}
