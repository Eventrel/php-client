<?php

namespace Eventrel\Responses;

use Eventrel\Entities\Destination;
use GuzzleHttp\Psr7\Response;

/**
 * Response object for single destination retrieval
 * 
 * Represents the API response when fetching a single destination by its UUID.
 * Contains the full destination details including configuration, statistics,
 * and status information.
 * 
 * @package Eventrel\Responses
 */
class DestinationResponse extends BaseResponse
{
    /**
     * The destination entity containing destination details.
     */
    private ?Destination $destination;

    /**
     * Create a new DestinationResponse instance.
     * 
     * Parses the Guzzle HTTP response and extracts all relevant data
     * including the destination, metadata, and headers.
     *
     * @param Response $response The Guzzle HTTP response object
     */
    public function __construct(Response $response, private bool $asDestination = false)
    {
        parent::__construct($response);
    }

    /**
     * Parse and populate response data from the Guzzle HTTP response.
     * 
     * Extracts JSON content, headers, and idempotency information,
     * populating all class properties for convenient access.
     *
     * @return void
     */
    protected function parseResponse(): void
    {
        parent::parseResponse(); // Parse common fields first

        if ($this->asDestination) {
            $this->destination = Destination::from($this->data['destination'] ?? []);
        }
    }

    /**
     * Get the complete destination entity.
     * 
     * Returns the full Destination object with all details.
     *
     * @return Destination The complete destination entity
     */
    public function getDetails(): Destination
    {
        return $this->destination;
    }
}
