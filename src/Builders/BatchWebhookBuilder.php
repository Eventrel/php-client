<?php

namespace Eventrel\Client\Builders;

use Carbon\Carbon;
use Eventrel\Client\EventrelClient;

class BatchWebhookBuilder
{
    /**
     * WebhookBuilder constructor.
     * 
     * @param \Eventrel\Client\EventrelClient $client
     * @param string $eventType
     */
    public function __construct(
        private EventrelClient $client,
        private string $eventType
    ) {
        // 
    }
}
