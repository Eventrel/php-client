<?php

namespace Eventrel\Client\Builders\Concerns;

trait CanIdempotentize
{
    /**
     * Set idempotency key to prevent duplicate processing
     */
    public function idempotencyKey(string $key): self
    {
        $this->idempotencyKey = $key;

        return $this;
    }

    /**
     * Set unique idempotency
     */
    public function withUniqueKey(): self
    {
        // TODO: Implement unique key generation
        //     $this->idempotencyKey = $this->generateUuid();

        return $this;
    }

    /**
     * Get the idempotency key
     */
    public function getIdempotencyKey(): string
    {
        if (!$this->idempotencyKey) {
            $this->withUniqueKey();
        }

        return $this->idempotencyKey;
    }
}
