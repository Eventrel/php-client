<?php

namespace Eventrel\Client\Responses;

class WebhookResponse
{
    // Define properties and methods for the WebhookResponse class

    public function __construct(
        private array $data
    ) {
        // 
    }

    // public function getId(): string
    // {
    //     return $this->data['id'] ?? '';
    // }

    // public function getEventType(): string
    // {
    //     return $this->data['event_type'] ?? '';
    // }

    // public function getPayload(): array
    // {
    //     return $this->data['payload'] ?? [];
    // }

    // public function getStatus(): string
    // {
    //     return $this->data['status'] ?? '';
    // }

    // public function getIdempotencyKey(): ?string
    // {
    //     return $this->data['idempotency_key'] ?? null;
    // }

    // public function getCreatedAt(): ?Carbon
    // {
    //     $timestamp = $this->data['created_at'] ?? null;
    //     return $timestamp ? Carbon::parse($timestamp) : null;
    // }

    // public function getScheduledAt(): ?Carbon
    // {
    //     $timestamp = $this->data['scheduled_at'] ?? null;
    //     return $timestamp ? Carbon::parse($timestamp) : null;
    // }

    // public function getProcessedAt(): ?Carbon
    // {
    //     $timestamp = $this->data['processed_at'] ?? null;
    //     return $timestamp ? Carbon::parse($timestamp) : null;
    // }

    // public function getFailureCount(): int
    // {
    //     return $this->data['failure_count'] ?? 0;
    // }

    // public function getLastFailureReason(): ?string
    // {
    //     return $this->data['last_failure_reason'] ?? null;
    // }

    // public function isSuccessful(): bool
    // {
    //     return $this->getStatus() === 'delivered';
    // }

    // public function isFailed(): bool
    // {
    //     return $this->getStatus() === 'failed';
    // }

    // public function isPending(): bool
    // {
    //     return in_array($this->getStatus(), ['pending', 'queued', 'processing']);
    // }

    // public function toArray(): array
    // {
    //     return $this->data;
    // }
}
