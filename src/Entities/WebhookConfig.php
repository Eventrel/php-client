<?php

namespace Eventrel\Entities;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Webhook configuration
 * 
 * Defines detailed delivery behavior, security settings, and processing rules
 * for webhook events sent to a destination. This configuration controls how
 * events are batched, signed, filtered, and delivered.
 * 
 * This immutable configuration object is nested within the Destination entity
 * and determines the operational characteristics of webhook delivery.
 * 
 * Key Configuration Areas:
 * - **Batching**: Group multiple events into single webhook requests
 * - **Security**: SSL verification and webhook signature algorithms
 * - **Filtering**: Control which event types are delivered
 * - **Delivery**: Strategy (immediate, batched, scheduled) and retry handling
 * - **Reliability**: Dead letter queues and redirect following
 * - **Timing**: Timestamp tolerance for replay protection
 * 
 * Delivery Strategies:
 * - **immediate**: Send events as soon as they're created (low latency)
 * - **batched**: Group events together for efficiency (reduced requests)
 * - **scheduled**: Deliver at specific times (for rate-limited APIs)
 * 
 * Security Features:
 * - HMAC signature generation (SHA-256, SHA-512, etc.)
 * - Timestamp-based replay attack prevention
 * - SSL/TLS certificate verification
 * - Custom signature header support
 * 
 * @package Eventrel\Entities
 * 
 * @property int|null $batchSize Number of events per batch (null = no batching)
 * @property bool $verifySsl Whether to verify SSL/TLS certificates
 * @property EventFiltering $eventFiltering Event type filtering rules
 * @property bool $includeHeaders Include custom headers in webhook body
 * @property bool $followRedirects Follow HTTP 3xx redirects
 * @property string|null $signatureHeader Custom header name for signature
 * @property bool $deadLetterQueue Send failed events to DLQ
 * @property string $deliveryStrategy Delivery mode (batched/immediate/scheduled)
 * @property string $signatureAlgorithm HMAC algorithm (sha256/sha512)
 * @property int $timestampTolerance Max acceptable timestamp drift in seconds
 * 
 * @example
 * // Access via destination
 * $config = $destination->webhookConfig;
 * 
 * // Check batching
 * if ($config->isBatchingEnabled()) {
 *     echo "Batching {$config->batchSize} events per request";
 * }
 * 
 * // Check security settings
 * echo "Signature: {$config->signatureAlgorithm}";
 * echo "SSL Verification: " . ($config->verifySsl ? 'Enabled' : 'Disabled');
 * 
 * // Get delivery info
 * echo "Strategy: {$config->getDeliveryStrategyLabel()}";
 */
#[MapName(SnakeCaseMapper::class)]
class WebhookConfig extends Data
{
    /**
     * Create a new WebhookConfig entity
     * 
     * This constructor is used internally when hydrating from API responses.
     * Configuration objects are created automatically as part of Destination entities.
     * 
     * @param int|null $batchSize Number of events to combine into a single webhook request.
     *                            If null or 1, events are sent individually.
     *                            Batching reduces HTTP overhead but increases latency.
     *                            Typical values: 10, 50, 100 depending on event volume.
     *                            Example: 50 means up to 50 events sent in one POST.
     * 
     * @param bool $verifySsl Whether to verify SSL/TLS certificates when connecting
     *                        to webhook endpoints. Should be true for production to
     *                        prevent man-in-the-middle attacks. May be false for
     *                        development with self-signed certificates.
     * 
     * @param EventFiltering $eventFiltering Configuration object that controls which
     *                                       event types are allowed to be sent to this
     *                                       destination. Contains enable/disable flag
     *                                       and optional allowlist of event types.
     * 
     * @param bool $includeHeaders Whether to include the destination's custom headers
     *                             directly in the webhook payload body (in addition to
     *                             sending them as HTTP headers). Useful for debugging
     *                             or when the receiving endpoint needs to log headers.
     * 
     * @param bool $followRedirects Whether to automatically follow HTTP 3xx redirects
     *                              (301, 302, 307, 308) when delivering webhooks.
     *                              If false, redirects are treated as delivery failures.
     *                              If true, follows up to 5 redirects by default.
     * 
     * @param string|null $signatureHeader Custom HTTP header name for the webhook signature.
     *                                     If null, uses the default 'X-Webhook-Signature'.
     *                                     Some APIs require specific header names like
     *                                     'X-Hub-Signature' or 'Stripe-Signature'.
     * 
     * @param bool $deadLetterQueue Whether to send permanently failed events (after all
     *                              retries exhausted) to a dead letter queue for later
     *                              inspection and manual reprocessing. Recommended for
     *                              critical events that must not be lost.
     * 
     * @param string $deliveryStrategy Determines when and how events are delivered:
     *                                 - 'immediate': Send as soon as event is created
     *                                 - 'batched': Accumulate events and send in groups
     *                                 - 'scheduled': Deliver at specific times/intervals
     *                                 Choice affects latency vs. efficiency trade-off.
     * 
     * @param string $signatureAlgorithm HMAC hashing algorithm for webhook signatures.
     *                                   Common values: 'sha256' (recommended), 'sha512'
     *                                   (more secure but slower), 'sha1' (legacy).
     *                                   The signature allows receivers to verify webhook
     *                                   authenticity and integrity.
     * 
     * @param int $timestampTolerance Maximum acceptable difference in seconds between
     *                                the webhook's timestamp and the receiver's current
     *                                time. Prevents replay attacks. Typical value: 300
     *                                (5 minutes) to account for clock drift. Lower values
     *                                are more secure but may cause legitimate webhooks
     *                                to be rejected if clocks are out of sync.
     */
    public function __construct(
        public ?int $batchSize,
        public ?bool $verifySsl,
        public ?EventFiltering $eventFiltering,
        public ?bool $includeHeaders,
        public ?bool $followRedirects,
        public ?string $signatureHeader,
        public ?bool $deadLetterQueue,
        public ?string $deliveryStrategy,
        public ?string $signatureAlgorithm,
        public ?int $timestampTolerance,
    ) {
        //
    }

    /**
     * Check if event batching is enabled
     * 
     * Batching is considered enabled when batchSize is set to a value
     * greater than 1. This combines multiple events into a single HTTP
     * request to reduce network overhead and improve throughput.
     * 
     * Benefits of batching:
     * - Reduced HTTP request overhead (fewer connections)
     * - Lower network costs
     * - Better throughput for high-volume events
     * 
     * Trade-offs:
     * - Increased latency (wait for batch to fill)
     * - More complex error handling (partial failures)
     * - Larger payloads (may hit size limits)
     * 
     * @return bool True if batch size is configured and greater than 1
     * 
     * @example
     * if ($config->isBatchingEnabled()) {
     *     echo "Batching enabled: {$config->batchSize} events per request\n";
     *     echo "Strategy: {$config->deliveryStrategy}\n";
     *     
     *     // Estimate requests per hour
     *     $eventsPerHour = 1000;
     *     $requestsPerHour = ceil($eventsPerHour / $config->batchSize);
     *     echo "Estimated requests/hour: {$requestsPerHour}";
     * } else {
     *     echo "No batching - events sent individually";
     * }
     * 
     * @example
     * // Configure based on batching status
     * if ($config->isBatchingEnabled()) {
     *     $this->setupBatchProcessor($config->batchSize);
     * } else {
     *     $this->setupImmediateProcessor();
     * }
     */
    public function isBatchingEnabled(): bool
    {
        return $this->batchSize !== null && $this->batchSize > 1;
    }

    /**
     * Check if event type filtering is active
     * 
     * When enabled, only events matching the configured event types
     * (in eventFiltering->allowedEvents) will be sent to this destination.
     * All other events are silently dropped.
     * 
     * Use cases for filtering:
     * - Send only payment events to payment webhooks
     * - Route user events to analytics endpoints
     * - Separate critical vs. non-critical events
     * - Reduce noise by filtering test events
     * 
     * @return bool True if event filtering is enabled
     * 
     * @example
     * if ($config->hasEventFiltering()) {
     *     $allowed = $config->eventFiltering->allowedEvents ?? [];
     *     echo "Filtering enabled - allowed types:\n";
     *     foreach ($allowed as $type) {
     *         echo "  - {$type}\n";
     *     }
     *     
     *     // Check if specific event is allowed
     *     if ($config->eventFiltering->isEventAllowed('user.created')) {
     *         echo "user.created events will be delivered";
     *     }
     * } else {
     *     echo "No filtering - all event types accepted";
     * }
     * 
     * @example
     * // Decide whether to send event
     * $eventType = 'payment.completed';
     * if (!$config->hasEventFiltering() || 
     *     $config->eventFiltering->isEventAllowed($eventType)) {
     *     // Send the event
     * } else {
     *     // Skip - not in allowlist
     * }
     */
    public function hasEventFiltering(): bool
    {
        return $this->eventFiltering->enabled;
    }

    /**
     * Get the delivery strategy as a user-friendly display label
     * 
     * Converts the technical delivery strategy value into human-readable
     * format suitable for UI display, reports, or logs.
     * 
     * Strategy meanings:
     * - **Batched Delivery**: Events grouped together for efficiency
     * - **Immediate Delivery**: Events sent as soon as created (real-time)
     * - **Scheduled Delivery**: Events sent at specific times/intervals
     * 
     * @return string User-friendly label for the delivery strategy
     * 
     * @example
     * // Display in UI
     * echo "Delivery Mode: {$config->getDeliveryStrategyLabel()}";
     * // Output: "Delivery Mode: Batched Delivery"
     * 
     * @example
     * // Use in status reports
     * $report = sprintf(
     *     "Destination '%s' uses %s with %s",
     *     $destination->name,
     *     $config->getDeliveryStrategyLabel(),
     *     $config->isBatchingEnabled() 
     *         ? "batches of {$config->batchSize}" 
     *         : "individual requests"
     * );
     * 
     * @example
     * // Switch on strategy for metrics
     * match($config->getDeliveryStrategyLabel()) {
     *     'Immediate Delivery' => $metrics->trackLatency(),
     *     'Batched Delivery' => $metrics->trackThroughput(),
     *     'Scheduled Delivery' => $metrics->trackScheduleAdherence(),
     * };
     */
    public function getDeliveryStrategyLabel(): string
    {
        return match ($this->deliveryStrategy) {
            'batched' => 'Batched Delivery',
            'immediate' => 'Immediate Delivery',
            'scheduled' => 'Scheduled Delivery',
            default => ucfirst($this->deliveryStrategy),
        };
    }

    /**
     * Check if SSL verification is enabled (recommended for production)
     * 
     * @return bool True if SSL certificates will be verified
     * 
     * @example
     * if (!$config->isSecure()) {
     *     logger()->warning('SSL verification disabled for destination', [
     *         'destination' => $destination->identifier,
     *         'environment' => $destination->getMetadata('environment')
     *     ]);
     * }
     */
    public function isSecure(): bool
    {
        return $this->verifySsl;
    }

    /**
     * Check if dead letter queue is enabled for failed events
     * 
     * @return bool True if DLQ is enabled
     * 
     * @example
     * if ($config->hasDeadLetterQueue()) {
     *     echo "Failed events will be preserved in DLQ for manual reprocessing";
     * }
     */
    public function hasDeadLetterQueue(): bool
    {
        return $this->deadLetterQueue;
    }

    /**
     * Get security configuration summary
     * 
     * @return array<string, mixed> Security settings
     * 
     * @example
     * $security = $config->getSecuritySummary();
     * // [
     * //     'ssl_verification' => true,
     * //     'signature_algorithm' => 'sha256',
     * //     'timestamp_tolerance' => 300,
     * //     'signature_header' => 'X-Webhook-Signature'
     * // ]
     */
    public function getSecuritySummary(): array
    {
        return [
            'ssl_verification' => $this->verifySsl,
            'signature_algorithm' => $this->signatureAlgorithm,
            'timestamp_tolerance' => $this->timestampTolerance,
            'signature_header' => $this->signatureHeader ?? 'X-Webhook-Signature',
        ];
    }

    /**
     * Get delivery configuration summary
     * 
     * @return array<string, mixed> Delivery settings
     * 
     * @example
     * $delivery = $config->getDeliverySummary();
     * // [
     * //     'strategy' => 'batched',
     * //     'batching_enabled' => true,
     * //     'batch_size' => 50,
     * //     'follow_redirects' => true,
     * //     'dead_letter_queue' => true
     * // ]
     */
    public function getDeliverySummary(): array
    {
        return [
            'strategy' => $this->deliveryStrategy,
            'batching_enabled' => $this->isBatchingEnabled(),
            'batch_size' => $this->batchSize,
            'follow_redirects' => $this->followRedirects,
            'dead_letter_queue' => $this->deadLetterQueue,
        ];
    }
}
