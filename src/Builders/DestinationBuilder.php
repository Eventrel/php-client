<?php

namespace Eventrel\Client\Builders;

use Eventrel\Client\EventrelClient;
use Eventrel\Client\Responses\DestinationResponse;
use Eventrel\Client\Services\DestinationService;
use InvalidArgumentException;

/**
 * Destination Builder
 * 
 * Provides a fluent interface for creating destinations with complex configurations.
 * Makes it easy to build destinations step-by-step without dealing with large
 * parameter lists or deeply nested arrays.
 * 
 * The builder pattern allows for:
 * - Clear, readable destination configuration
 * - Method chaining for concise setup
 * - Validation before sending to API
 * - Preset configurations for common patterns
 * - IDE autocomplete for all options
 * 
 * @package Eventrel\Client\Builders
 * 
 * @example
 * // Simple outbound webhook
 * $destination = $client->destinations->builder('My API', 'https://api.example.com/webhook')
 *     ->outbound()
 *     ->create();
 * 
 * @example
 * // Complex bidirectional with batching and filtering
 * $destination = $client->destinations->builder('Analytics', 'https://analytics.example.com/webhook')
 *     ->bidirectional()
 *     ->withDescription('Main analytics endpoint')
 *     ->withMetadata(['environment' => 'production', 'team' => 'analytics'])
 *     ->withTimeout(45)
 *     ->withRetryLimit(5)
 *     ->withRateLimit(perMinute: 1000, perHour: 50000)
 *     ->withBatching(size: 50, strategy: 'batched')
 *     ->withEventFiltering(['user.created', 'user.updated', 'order.completed'])
 *     ->withDeadLetterQueue()
 *     ->verifySsl()
 *     ->create();
 */
class DestinationBuilder
{
    /**
     * The destination service for API communication.
     */
    private DestinationService $service;

    /**
     * Destination name
     */
    private string $name;

    /**
     * Webhook URL
     */
    private string $webhookUrl;

    /**
     * Webhook mode (bidirectional, outbound, inbound)
     */
    private string $webhookMode = 'outbound';

    /**
     * Optional description
     */
    private ?string $description = null;

    /**
     * Custom HTTP headers
     */
    private array $headers = [];

    /**
     * Additional metadata
     */
    private array $metadata = [];

    /**
     * Webhook configuration
     */
    private array $webhookConfig = [];

    /**
     * Request timeout in seconds
     */
    private ?int $timeout = null;

    /**
     * Maximum retry attempts
     */
    private ?int $retryLimit = null;

    /**
     * Rate limit per minute
     */
    private ?int $rateLimitPerMinute = null;

    /**
     * Rate limit per hour
     */
    private ?int $rateLimitPerHour = null;

    /**
     * Rate limit per day
     */
    private ?int $rateLimitPerDay = null;

    /**
     * Whether destination is active
     */
    private bool $isActive = true;

    /**
     * Create a new DestinationBuilder instance
     * 
     * @param EventrelClient $client The EventrelClient instance
     * 
     */
    public function __construct(
        private EventrelClient $client,
    ) {
        $this->service = new DestinationService($client);
    }

    /**
     * Set the destination name
     * 
     * @param string $name Human-readable name for the destination
     * @return self
     * 
     * @example
     * $builder->name('My Webhook');
     */
    public function name(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Set the webhook URL
     * 
     * @param string $url The webhook endpoint URL
     * @return self
     * 
     * @example
     * $builder->webhookUrl('https://api.example.com/webhook');
     */
    public function webhookUrl(string $url): self
    {
        $this->webhookUrl = $url;

        return $this;
    }

    /**
     * Set webhook mode to bidirectional
     * 
     * Allows both sending events and receiving webhooks.
     * 
     * @return self
     * 
     * @example
     * $builder->bidirectional();
     */
    public function bidirectional(): self
    {
        $this->webhookMode = 'bidirectional';

        return $this;
    }

    /**
     * Set webhook mode to outbound only
     * 
     * Only sends events to the webhook URL.
     * 
     * @return self
     * 
     * @example
     * $builder->outbound();
     */
    public function outbound(): self
    {
        $this->webhookMode = 'outbound';

        return $this;
    }

    /**
     * Set webhook mode to inbound only
     * 
     * Only receives webhooks from external sources.
     * 
     * @return self
     * 
     * @example
     * $builder->inbound();
     */
    public function inbound(): self
    {
        $this->webhookMode = 'inbound';

        return $this;
    }

    /**
     * Set the description
     * 
     * @param string $description Optional description of the destination
     * @return self
     * 
     * @example
     * $builder->withDescription('Production payment webhook endpoint');
     */
    public function withDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Add a custom HTTP header
     * 
     * @param string $name Header name
     * @param string $value Header value
     * @return self
     * 
     * @example
     * $builder->withHeader('Authorization', 'Bearer secret_token');
     */
    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    /**
     * Set multiple custom HTTP headers
     * 
     * @param array<string, string> $headers Associative array of headers
     * @return self
     * 
     * @example
     * $builder->withHeaders([
     *     'Authorization' => 'Bearer token',
     *     'X-API-Version' => 'v1',
     *     'User-Agent' => 'MyApp/1.0'
     * ]);
     */
    public function withHeaders(array $headers): self
    {
        $this->headers = array_merge($this->headers, $headers);

        return $this;
    }

    /**
     * Add Bearer token authorization header
     * 
     * @param string $token Bearer token
     * @return self
     * 
     * @example
     * $builder->withBearerToken('secret_token_here');
     */
    public function withBearerToken(string $token): self
    {
        return $this->withHeader('Authorization', "Bearer {$token}");
    }

    /**
     * Add API key header
     * 
     * @param string $apiKey API key value
     * @param string $headerName Header name (default: 'X-API-Key')
     * @return self
     * 
     * @example
     * $builder->withApiKey('my-api-key');
     * $builder->withApiKey('my-api-key', 'X-Custom-API-Key');
     */
    public function withApiKey(string $apiKey, string $headerName = 'X-API-Key'): self
    {
        return $this->withHeader($headerName, $apiKey);
    }

    /**
     * Add a metadata key-value pair
     * 
     * @param string $key Metadata key
     * @param mixed $value Metadata value
     * @return self
     * 
     * @example
     * $builder->withMetadata('environment', 'production');
     */
    public function withMetadata(string $key, mixed $value): self
    {
        $this->metadata[$key] = $value;

        return $this;
    }

    /**
     * Set multiple metadata values
     * 
     * @param array<string, mixed> $metadata Associative array of metadata
     * @return self
     * 
     * @example
     * $builder->withMetadataArray([
     *     'environment' => 'production',
     *     'team' => 'platform',
     *     'business_critical' => true,
     *     'owner_email' => 'team@example.com'
     * ]);
     */
    public function withMetadataArray(array $metadata): self
    {
        $this->metadata = array_merge($this->metadata, $metadata);

        return $this;
    }

    /**
     * Set request timeout in seconds
     * 
     * @param int $seconds Timeout in seconds (5-120 recommended)
     * @return self
     * 
     * @example
     * $builder->withTimeout(45);
     */
    public function withTimeout(int $seconds): self
    {
        if ($seconds < 1) {
            throw new InvalidArgumentException('Timeout must be at least 1 second');
        }

        $this->timeout = $seconds;

        return $this;
    }

    /**
     * Set maximum retry attempts
     * 
     * @param int $limit Maximum retry attempts (0-10 recommended)
     * @return self
     * 
     * @example
     * $builder->withRetryLimit(5);
     */
    public function withRetryLimit(int $limit): self
    {
        if ($limit < 0) {
            throw new InvalidArgumentException('Retry limit cannot be negative');
        }

        $this->retryLimit = $limit;

        return $this;
    }

    /**
     * Configure rate limiting
     * 
     * @param int|null $perMinute Max requests per minute (null = unlimited)
     * @param int|null $perHour Max requests per hour (null = unlimited)
     * @param int|null $perDay Max requests per day (null = unlimited)
     * @return self
     * 
     * @example
     * $builder->withRateLimit(perMinute: 1000, perHour: 50000);
     * $builder->withRateLimit(perDay: 1000000);
     */
    public function withRateLimit(
        ?int $perMinute = null,
        ?int $perHour = null,
        ?int $perDay = null
    ): self {
        $this->rateLimitPerMinute = $perMinute;
        $this->rateLimitPerHour = $perHour;
        $this->rateLimitPerDay = $perDay;

        return $this;
    }

    /**
     * Set destination as inactive
     * 
     * @return self
     * 
     * @example
     * $builder->inactive();
     */
    public function inactive(): self
    {
        $this->isActive = false;

        return $this;
    }

    /**
     * Set destination as active (default)
     * 
     * @return self
     * 
     * @example
     * $builder->active();
     */
    public function active(): self
    {
        $this->isActive = true;

        return $this;
    }

    /**
     * Configure event batching
     * 
     * @param int $size Number of events per batch (2-1000)
     * @param string $strategy Delivery strategy: 'batched', 'immediate', 'scheduled'
     * @return self
     * 
     * @example
     * $builder->withBatching(size: 50, strategy: 'batched');
     */
    public function withBatching(int $size, string $strategy = 'batched'): self
    {
        if ($size < 1) {
            throw new InvalidArgumentException('Batch size must be at least 1');
        }

        $this->webhookConfig['batch_size'] = $size;
        $this->webhookConfig['delivery_strategy'] = $strategy;

        return $this;
    }

    /**
     * Configure event filtering by allowed event types
     * 
     * @param array<int, string> $allowedEvents List of allowed event types
     * @return self
     * 
     * @example
     * $builder->withEventFiltering([
     *     'user.created',
     *     'user.updated',
     *     'payment.completed'
     * ]);
     */
    public function withEventFiltering(array $allowedEvents): self
    {
        $this->webhookConfig['event_filtering'] = [
            'enabled' => true,
            'allowed_events' => $allowedEvents,
        ];

        return $this;
    }

    /**
     * Disable event filtering (allow all event types)
     * 
     * @return self
     * 
     * @example
     * $builder->withoutEventFiltering();
     */
    public function withoutEventFiltering(): self
    {
        $this->webhookConfig['event_filtering'] = [
            'enabled' => false,
            'allowed_events' => null,
        ];

        return $this;
    }

    /**
     * Enable SSL certificate verification (recommended for production)
     * 
     * @return self
     * 
     * @example
     * $builder->verifySsl();
     */
    public function verifySsl(): self
    {
        $this->webhookConfig['verify_ssl'] = true;

        return $this;
    }

    /**
     * Disable SSL certificate verification (for development/testing only)
     * 
     * @return self
     * 
     * @example
     * $builder->skipSslVerification();
     */
    public function skipSslVerification(): self
    {
        $this->webhookConfig['verify_ssl'] = false;

        return $this;
    }

    /**
     * Enable dead letter queue for failed events
     * 
     * @return self
     * 
     * @example
     * $builder->withDeadLetterQueue();
     */
    public function withDeadLetterQueue(): self
    {
        $this->webhookConfig['dead_letter_queue'] = true;

        return $this;
    }

    /**
     * Disable dead letter queue
     * 
     * @return self
     * 
     * @example
     * $builder->withoutDeadLetterQueue();
     */
    public function withoutDeadLetterQueue(): self
    {
        $this->webhookConfig['dead_letter_queue'] = false;

        return $this;
    }

    /**
     * Enable following HTTP redirects
     * 
     * @return self
     * 
     * @example
     * $builder->followRedirects();
     */
    public function followRedirects(): self
    {
        $this->webhookConfig['follow_redirects'] = true;

        return $this;
    }

    /**
     * Disable following HTTP redirects
     * 
     * @return self
     * 
     * @example
     * $builder->dontFollowRedirects();
     */
    public function dontFollowRedirects(): self
    {
        $this->webhookConfig['follow_redirects'] = false;

        return $this;
    }

    /**
     * Configure webhook signature
     * 
     * @param string $algorithm Signature algorithm: 'sha256', 'sha512', etc.
     * @param string|null $headerName Custom header name for signature (null = default)
     * @return self
     * 
     * @example
     * $builder->withSignature('sha256');
     * $builder->withSignature('sha512', 'X-Hub-Signature');
     */
    public function withSignature(string $algorithm = 'sha256', ?string $headerName = null): self
    {
        $this->webhookConfig['signature_algorithm'] = $algorithm;

        if ($headerName !== null) {
            $this->webhookConfig['signature_header'] = $headerName;
        }

        return $this;
    }

    /**
     * Set timestamp tolerance for replay protection
     * 
     * @param int $seconds Acceptable timestamp difference in seconds
     * @return self
     * 
     * @example
     * $builder->withTimestampTolerance(300); // 5 minutes
     */
    public function withTimestampTolerance(int $seconds): self
    {
        if ($seconds < 0) {
            throw new InvalidArgumentException('Timestamp tolerance cannot be negative');
        }

        $this->webhookConfig['timestamp_tolerance'] = $seconds;

        return $this;
    }

    /**
     * Include custom headers in webhook payload body
     * 
     * @return self
     * 
     * @example
     * $builder->includeHeadersInPayload();
     */
    public function includeHeadersInPayload(): self
    {
        $this->webhookConfig['include_headers'] = true;

        return $this;
    }

    /**
     * Set custom webhook configuration
     * 
     * For advanced configuration not covered by builder methods.
     * 
     * @param array<string, mixed> $config Custom configuration
     * @return self
     * 
     * @example
     * $builder->withWebhookConfig([
     *     'custom_setting' => 'value',
     *     'advanced_option' => true
     * ]);
     */
    public function withWebhookConfig(array $config): self
    {
        $this->webhookConfig = array_merge($this->webhookConfig, $config);

        return $this;
    }

    /**
     * Apply a production-ready preset configuration
     * 
     * Sets recommended settings for production use:
     * - SSL verification enabled
     * - Dead letter queue enabled
     * - Batching with size 50
     * - 5 retry attempts
     * - 45 second timeout
     * - SHA-256 signatures
     * 
     * @return self
     * 
     * @example
     * $destination = $client->destinations->builder('Prod API', 'https://api.example.com/webhook')
     *     ->productionPreset()
     *     ->withBearerToken('secret')
     *     ->create();
     */
    public function productionPreset(): self
    {
        return $this
            ->verifySsl()
            ->withDeadLetterQueue()
            ->withBatching(size: 50, strategy: 'batched')
            ->withRetryLimit(5)
            ->withTimeout(45)
            ->withSignature('sha256')
            ->withTimestampTolerance(300)
            ->followRedirects();
    }

    /**
     * Apply a development-friendly preset configuration
     * 
     * Sets relaxed settings for development:
     * - SSL verification disabled
     * - No batching (immediate delivery)
     * - 3 retry attempts
     * - 120 second timeout
     * 
     * @return self
     * 
     * @example
     * $destination = $client->destinations->builder('Dev API', 'http://localhost:3000/webhook')
     *     ->developmentPreset()
     *     ->create();
     */
    public function developmentPreset(): self
    {
        return $this
            ->skipSslVerification()
            ->withRetryLimit(3)
            ->withTimeout(120)
            ->withWebhookConfig(['delivery_strategy' => 'immediate'])
            ->withoutDeadLetterQueue();
    }

    /**
     * Build the destination data array without creating
     * 
     * Useful for inspection or manual API calls.
     * 
     * @return array<string, mixed> The destination configuration array
     * 
     * @example
     * $data = $builder->build();
     * print_r($data);
     */
    public function build(): array
    {
        return array_filter([
            'name' => $this->name,
            'webhook_url' => $this->webhookUrl,
            'webhook_mode' => $this->webhookMode,
            'description' => $this->description,
            'headers' => !empty($this->headers) ? $this->headers : null,
            'metadata' => !empty($this->metadata) ? $this->metadata : null,
            'webhook_config' => !empty($this->webhookConfig) ? $this->webhookConfig : null,
            'timeout' => $this->timeout,
            'retry_limit' => $this->retryLimit,
            'rate_limit_per_minute' => $this->rateLimitPerMinute,
            'rate_limit_per_hour' => $this->rateLimitPerHour,
            'rate_limit_per_day' => $this->rateLimitPerDay,
            'is_active' => $this->isActive,
        ], fn($value) => $value !== null);
    }

    /**
     * Validate the builder configuration
     * 
     * @throws InvalidArgumentException If configuration is invalid
     * @return void
     */
    private function validate(): void
    {
        if (empty($this->name)) {
            throw new InvalidArgumentException('Destination name is required');
        }

        if (empty($this->webhookUrl)) {
            throw new InvalidArgumentException('Webhook URL is required');
        }

        if (!filter_var($this->webhookUrl, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('Invalid webhook URL format');
        }

        $validModes = ['bidirectional', 'outbound', 'inbound'];
        if (!in_array($this->webhookMode, $validModes)) {
            throw new InvalidArgumentException(
                "Invalid webhook mode. Must be one of: " . implode(', ', $validModes)
            );
        }
    }

    /**
     * Create the destination via the API
     * 
     * Validates the configuration and sends the request to create the destination.
     * 
     * @return DestinationResponse
     * @throws InvalidArgumentException If configuration is invalid
     * 
     * @example
     * $destination = $client->destinations->builder('My API', 'https://api.example.com/webhook')
     *     ->outbound()
     *     ->withTimeout(45)
     *     ->create();
     * 
     * echo "Created: {$destination->getId()}";
     */
    public function create(): DestinationResponse
    {
        $this->validate();

        return $this->service->create(
            name: $this->name,
            webhookUrl: $this->webhookUrl,
            webhookMode: $this->webhookMode,
            description: $this->description,
            headers: $this->headers,
            metadata: $this->metadata,
            webhookConfig: $this->webhookConfig,
            timeout: $this->timeout,
            retryLimit: $this->retryLimit,
            rateLimitPerMinute: $this->rateLimitPerMinute,
            rateLimitPerHour: $this->rateLimitPerHour,
            rateLimitPerDay: $this->rateLimitPerDay,
            isActive: $this->isActive,
        );
    }
}
