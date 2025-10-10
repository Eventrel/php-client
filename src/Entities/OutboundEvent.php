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
     * @param string $eventType
     * @param array $payload
     * @param string $idempotencyKey
     * @param string|null $batch
     * @param Carbon|null $scheduledAt
     * @param array $tags
     * @param EventStatus $status
     * @param Carbon $updatedAt
     * @param Carbon $createdAt
     */
    public function __construct(
        public string $uuid,
        public string $eventType,
        public array $payload,
        public string $idempotencyKey,
        public ?string $batch,
        public ?Carbon $scheduledAt,
        public array $tags,
        public EventStatus $status,
        public Carbon $updatedAt,
        public Carbon $createdAt
    ) {
        // 
    }
}
