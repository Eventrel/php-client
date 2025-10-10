<?php

namespace Eventrel\Client\Entities;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class OutboundEvent extends Data
{
    public function __construct(
        public string $uuid,
        public string $eventType,
        public array $payload,
        // public string $idempotencyKey,
        public ?string $batch,
        // public ?string $scheduledAt,
        public array $tags,
        // public string $status,
        // public string $updatedAt,
        // public string $createdAt
    ) {
        // 
    }
}

// "event_type": "event.created",
// "payload": {
//     "user_id": 123
// },
// "idempotency_key": "ctx_1ede534b269f4960f8c5aa88e461b606",
// "endpoint_id": null,
// "batch": null,
// "scheduled_at": null,
// "tags": [
//     "new_order"
// ],
// "status": "pending",
// "uuid": "01993b37-c347-731f-acde-32566b19834f",
// "updated_at": "2025-09-11T23:58:54.000000Z",
// "created_at": "2025-09-11T23:58:54.000000Z"