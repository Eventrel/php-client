<?php

namespace Eventrel\Client;

use Carbon\Carbon;

class WebhookBuilder
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
