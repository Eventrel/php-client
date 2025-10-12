<?php

namespace Eventrel\Client\Responses;

use GuzzleHttp\Psr7\Response;

/**
 * Abstract base class for all Eventrel API response objects
 * 
 * Provides common functionality for parsing API responses and accessing
 * standard response metadata like errors, success status, HTTP codes, and headers.
 * All concrete response classes extend this base to inherit common behavior
 * and ensure consistent structure across the SDK.
 * 
 * @package Eventrel\Client\Responses
 */
abstract class BaseResponse
{
    /**
     * Parsed response content
     * 
     * @var array<string, mixed>
     */
    protected array $content = [];

    /**
     * Response data payload
     * 
     * @var array<string, mixed>
     */
    protected array $data = [];

    /**
     * Array of validation or processing errors
     * 
     * @var array<int, array<string, mixed>>
     */
    protected array $errors = [];

    /**
     * Whether the request was successful
     * 
     * @var bool
     */
    protected bool $success = false;

    /**
     * HTTP status code from the API
     * 
     * @var int
     */
    protected int $statusCode = 0;

    /**
     * HTTP headers from the response
     * 
     * @var array<string, array<int, string>>
     */
    protected array $headers = [];

    /**
     * Human-readable message from the API (if present)
     * 
     * @var string|null
     */
    protected ?string $message = null;

    /**
     * Create a new response instance
     * 
     * @param Response $response The Guzzle HTTP response object from the API
     */
    public function __construct(
        protected Response $response
    ) {
        $this->parseResponse();
    }

    /**
     * Parse response data from the HTTP response
     * 
     * Base implementation extracts standard fields present in all API responses:
     * - JSON content and data payload
     * - Success status and errors
     * - HTTP status code and headers
     * - Optional message field
     * 
     * Concrete classes should override this method and call parent::parseResponse()
     * at the start to populate base fields, then extract their specific response data.
     * 
     * @return void
     */
    protected function parseResponse(): void
    {
        $this->content = json_decode($this->response->getBody()->getContents(), true) ?? [];
        $this->data = $this->content['data'] ?? [];

        $this->errors = $this->content['errors'] ?? [];
        $this->success = $this->content['success'] ?? false;
        $this->statusCode = $this->content['status_code'] ?? 0;
        $this->headers = $this->response->getHeaders();

        // Optional message field (not present in all responses)
        $this->message = $this->data['message'] ?? $this->content['message'] ?? null;
    }

    /**
     * Get the raw HTTP response object
     * 
     * Provides access to the underlying Guzzle response for advanced usage.
     * 
     * @return Response The raw Guzzle HTTP response
     */
    public function getRawResponse(): Response
    {
        return $this->response;
    }

    /**
     * Check if the request was successful
     * 
     * @return bool True if the request succeeded
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Get any validation or processing errors
     * 
     * Returns an array of error details if the request failed.
     * Empty array if no errors occurred.
     * 
     * @return array<int, array<string, mixed>> Array of error messages/details
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get the HTTP status code from the API response
     * 
     * @return int The HTTP status code (e.g., 200, 201, 400, 500)
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Get all HTTP headers from the response
     * 
     * Headers may contain rate limit info, request IDs, or other
     * metadata useful for debugging and monitoring.
     * 
     * @return array<string, array<int, string>> Associative array of header name => value(s)
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Get the human-readable response message
     * 
     * Returns the message if present in the response, null otherwise.
     * 
     * @return string|null The response message
     */
    public function getMessage(): ?string
    {
        return $this->message;
    }
}
