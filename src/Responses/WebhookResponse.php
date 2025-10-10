<?php

namespace Eventrel\Client\Responses;

use Eventrel\Client\Entities\OutboundEvent;
use GuzzleHttp\Psr7\Response;

class WebhookResponse
{
    /**
     * The data returned by the webhook.
     * 
     * @var OutboundEvent
     */
    private OutboundEvent $outboundEvent;

    /**
     * The message returned by the webhook.
     * 
     * @var string
     */
    private string $message = '';

    /**
     * The errors returned by the webhook.
     * 
     * @var array
     */
    private array $errors = [];

    /**
     * Indicates if the webhook was successfully delivered.
     * 
     * @var bool
     */
    private bool $success = false;

    /**
     * The status code returned by the webhook.
     * 
     * @var int
     */
    private int $statusCode = 0;

    /**
     * The headers returned by the webhook.
     * 
     * @var array
     */
    private array $headers = [];

    /**
     * The idempotency key for the webhook.
     * 
     * @var string|null
     */
    private ?string $idempotencyKey = null;

    /**
     * WebhookResponse constructor.
     * 
     * @param \GuzzleHttp\Psr7\Response $response
     */
    public function __construct(
        private readonly Response $response
    ) {
        $content = json_decode($response->getBody()->getContents(), true);

        $this->headers = $response->getHeaders();

        $this->outboundEvent = OutboundEvent::from($content['data']['outbound_event'] ?? []);
        $this->message = $content['data']['message'] ?? '';
        $this->errors = $content['errors'] ?? [];
        $this->success = $content['success'] ?? false;
        $this->statusCode = $content['status_code'] ?? 0;

        $this->idempotencyKey = ($response->hasHeader('x-idempotency-key')) ?
            $response->getHeaderLine('x-idempotency-key') :
            $content['data']['idempotency_key'] ?? null;
    }

    /**
     * Get the outbound event data.
     *
     * @return OutboundEvent
     */
    public function getOutboundEvent(): OutboundEvent
    {
        return $this->outboundEvent;
    }

    /**
     * Get the ID of the outbound event.
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->outboundEvent->uuid;
    }

    /**
     * Get the payload of the outbound event.
     *
     * @return array
     */
    public function getPayload(): array
    {
        return $this->outboundEvent->payload ?? [];
    }

    // public function getStatus(): string
    // {
    //     return $this->data['status'] ?? '';
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

    /**
     * Get the response message.
     *
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Get the idempotency key for the webhook.
     *
     * @return string|null
     */
    public function getIdempotencyKey(): ?string
    {
        return $this->idempotencyKey;
    }

    /**
     * Get the headers returned by the webhook.
     *
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Get the outbound event data.
     *
     * @return OutboundEvent
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Check if the request was successful.
     * 
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }
}
