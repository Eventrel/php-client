<?php

namespace Eventrel\Client\Responses;

use GuzzleHttp\Psr7\Response;

abstract class BaseResponse
{
    public function __construct(
        private Response $response
    ) {
        // $this->parseResponse();
    }
}
