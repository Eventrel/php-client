<?php

namespace Eventrel\Services;

use Eventrel\EventrelClient;

/**
 * Service for generating idempotency keys
 * 
 * Provides utilities for creating unique and deterministic idempotency keys
 * compatible with the Eventrel platform's idempotency handling.
 * 
 * Supports two key types:
 * - Contextual keys (ctx_): Content-based, deterministic
 * - Time-bound keys (tbx_): Content + time-based with configurable windows
 * 
 * @package Eventrel\Services
 */
class IdempotencyService
{
    /**
     * IdempotencyService constructor.
     * 
     * @param EventrelClient $client The Eventrel client instance
     */
    public function __construct(
        private EventrelClient $client
    ) {
        //
    }

    /**
     * Generate a random idempotency key
     * 
     * Creates a unique key using cryptographically secure random bytes.
     * Use this when you want each request to be treated as unique.
     * 
     * @return string Idempotency key in format 'evt_' followed by 32 hex characters
     * 
     * @example
     * $key = $client->idempotency->generate();
     * // Result: 'evt_a1b2c3d4e5f6789...'
     */
    public function generate(): string
    {
        return 'evt_' . bin2hex(random_bytes(16));
    }

    /**
     * Generate a contextual key for idempotency
     * 
     * Creates a deterministic key based on the context data.
     * The same context will always produce the same key, enabling
     * true idempotency across identical requests.
     * 
     * This is the client equivalent of your backend's generateContextualKey().
     * 
     * @param array<string, mixed>|object $context The context data to hash
     * @return string Idempotency key in format 'ctx_' followed by 32 hex characters
     * 
     * @example
     * // Payment context - prevent duplicate charges
     * $context = [
     *     'amount' => 10000,
     *     'currency' => 'USD',
     *     'customer_id' => 'cust_123',
     *     'order_id' => 'ord_456'
     * ];
     * $key = $client->idempotency->generateContextual($context);
     * 
     * @example
     * // User action context
     * $context = [
     *     'user_id' => 123,
     *     'action' => 'profile_update',
     *     'data' => ['name' => 'John Doe']
     * ];
     * $key = $client->idempotency->generateContextual($context);
     */
    public function generateContextual(array|object $context): string
    {
        $standardized = $this->standardizeContext($context);
        $contextString = json_encode($standardized);

        return 'evt_ctx_' . substr(hash('sha256', "{$contextString}{$this->client->getApiToken()}"), 0, 32);
    }

    /**
     * Generate a time-bound key for idempotency
     * 
     * Creates a deterministic key based on context, operation, and time window.
     * The key changes when the time window expires, allowing the same operation
     * to be repeated after the window.
     * 
     * This is the client equivalent of your backend's generateTimeBoundKey().
     * 
     * @param array<string, mixed>|object $context The context data
     * @param string $operation The operation identifier (e.g., 'daily_report', 'hourly_sync')
     * @param int|null $windowSeconds Time window in seconds (null = per-second granularity)
     * @return string Idempotency key in format 'tbx_' followed by 32 hex characters
     * 
     * @example
     * // Daily report - only send once per day
     * $context = ['user_id' => 123, 'report_type' => 'daily_summary'];
     * $key = $client->idempotency->generateTimeBound($context, 'daily_report', 86400);
     * // Same context + operation within 24 hours = same key
     * 
     * @example
     * // Hourly sync - deduplicate within 1-hour window
     * $context = ['tenant_id' => 'tenant_abc', 'sync_type' => 'incremental'];
     * $key = $client->idempotency->generateTimeBound($context, 'hourly_sync', 3600);
     * 
     * @example
     * // Rate-limited action - 5-minute cooldown
     * $context = ['user_id' => 123, 'action' => 'password_reset'];
     * $key = $client->idempotency->generateTimeBound($context, 'password_reset', 300);
     */
    public function generateTimeBound(array|object $context, string $operation, ?int $windowSeconds = null): string
    {
        $standardized = $this->standardizeContext($context);
        $contextString = json_encode($standardized);

        // Calculate time window
        $timeWindow = $windowSeconds ? floor(time() / $windowSeconds) : floor(time());

        // Build the hash string: context + operation + time window + token
        $hashString = "{$contextString}_{$operation}_{$timeWindow}{$this->client->getApiToken()}";

        return 'evt_tbx_' . substr(hash('sha256', $hashString), 0, 32);
    }

    /**
     * Generate a content-based idempotency key (alias for generateContextual)
     * 
     * @param array<string, mixed> $data The request data to hash
     * @param int $windowMs Time window in milliseconds (for backwards compatibility)
     * @return string Idempotency key
     * 
     * @deprecated Use generateContextual() or generateTimeBound() instead
     */
    public function generateContentBased(array $data, int $windowMs = 1000): string
    {
        if ($windowMs > 1000) {
            return $this->generateTimeBound($data, 'event', (int)($windowMs / 1000));
        }

        return $this->generateContextual($data);
    }

    /**
     * Validate an idempotency key format
     * 
     * Checks if a key matches expected formats:
     * - evt_[32 hex chars] - Random key
     * - ctx_[32 hex chars] - Contextual key
     * - tbx_[32 hex chars] - Time-bound key
     * 
     * @param string $key The idempotency key to validate
     * @return bool True if the key is valid, false otherwise
     * 
     * @example
     * $key = $client->idempotency->generate();
     * if ($client->idempotency->isValid($key)) {
     *     // Use the key
     * }
     */
    public function isValid(string $key): bool
    {
        return preg_match('/^(evt|evt_ctx|evt_tbx)_[a-f0-9]{32}$/', $key) === 1;
    }

    /**
     * Get the key type from an idempotency key
     * 
     * @param string $key The idempotency key
     * @return string|null The key type ('evt', 'ctx', 'tbx') or null if invalid
     * 
     * @example
     * $type = $client->idempotency->getKeyType('ctx_abc123...');
     * // Returns: 'ctx'
     */
    public function getKeyType(string $key): ?string
    {
        if (!$this->isValid($key)) {
            return null;
        }

        return explode('_', $key)[0];
    }

    /**
     * Standardize the context for consistent key generation
     * 
     * This matches your backend's standardizeContext() logic:
     * - Recursively sorts object/array keys alphabetically
     * - Filters out null values
     * - Handles nested structures
     * - Normalizes list arrays
     * 
     * @param mixed $input The input to standardize
     * @return mixed The standardized output
     */
    public function standardizeContext(mixed $input): mixed
    {
        // Handle list arrays (indexed arrays)
        if (is_array($input) && array_is_list($input)) {
            $cleaned = array_map([$this, 'standardizeContext'], $input);
            return array_values(array_filter($cleaned, fn($item) => !is_null($item)));
        }

        // Handle associative arrays and objects
        if (is_array($input) || is_object($input)) {
            $array = is_object($input) ? (array) $input : $input;
            $cleaned = [];

            // Sort keys for consistency
            $keys = array_keys($array);
            sort($keys);

            foreach ($keys as $key) {
                $value = $this->standardizeContext($array[$key]);
                if (!is_null($value)) {
                    $cleaned[$key] = $value;
                }
            }

            return $cleaned;
        }

        // Return primitives as-is
        return $input;
    }

    /**
     * Create a scoped key generator for a specific context
     * 
     * Returns a closure that generates keys with a fixed context scope.
     * Useful for creating multiple keys within the same context.
     * 
     * @param array<string, mixed>|object $baseContext The base context
     * @return \Closure A function that generates scoped keys
     * 
     * @example
     * // Create a generator for a specific user
     * $userKeyGen = $client->idempotency->createScopedGenerator(['user_id' => 123]);
     * 
     * // Generate different keys for different operations
     * $loginKey = $userKeyGen('login');
     * $logoutKey = $userKeyGen('logout');
     * $updateKey = $userKeyGen('profile_update');
     */
    public function createScopedGenerator(array|object $baseContext): \Closure
    {
        return function (string $operation, ?int $windowSeconds = null) use ($baseContext): string {
            return $this->generateTimeBound($baseContext, $operation, $windowSeconds);
        };
    }

    /**
     * Compare two keys to check if they would conflict
     * 
     * @param string $key1 First idempotency key
     * @param string $key2 Second idempotency key
     * @return bool True if keys are identical
     * 
     * @example
     * $key1 = $client->idempotency->generateContextual($data);
     * $key2 = $client->idempotency->generateContextual($data);
     * 
     * if ($client->idempotency->keysMatch($key1, $key2)) {
     *     echo "These requests would be deduplicated";
     * }
     */
    public function keysMatch(string $key1, string $key2): bool
    {
        return hash_equals($key1, $key2);
    }
}
