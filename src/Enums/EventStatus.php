<?php

namespace Eventrel\Enums;

enum EventStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case DELIVERED = 'delivered';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';

    /**
     * Get human-readable label for the status.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::PROCESSING => 'Processing',
            self::DELIVERED => 'Delivered',
            self::FAILED => 'Failed',
            self::CANCELLED => 'Cancelled',
        };
    }

    /**
     * Check if status represents a final state.
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::DELIVERED, self::FAILED, self::CANCELLED]);
    }

    /**
     * Check if status allows retries.
     */
    public function canRetry(): bool
    {
        return $this === self::FAILED;
    }

    /**
     * Check if status allows cancellation.
     */
    public function canCancel(): bool
    {
        return in_array($this, [self::PENDING, self::PROCESSING]);
    }
}
