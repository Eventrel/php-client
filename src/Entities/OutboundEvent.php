<?php

namespace Eventrel\Entities;

use Carbon\Carbon;
use Eventrel\Enums\EventStatus;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Outbound Event entity
 * 
 * Represents an event that has been queued or delivered to a destination.
 * Tracks the complete lifecycle of an event from creation through delivery,
 * including retry attempts, failures, and cancellations.
 * 
 * This immutable data object automatically converts between snake_case (API)
 * and camelCase (PHP) using Spatie's Laravel Data package.
 * 
 * Event Lifecycle States:
 * - PENDING: Queued and awaiting delivery
 * - DELIVERED: Successfully delivered to destination
 * - FAILED: Delivery failed after retries exhausted
 * - CANCELLED: Manually cancelled or expired
 * 
 * Key Features:
 * - Idempotency support to prevent duplicate deliveries
 * - Automatic retry tracking with failure reasons
 * - Scheduled delivery for future events
 * - Batch processing support
 * - Flexible tagging and metadata
 * - Complete audit trail with timestamps
 * 
 * @package Eventrel\Entities
 * 
 * @property string $uuid Unique event identifier
 * @property string|null $idempotencyKey Key for preventing duplicate events
 * @property string $eventType Event type identifier (e.g., 'user.created')
 * @property array<string, mixed> $payload Event data sent to destination
 * @property EventStatus $status Current delivery status
 * @property array<int, string> $tags Categorization tags
 * @property Carbon $createdAt When the event was created
 * @property Carbon $updatedAt When the event was last modified
 * @property string|null $batch Batch identifier if part of batch
 * @property string|null $failureReason Why delivery failed (if status is FAILED)
 * @property string|null $cancelReason Why event was cancelled (if status is CANCELLED)
 * @property int|null $retryCount Number of delivery attempts made
 * @property Carbon|null $scheduledAt When event is scheduled for delivery
 * @property Carbon|null $lastAttemptedAt Timestamp of most recent delivery attempt
 * @property Carbon|null $deliveredAt When successfully delivered
 * @property Carbon|null $cancelledAt When cancelled
 * @property array<string, mixed>|null $metadata Additional event metadata
 * 
 * @example
 * // Create from API response
 * $event = OutboundEvent::from($apiResponse['outbound_event']);
 * 
 * // Check status
 * if ($event->isPending()) {
 *     echo "Event is queued for delivery";
 * }
 * 
 * // Access properties
 * echo "Event {$event->uuid}: {$event->eventType}";
 * echo "Status: {$event->status->value}";
 * echo "Created: {$event->createdAt->diffForHumans()}";
 * 
 * // Work with payload
 * $userId = $event->payload['user_id'] ?? null;
 * 
 * // Check if scheduled
 * if ($event->isScheduled()) {
 *     echo "Scheduled for: {$event->scheduledAt->format('Y-m-d H:i:s')}";
 * }
 */
#[MapName(SnakeCaseMapper::class)]
class OutboundEvent extends Data
{
    /**
     * Create a new OutboundEvent entity
     * 
     * This constructor is primarily used internally when hydrating from API responses.
     * Use OutboundEvent::from() to create instances from arrays or JSON.
     * 
     * @param string $uuid Unique identifier for the event (UUID v7 format)
     * @param string $identifier Client-defined event identifier for idempotency
     * @param string|null $idempotencyKey Idempotency key used when creating the event.
     *                                     Ensures the same key won't create duplicate events.
     *                                     Null if no idempotency key was provided.
     * @param string $eventType The event type identifier following dot notation
     *                          (e.g., 'user.created', 'payment.completed', 'order.shipped')
     * @param array<string, mixed> $payload The event data/body sent to the destination webhook.
     *                                       Contains business logic data relevant to the event type.
     * @param EventStatus $status Current delivery status: PENDING, DELIVERED, FAILED, or CANCELLED
     * @param array<int, string> $tags Array of string tags for categorization and filtering
     *                                  (e.g., ['premium', 'urgent', 'user-facing'])
     * @param Carbon $createdAt ISO 8601 timestamp when the event was created/queued
     * @param Carbon $updatedAt ISO 8601 timestamp of the last status change or modification
     * @param string|null $batch Batch identifier if this event is part of a batch submission.
     *                           Multiple events can share the same batch ID.
     * @param string|null $failureReason Detailed error message if delivery failed.
     *                                    Includes HTTP errors, timeouts, or endpoint issues.
     *                                    Only set when status is FAILED.
     * @param string|null $cancelReason Explanation for why the event was cancelled.
     *                                   Set manually or by system when cancelling.
     *                                   Only set when status is CANCELLED.
     * @param int|null $retryCount Number of delivery attempts made so far.
     *                              Increments with each retry until limit is reached.
     *                              0 = first attempt, null = no attempts yet
     * @param Carbon|null $scheduledAt ISO 8601 timestamp for when the event should be delivered.
     *                                  Null for immediate delivery. Used for scheduled/delayed events.
     * @param Carbon|null $lastAttemptedAt ISO 8601 timestamp of the most recent delivery attempt,
     *                                      regardless of success or failure. Null if never attempted.
     * @param Carbon|null $deliveredAt ISO 8601 timestamp when the event was successfully delivered
     *                                  (received successful HTTP response from destination).
     *                                  Only set when status is DELIVERED.
     * @param Carbon|null $cancelledAt ISO 8601 timestamp when the event was cancelled.
     *                                  Only set when status is CANCELLED.
     * @param array<string, mixed>|null $metadata Additional metadata attached to the event.
     *                                            Can store arbitrary key-value data for internal use,
     *                                            tracking, or context that doesn't belong in payload.
     */
    public function __construct(
        public string $uuid,
        public string $identifier,
        public ?string $idempotencyKey,
        public string $eventType,
        public array $payload,
        public EventStatus $status,
        public array $tags,
        public Carbon $createdAt,
        public Carbon $updatedAt,
        public ?string $batch = null,
        public ?string $failureReason = null,
        public ?string $cancelReason = null,
        public ?int $retryCount = null,
        public ?Carbon $scheduledAt = null,
        public ?Carbon $lastAttemptedAt = null,
        public ?Carbon $deliveredAt = null,
        public ?Carbon $cancelledAt = null,
        public ?array $metadata = null,
    ) {
        //
    }

    /**
     * Check if the event is pending delivery
     * 
     * @return bool True if status is PENDING
     * 
     * @example
     * if ($event->isPending()) {
     *     echo "Event is queued for delivery";
     * }
     */
    public function isPending(): bool
    {
        return $this->status === EventStatus::PENDING;
    }

    /**
     * Check if the event was successfully delivered
     * 
     * @return bool True if status is DELIVERED
     * 
     * @example
     * if ($event->isDelivered()) {
     *     echo "Delivered at: {$event->deliveredAt->format('Y-m-d H:i:s')}";
     * }
     */
    public function isDelivered(): bool
    {
        return $this->status === EventStatus::DELIVERED;
    }

    /**
     * Check if the event delivery failed
     * 
     * @return bool True if status is FAILED
     * 
     * @example
     * if ($event->isFailed()) {
     *     echo "Failed: {$event->failureReason}";
     *     echo "Retry count: {$event->retryCount}";
     * }
     */
    public function isFailed(): bool
    {
        return $this->status === EventStatus::FAILED;
    }

    /**
     * Check if the event was cancelled
     * 
     * @return bool True if status is CANCELLED
     * 
     * @example
     * if ($event->isCancelled()) {
     *     echo "Cancelled: {$event->cancelReason}";
     * }
     */
    public function isCancelled(): bool
    {
        return $this->status === EventStatus::CANCELLED;
    }

    /**
     * Check if the event is scheduled for future delivery
     * 
     * @return bool True if scheduledAt is set
     * 
     * @example
     * if ($event->isScheduled()) {
     *     echo "Scheduled for: {$event->scheduledAt->diffForHumans()}";
     * }
     */
    public function isScheduled(): bool
    {
        return $this->scheduledAt !== null;
    }

    /**
     * Check if the event is part of a batch
     * 
     * @return bool True if batch identifier is set
     * 
     * @example
     * if ($event->isPartOfBatch()) {
     *     echo "Batch: {$event->batch}";
     * }
     */
    public function isPartOfBatch(): bool
    {
        return $this->batch !== null;
    }

    /**
     * Check if the event has been attempted for delivery
     * 
     * @return bool True if at least one delivery attempt was made
     * 
     * @example
     * if ($event->hasBeenAttempted()) {
     *     echo "Last attempt: {$event->lastAttemptedAt->diffForHumans()}";
     *     echo "Attempts: {$event->retryCount}";
     * }
     */
    public function hasBeenAttempted(): bool
    {
        return $this->lastAttemptedAt !== null;
    }

    /**
     * Check if the event has a specific tag
     * 
     * @param string $tag The tag to check for
     * @return bool True if the tag exists
     * 
     * @example
     * if ($event->hasTag('urgent')) {
     *     // Handle urgent events with priority
     * }
     * 
     * if ($event->hasTag('premium')) {
     *     // Apply premium processing
     * }
     */
    public function hasTag(string $tag): bool
    {
        return in_array($tag, $this->tags, true);
    }

    /**
     * Get a payload value by key with optional default
     * 
     * @param string $key The payload key to retrieve
     * @param mixed $default Default value if key doesn't exist
     * @return mixed The payload value or default
     * 
     * @example
     * $userId = $event->getPayloadValue('user_id');
     * $email = $event->getPayloadValue('email', 'unknown@example.com');
     * $amount = $event->getPayloadValue('amount', 0);
     */
    public function getPayloadValue(string $key, $default = null): mixed
    {
        return $this->payload[$key] ?? $default;
    }

    /**
     * Get a metadata value by key with optional default
     * 
     * @param string $key The metadata key to retrieve
     * @param mixed $default Default value if key doesn't exist
     * @return mixed The metadata value or default
     * 
     * @example
     * $source = $event->getMetadata('source', 'api');
     * $version = $event->getMetadata('version');
     */
    public function getMetadata(string $key, $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Get the age of the event since creation
     * 
     * @return \DateInterval Age of the event
     * 
     * @example
     * $age = $event->getAge();
     * echo "Event created {$age->format('%d days, %h hours ago')}";
     * 
     * // Or use Carbon's helper
     * echo "Created: {$event->createdAt->diffForHumans()}";
     */
    public function getAge(): \DateInterval
    {
        return $this->createdAt->diff(Carbon::now());
    }

    /**
     * Get a summary of the event's current state
     * 
     * @return array<string, mixed> Summary information
     * 
     * @example
     * $summary = $event->getSummary();
     * // [
     * //     'uuid' => 'evt_123...',
     * //     'type' => 'user.created',
     * //     'status' => 'delivered',
     * //     'scheduled' => false,
     * //     'batched' => false,
     * //     'attempts' => 1,
     * //     'age_seconds' => 3600
     * // ]
     */
    public function getSummary(): array
    {
        return [
            'uuid' => $this->uuid,
            'identifier' => $this->identifier,
            'type' => $this->eventType,
            'status' => $this->status->value,
            'scheduled' => $this->isScheduled(),
            'batched' => $this->isPartOfBatch(),
            'attempts' => $this->retryCount ?? 0,
            'age_seconds' => $this->createdAt->diffInSeconds(Carbon::now()),
        ];
    }
}
