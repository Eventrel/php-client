<?php

namespace Eventrel\Client\Entities;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Event filtering configuration
 * 
 * Controls which event types are allowed to be delivered to this destination.
 * When enabled, only events matching the allowed list will be sent.
 * 
 * @package Eventrel\Client\Entities
 */
#[MapName(SnakeCaseMapper::class)]
class EventFiltering extends Data
{
    public function __construct(
        /** Whether event filtering is enabled */
        public ?bool $enabled,

        /** List of allowed event types (null = all events) */
        public ?array $allowedEvents,
    ) {
        //
    }

    /**
     * Check if a specific event type is allowed
     * 
     * @param string $eventType
     * @return bool
     */
    public function isEventAllowed(string $eventType): bool
    {
        if (!$this->enabled) {
            return true; // Filtering disabled, all events allowed
        }

        if ($this->allowedEvents === null) {
            return true; // No specific filter, all events allowed
        }

        return in_array($eventType, $this->allowedEvents, true);
    }

    /**
     * Get count of allowed event types
     * 
     * @return int|null Null if all events are allowed
     */
    public function getAllowedEventCount(): ?int
    {
        if (!$this->enabled || $this->allowedEvents === null) {
            return null;
        }

        return count($this->allowedEvents);
    }
}
