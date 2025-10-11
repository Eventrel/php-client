<?php

namespace Eventrel\Client\Builders\Concerns;

/**
 * Provides idempotency functionality to prevent duplicate event delivery.
 * 
 * Idempotency ensures that if the same event is sent multiple times
 * (due to retries, network issues, or user error), it will only be
 * processed once by the receiving system.
 * 
 * Usage:
 * ```php
 * $builder->idempotencyKey('payment-order-12345')->send();
 * $builder->withUniqueKey()->send(); // Auto-generates unique key
 * ```
 */
trait CanIdempotentize
{
    /**
     * The idempotency key for preventing duplicate event delivery.
     * 
     * When set, the API will ensure that events with the same key
     * are not delivered multiple times, even if the send() method
     * is called repeatedly.
     */
    private ?string $idempotencyKey = null;

    /**
     * Set an explicit idempotency key.
     * 
     * Use this when you have a natural unique identifier from your
     * domain (e.g., order ID, transaction ID, user action ID).
     * 
     * Example:
     * ```php
     * ->idempotencyKey('payment-' . $orderId)
     * ->idempotencyKey('user-signup-' . $userId)
     * ```
     *
     * @param string $key The unique idempotency key
     * @return $this Fluent interface
     */
    public function idempotencyKey(string $key): self
    {
        $this->idempotencyKey = $key;

        return $this;
    }

    /**
     * Auto-generate a unique idempotency key.
     * 
     * Useful when you don't have a natural unique identifier
     * but still want idempotent delivery. The key will be
     * automatically generated when needed.
     *
     * @return $this Fluent interface
     */
    public function withUniqueKey(): self
    {
        // TODO: Implement unique key generation
        // $this->idempotencyKey = $this->generateUuid();

        return $this;
    }

    /**
     * Get the current idempotency key.
     * 
     * If no key has been set, this will automatically generate
     * one by calling withUniqueKey().
     *
     * @return string The idempotency key
     */
    public function getIdempotencyKey(): string
    {
        if (!$this->idempotencyKey) {
            $this->withUniqueKey();
        }

        return $this->idempotencyKey;
    }
}
