<?php

namespace Eventrel\Client\Responses;

use GuzzleHttp\Psr7\Response;

class BatchWebhookResponse
{
    public function __construct(
        private Response $response
    ) {
        $content = json_decode($response->getBody()->getContents(), true);

        dd(
            $content
        );
    }

    // /**
    //  * Get all webhook responses in the batch
    //  */
    // public function getWebhooks(): array
    // {
    //     return array_map(
    //         fn($webhook) => new WebhookResponse($webhook),
    //         $this->data['webhooks'] ?? []
    //     );
    // }

    // /**
    //  * Get batch metadata
    //  */
    // public function getBatchId(): ?string
    // {
    //     return $this->data['batch_id'] ?? null;
    // }

    // /**
    //  * Get total count of webhooks in batch
    //  */
    // public function getTotalCount(): int
    // {
    //     return $this->data['total_count'] ?? 0;
    // }

    // /**
    //  * Get count of successful webhooks
    //  */
    // public function getSuccessCount(): int
    // {
    //     return $this->data['success_count'] ?? 0;
    // }

    // /**
    //  * Get count of failed webhooks
    //  */
    // public function getFailureCount(): int
    // {
    //     return $this->data['failure_count'] ?? 0;
    // }

    // /**
    //  * Check if all webhooks in batch were successful
    //  */
    // public function isAllSuccessful(): bool
    // {
    //     return $this->getFailureCount() === 0 && $this->getSuccessCount() > 0;
    // }

    // /**
    //  * Check if any webhooks failed
    //  */
    // public function hasFailures(): bool
    // {
    //     return $this->getFailureCount() > 0;
    // }

    // /**
    //  * Get only successful webhook responses
    //  */
    // public function getSuccessfulWebhooks(): array
    // {
    //     return array_filter(
    //         $this->getWebhooks(),
    //         fn(WebhookResponse $webhook) => $webhook->isSuccessful()
    //     );
    // }

    // /**
    //  * Get only failed webhook responses
    //  */
    // public function getFailedWebhooks(): array
    // {
    //     return array_filter(
    //         $this->getWebhooks(),
    //         fn(WebhookResponse $webhook) => $webhook->isFailed()
    //     );
    // }

    // /**
    //  * Get batch processing time in milliseconds
    //  */
    // public function getProcessingTime(): ?int
    // {
    //     return $this->data['processing_time_ms'] ?? null;
    // }

    // /**
    //  * Convert to array
    //  */
    // public function toArray(): array
    // {
    //     return $this->data;
    // }
}
