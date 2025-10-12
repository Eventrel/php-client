<?php

namespace Eventrel\Client\Entities;

use Carbon\Carbon;
use Eventrel\Client\Enums\EventStatus;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class OutboundEvent extends Data
{
    /**
     * OutboundEvent constructor.
     * 
     * @param string $uuid
     * @param string $idempotencyKey
     * @param string $eventType
     * @param array $payload
     * @param EventStatus $status
     * @param array $tags
     * @param Carbon $createdAt
     * @param Carbon $updatedAt
     * @param string|null $batch
     * @param string|null $failureReason
     * @param string|null $cancelReason
     * @param int|null $retryCount
     * @param Carbon|null $scheduledAt
     * @param Carbon|null $lastAttemptedAt
     * @param Carbon|null $deliveredAt
     * @param Carbon|null $cancelledAt
     * @param array|null $metadata
     */
    public function __construct(
        public string $uuid,
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
}
