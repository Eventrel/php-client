<?php

namespace Eventrel\Entities;

use Carbon\Carbon;
use Eventrel\Enums\WebhookMode;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Destination entity
 * 
 * Represents a webhook destination where events are delivered. A destination
 * is a configured endpoint that receives event notifications with customizable
 * delivery settings, authentication, rate limiting, and retry policies.
 * 
 * This immutable data object uses Spatie's Laravel Data package for automatic
 * JSON serialization, validation, and type casting. Field names are automatically
 * converted between snake_case (API) and camelCase (PHP) via the SnakeCaseMapper.
 * 
 * Key features:
 * - Webhook URL configuration with custom headers
 * - HMAC signature verification for security
 * - Rate limiting at minute, hour, and day levels
 * - Configurable retry and timeout policies
 * - Event filtering and batching support
 * - Bidirectional webhook support (inbound and outbound)
 * - Soft deletion for historical preservation
 * 
 * Webhook Modes:
 * - **Bidirectional**: Can send events out and receive webhooks in
 * - **Outbound**: Only sends events to external endpoints
 * - **Inbound**: Only receives webhooks from external sources
 * 
 * @package Eventrel\Entities
 * 
 * @property string $uuid Unique identifier for the destination
 * @property string $name Human-readable name
 * @property string $slug URL-friendly slug
 * @property string $identifier Application identifier (app_ prefix)
 * @property string|null $description Optional description
 * @property array<string, mixed> $metadata Additional metadata (tags, version, owner, etc.)
 * @property WebhookMode $webhookMode Webhook mode enum (bidirectional, outbound, inbound)
 * @property string $webhookUrl Webhook endpoint URL
 * @property string $webhookSecret Secret for webhook signature verification (whsec_ prefix)
 * @property string|null $inboundToken Token for inbound webhooks (wht_ prefix)
 * @property WebhookConfig|null $webhookConfig Webhook delivery configuration
 * @property array<string, string> $headers Custom HTTP headers to send with webhooks
 * @property int|null $timeout Request timeout in seconds
 * @property int|null $retryLimit Maximum retry attempts on failure
 * @property int|null $rateLimitPerMinute Maximum requests per minute
 * @property int|null $rateLimitPerHour Maximum requests per hour
 * @property int|null $rateLimitPerDay Maximum requests per day (null = unlimited)
 * @property bool $isActive Whether the destination is active
 * @property Carbon $createdAt Creation timestamp
 * @property Carbon $updatedAt Last update timestamp
 * @property Carbon|null $deletedAt Soft deletion timestamp (null if not deleted)
 * 
 * @example
 * // Create from API response
 * $destination = Destination::from($apiResponse['destination']);
 * 
 * // Access properties
 * echo $destination->name; // "Analytics Dashboard"
 * echo $destination->webhookUrl; // "https://api.example.com/webhooks"
 * echo $destination->webhookMode->value; // "bidirectional"
 * 
 * // Use helper methods
 * if ($destination->isEnabled()) {
 *     echo "Destination is active and ready";
 * }
 * 
 * if ($destination->isBidirectional()) {
 *     echo "Can send and receive webhooks";
 * }
 * 
 * // Work with timestamps
 * echo "Created: {$destination->createdAt->diffForHumans()}";
 * echo "Age: {$destination->getAge()} days";
 * 
 * // Convert to array/JSON
 * $array = $destination->toArray();
 * $json = $destination->toJson();
 */
#[MapName(SnakeCaseMapper::class)]
class Destination extends Data
{
    /**
     * Create a new Destination entity
     * 
     * This constructor is primarily used internally by Spatie Data when
     * hydrating from arrays or JSON. Use Destination::from() for creating
     * instances from API responses.
     * 
     * The SnakeCaseMapper automatically converts API field names (snake_case)
     * to PHP property names (camelCase), so 'webhook_url' becomes 'webhookUrl'.
     * 
     * @param string $uuid Unique identifier (UUID v7 format)
     *                     Example: '0199ac84-0922-7077-bf47-acb70d84931b'
     * 
     * @param string $name Human-readable name for UI display
     *                     Example: 'Analytics Dashboard', 'Production API'
     * 
     * @param string $slug URL-friendly identifier derived from name
     *                     Example: 'analytics-dashboard', 'production-api'
     * 
     * @param string $identifier Application identifier with 'app_' prefix
     *                           Used as the destination reference in API calls
     *                           Example: 'app_zYFueyPGAQnxPubJzn5Ds8p0mluHWIKgxU'
     * 
     * @param string|null $description Optional detailed description of the destination's
     *                                 purpose, owner, or usage notes
     * 
     * @param array<string, mixed> $metadata Flexible key-value metadata storage:
     *                                       - environment: dev/staging/production
     *                                       - tags: Array of categorization tags
     *                                       - version: Application version string
     *                                       - owner_email: Contact email
     *                                       - created_by: Creator name
     *                                       - business_critical: Boolean flag
     *                                       - compliance_required: Boolean flag
     * 
     * @param WebhookMode $webhookMode Enum defining webhook direction:
     *                                  - BIDIRECTIONAL: Send and receive
     *                                  - OUTBOUND: Send only
     *                                  - INBOUND: Receive only
     * 
     * @param string $webhookUrl Full HTTPS URL where webhooks are delivered
     *                           Must be publicly accessible for outbound mode
     *                           Example: 'https://api.example.com/webhooks'
     * 
     * @param string $webhookSecret HMAC secret key for signature verification
     *                              Format: 'whsec_' followed by random string
     *                              Used to sign outbound webhooks and verify inbound
     * 
     * @param string|null $inboundToken Authentication token for receiving webhooks
     *                                  Format: 'wht_' followed by random string
     *                                  Required for inbound/bidirectional modes
     * 
     * @param WebhookConfig|null $webhookConfig Detailed delivery configuration including:
     *                                          - Batching settings
     *                                          - SSL verification
     *                                          - Event filtering
     *                                          - Retry policies
     *                                          - Signature algorithms
     * 
     * @param array<string, string> $headers Custom HTTP headers sent with every webhook:
     *                                       - Authorization: Bearer tokens, API keys
     *                                       - User-Agent: Custom identification
     *                                       - X-Custom-Header: Any custom headers
     * 
     * @param int|null $timeout Maximum seconds to wait for webhook response
     *                          Default: 30 seconds
     *                          Range: 5-120 seconds recommended
     * 
     * @param int|null $retryLimit Maximum retry attempts for failed deliveries
     *                             Default: 3 retries
     *                             Failed attempts use exponential backoff
     * 
     * @param int|null $rateLimitPerMinute Maximum webhook requests per minute
     *                                     0 or null = unlimited
     *                                     Prevents overwhelming destination endpoints
     * 
     * @param int|null $rateLimitPerHour Maximum webhook requests per hour
     *                                   0 or null = unlimited
     *                                   Useful for API quota management
     * 
     * @param int|null $rateLimitPerDay Maximum webhook requests per day
     *                                  null = unlimited
     *                                  Helps manage daily API limits
     * 
     * @param bool $isActive Whether destination accepts new events
     *                       false = soft disabled (no new deliveries)
     *                       true = active and accepting events
     * 
     * @param Carbon $createdAt Timestamp when destination was created
     *                          Automatically set by API on creation
     * 
     * @param Carbon $updatedAt Timestamp of last modification
     *                          Updated automatically on any change
     * 
     * @param Carbon|null $deletedAt Timestamp when soft-deleted
     *                               null = active, not deleted
     *                               Soft delete preserves history
     */
    public function __construct(
        public string $uuid,
        public string $name,
        public string $slug,
        public string $identifier,
        public ?string $description,
        public array $metadata,
        public WebhookMode $webhookMode,
        public string $webhookUrl,
        public string $webhookSecret,
        public ?string $inboundToken,
        public ?WebhookConfig $webhookConfig,
        public array $headers,
        public ?int $timeout,
        public ?int $retryLimit,
        public ?int $rateLimitPerMinute,
        public ?int $rateLimitPerHour,
        public ?int $rateLimitPerDay,
        public bool $isActive,
        public Carbon $createdAt,
        public Carbon $updatedAt,
        public ?Carbon $deletedAt,
    ) {
        //
    }

    /**
     * Check if the destination is soft-deleted
     * 
     * Soft-deleted destinations are marked for deletion but remain in the system
     * for historical reference and audit trails. Events cannot be sent to deleted
     * destinations, but past delivery history is preserved.
     * 
     * Soft deletion allows:
     * - Preserving delivery history and analytics
     * - Preventing accidental data loss
     * - Maintaining referential integrity
     * - Potential restoration if needed
     * 
     * @return bool True if destination has been soft-deleted, false if active
     * 
     * @example
     * if ($destination->isDeleted()) {
     *     echo "This destination was deleted on {$destination->deletedAt->format('Y-m-d H:i:s')}";
     *     echo "Age at deletion: {$destination->createdAt->diffInDays($destination->deletedAt)} days";
     * } else {
     *     echo "Destination is active";
     * }
     */
    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    /**
     * Check if the destination is enabled and accepting events
     * 
     * A destination is considered enabled when:
     * - The isActive flag is true (not soft-disabled)
     * - It has not been soft-deleted (deletedAt is null)
     * 
     * Only enabled destinations can receive webhook deliveries. This check
     * should be performed before attempting to send events.
     * 
     * @return bool True if destination is active and not deleted
     * 
     * @example
     * if ($destination->isEnabled()) {
     *     // Safe to send events to this destination
     *     $client->events->create(
     *         eventType: 'user.created',
     *         payload: ['user_id' => 123],
     *         destination: $destination->identifier
     *     );
     * } else {
     *     logger()->warning('Attempted to use disabled destination', [
     *         'destination' => $destination->identifier,
     *         'is_active' => $destination->isActive,
     *         'is_deleted' => $destination->isDeleted()
     *     ]);
     * }
     * 
     * @example
     * // Filter destinations to only enabled ones
     * $enabled = array_filter($destinations, fn($d) => $d->isEnabled());
     */
    public function isEnabled(): bool
    {
        return $this->isActive && !$this->isDeleted();
    }

    /**
     * Get a metadata value by key with optional default
     * 
     * Safely retrieves values from the metadata array without triggering
     * undefined key warnings. Metadata is a flexible storage for custom
     * attributes that don't fit into the standard destination schema.
     * 
     * Common metadata keys:
     * - environment: 'development', 'staging', 'production'
     * - tags: Array of categorization tags
     * - version: Application version string
     * - owner_email: Contact email for the destination
     * - created_by: Name of the creator
     * - business_critical: Boolean flag
     * - compliance_required: Boolean flag
     * - region: Geographic region or data center
     * - cost_center: Budget tracking
     * 
     * @param string $key The metadata key to retrieve
     * @param mixed $default Default value if key doesn't exist (default: null)
     * @return mixed The metadata value or default
     * 
     * @example
     * // Get specific metadata with defaults
     * $env = $destination->getMetadata('environment', 'production');
     * $tags = $destination->getMetadata('tags', []);
     * $version = $destination->getMetadata('version');
     * 
     * // Check business criticality
     * if ($destination->getMetadata('business_critical', false)) {
     *     echo "⚠️ Critical destination - handle with care!";
     *     $this->notifyOncall($destination);
     * }
     * 
     * // Get owner contact for incidents
     * $owner = $destination->getMetadata('owner_email', 'support@example.com');
     * $this->sendAlert($owner, "Destination {$destination->name} is failing");
     * 
     * // Environment-specific configuration
     * $timeout = match($destination->getMetadata('environment')) {
     *     'production' => 30,
     *     'staging' => 60,
     *     'development' => 120,
     *     default => 30
     * };
     */
    public function getMetadata(string $key, $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Check if rate limiting is configured for this destination
     * 
     * Returns true if any rate limit threshold is set (per minute, hour, or day).
     * Rate limiting helps prevent overwhelming destination endpoints and ensures
     * fair resource usage across multiple event sources.
     * 
     * Benefits of rate limiting:
     * - Prevents overwhelming destination endpoints
     * - Manages API quotas and costs
     * - Ensures fair resource distribution
     * - Protects against traffic spikes
     * 
     * @return bool True if any rate limit is configured, false if unlimited
     * 
     * @example
     * if ($destination->hasRateLimiting()) {
     *     echo "Rate limits configured:\n";
     *     
     *     if ($destination->rateLimitPerMinute) {
     *         echo "- Per minute: {$destination->rateLimitPerMinute}\n";
     *     }
     *     
     *     if ($destination->rateLimitPerHour) {
     *         echo "- Per hour: {$destination->rateLimitPerHour}\n";
     *     }
     *     
     *     if ($destination->rateLimitPerDay) {
     *         echo "- Per day: {$destination->rateLimitPerDay}\n";
     *     }
     * } else {
     *     echo "No rate limiting - unlimited webhooks";
     * }
     * 
     * @example
     * // Decide on delivery strategy based on rate limits
     * if ($destination->hasRateLimiting()) {
     *     // Use batching to respect limits
     *     $batchSize = min(
     *         $destination->rateLimitPerMinute ?? 100,
     *         $destination->webhookConfig?->batchSize ?? 10
     *     );
     *     $this->queueWithBatching($events, $batchSize);
     * } else {
     *     // Send immediately without batching
     *     $this->sendImmediately($events);
     * }
     */
    public function hasRateLimiting(): bool
    {
        return $this->rateLimitPerMinute > 0
            || $this->rateLimitPerHour > 0
            || $this->rateLimitPerDay !== null;
    }

    /**
     * Check if destination is bidirectional (can send and receive)
     * 
     * @return bool True if webhook mode is bidirectional
     * 
     * @example
     * if ($destination->isBidirectional()) {
     *     echo "Can send events and receive webhooks";
     *     $this->setupInboundEndpoint($destination);
     * }
     */
    public function isBidirectional(): bool
    {
        return $this->webhookMode === WebhookMode::BIDIRECTIONAL;
    }

    /**
     * Check if destination only sends outbound events
     * 
     * @return bool True if webhook mode is outbound only
     * 
     * @example
     * if ($destination->isOutboundOnly()) {
     *     echo "This destination only sends events, doesn't receive";
     * }
     */
    public function isOutboundOnly(): bool
    {
        return $this->webhookMode === WebhookMode::OUTBOUND;
    }

    /**
     * Check if destination only receives inbound webhooks
     * 
     * @return bool True if webhook mode is inbound only
     * 
     * @example
     * if ($destination->isInboundOnly()) {
     *     echo "This destination only receives webhooks";
     *     echo "Inbound token: {$destination->inboundToken}";
     * }
     */
    public function isInboundOnly(): bool
    {
        return $this->webhookMode === WebhookMode::INBOUND;
    }

    /**
     * Check if destination can send outbound events
     * 
     * @return bool True if bidirectional or outbound mode
     * 
     * @example
     * if ($destination->canSendEvents()) {
     *     $this->queueEvent($event, $destination);
     * }
     */
    public function canSendEvents(): bool
    {
        return $this->webhookMode === WebhookMode::BIDIRECTIONAL
            || $this->webhookMode === WebhookMode::OUTBOUND;
    }

    /**
     * Check if destination can receive inbound webhooks
     * 
     * @return bool True if bidirectional or inbound mode
     * 
     * @example
     * if ($destination->canReceiveWebhooks()) {
     *     $this->registerInboundEndpoint($destination);
     * }
     */
    public function canReceiveWebhooks(): bool
    {
        return $this->webhookMode === WebhookMode::BIDIRECTIONAL
            || $this->webhookMode === WebhookMode::INBOUND;
    }

    /**
     * Get the age of the destination in days
     * 
     * @return int Number of days since creation
     * 
     * @example
     * $age = $destination->getAge();
     * echo "Destination created {$age} days ago";
     * 
     * if ($age > 365) {
     *     echo "This is a legacy destination - consider reviewing";
     * }
     */
    public function getAge(): int
    {
        return (int) $this->createdAt->diffInDays(Carbon::now());
    }

    /**
     * Check if destination was recently updated (within last 24 hours)
     * 
     * @return bool True if updated in last 24 hours
     * 
     * @example
     * if ($destination->isRecentlyUpdated()) {
     *     echo "Recently modified: {$destination->updatedAt->diffForHumans()}";
     * }
     */
    public function isRecentlyUpdated(): bool
    {
        return $this->updatedAt->diffInHours(Carbon::now()) < 24;
    }

    /**
     * Get a summary of the destination's current state
     * 
     * @return array<string, mixed> Summary information
     * 
     * @example
     * $summary = $destination->getSummary();
     * // [
     * //     'identifier' => 'app_xyz123',
     * //     'name' => 'Analytics Dashboard',
     * //     'enabled' => true,
     * //     'webhook_mode' => 'bidirectional',
     * //     'has_rate_limiting' => true,
     * //     'age_days' => 45,
     * //     'environment' => 'production'
     * // ]
     */
    public function getSummary(): array
    {
        return [
            'uuid' => $this->uuid,
            'identifier' => $this->identifier,
            'name' => $this->name,
            'enabled' => $this->isEnabled(),
            'webhook_mode' => $this->webhookMode->value,
            'has_rate_limiting' => $this->hasRateLimiting(),
            'age_days' => $this->getAge(),
            'environment' => $this->getMetadata('environment', 'unknown'),
        ];
    }
}
