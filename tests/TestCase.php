<?php

namespace Eventrel\Client\Tests;

use Eventrel\Client\EventrelClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base Test Case for Eventrel Client Tests
 * 
 * Provides helper methods for mocking HTTP responses,
 * creating client instances, and common test assertions.
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * History of HTTP requests made during tests
     */
    protected array $requestHistory = [];

    /**
     * Create a mock Eventrel client with predefined responses
     * 
     * @param array $responses Array of Response objects or arrays [status, body, headers]
     * @return EventrelClient
     */
    protected function createMockClient(array $responses = []): EventrelClient
    {
        $this->requestHistory = [];

        $mock = new MockHandler($this->formatResponses($responses));

        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push(Middleware::history($this->requestHistory));

        $httpClient = new Client(['handler' => $handlerStack]);

        // Use reflection to inject the mock HTTP client
        $client = new EventrelClient('test-token', 'v1', 'https://api.test.eventrel.sh');

        $reflection = new \ReflectionClass($client);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($client, $httpClient);

        return $client;
    }

    /**
     * Format responses into Response objects
     */
    protected function formatResponses(array $responses): array
    {
        return array_map(function ($response) {
            if ($response instanceof Response) {
                return $response;
            }

            return new Response(
                $response['status'] ?? 200,
                $response['headers'] ?? [],
                json_encode($response['body'] ?? [])
            );
        }, $responses);
    }

    /**
     * Get the last HTTP request made
     */
    protected function getLastRequest(): ?array
    {
        return end($this->requestHistory) ?: null;
    }

    /**
     * Get the last request URI
     */
    protected function getLastRequestUri(): ?string
    {
        $request = $this->getLastRequest();
        return $request ? (string) $request['request']->getUri() : null;
    }

    /**
     * Get the last request method
     */
    protected function getLastRequestMethod(): ?string
    {
        $request = $this->getLastRequest();
        return $request ? $request['request']->getMethod() : null;
    }

    /**
     * Get the last request body as array
     */
    protected function getLastRequestBody(): ?array
    {
        $request = $this->getLastRequest();
        if (!$request) {
            return null;
        }

        $body = (string) $request['request']->getBody();
        return json_decode($body, true);
    }

    /**
     * Assert that the last request was made to the expected endpoint
     */
    protected function assertRequestMadeTo(string $expectedPath): void
    {
        $uri = $this->getLastRequestUri();
        $this->assertNotNull($uri, 'No request was made');
        $this->assertStringContainsString($expectedPath, $uri);
    }

    /**
     * Assert that the last request used the expected HTTP method
     */
    protected function assertRequestMethod(string $expectedMethod): void
    {
        $method = $this->getLastRequestMethod();
        $this->assertNotNull($method, 'No request was made');
        $this->assertEquals($expectedMethod, $method);
    }

    /**
     * Assert that the last request body contains the expected data
     */
    protected function assertRequestBodyContains(array $expectedData): void
    {
        $body = $this->getLastRequestBody();
        $this->assertNotNull($body, 'Request had no body');

        foreach ($expectedData as $key => $value) {
            $this->assertArrayHasKey($key, $body);
            $this->assertEquals($value, $body[$key], "Expected '$key' to be '$value'");
        }
    }

    /**
     * Create a successful event response
     */
    protected function mockEventResponse(array $overrides = []): array
    {
        return array_merge([
            'status' => 200,
            'body' => [
                'data' => array_merge([
                    'identifier' => 'evt_' . uniqid(),
                    'event_type' => 'test.event',
                    'destination' => 'dest_test123',
                    'payload' => ['test' => 'data'],
                    'tags' => [],
                    'status' => 'pending',
                    'idempotency_key' => 'idem_' . uniqid(),
                    'scheduled_at' => null,
                    'created_at' => '2025-01-15T10:00:00Z',
                    'updated_at' => '2025-01-15T10:00:00Z',
                ], $overrides['data'] ?? []),
            ],
        ], $overrides);
    }

    /**
     * Create a successful batch event response
     */
    protected function mockBatchEventResponse(int $count = 3, array $overrides = []): array
    {
        $events = [];
        for ($i = 0; $i < $count; $i++) {
            $events[] = [
                'identifier' => 'evt_' . uniqid() . '_' . $i,
                'event_type' => 'test.event',
                'destination' => 'dest_test123',
                'payload' => ['index' => $i],
                'tags' => [],
                'status' => 'pending',
                'created_at' => '2025-01-15T10:00:00Z',
            ];
        }

        return array_merge([
            'status' => 200,
            'body' => [
                'data' => array_merge([
                    'batch_id' => 'batch_' . uniqid(),
                    'event_type' => 'test.event',
                    'destination' => 'dest_test123',
                    'total_events' => $count,
                    'events' => $events,
                    'created_at' => '2025-01-15T10:00:00Z',
                ], $overrides['data'] ?? []),
            ],
        ], $overrides);
    }

    /**
     * Create a successful destination response
     */
    protected function mockDestinationResponse(array $overrides = []): array
    {
        return array_merge([
            'status' => 200,
            'body' => [
                'data' => array_merge([
                    'identifier' => 'dest_' . uniqid(),
                    'name' => 'Test Destination',
                    'webhook_url' => 'https://example.com/webhook',
                    'webhook_mode' => 'outbound',
                    'description' => null,
                    'headers' => [],
                    'metadata' => [],
                    'webhook_config' => [],
                    'timeout' => 30,
                    'retry_limit' => 3,
                    'rate_limit_per_minute' => null,
                    'rate_limit_per_hour' => null,
                    'rate_limit_per_day' => null,
                    'is_active' => true,
                    'created_at' => '2025-01-15T10:00:00Z',
                    'updated_at' => '2025-01-15T10:00:00Z',
                ], $overrides['data'] ?? []),
            ],
        ], $overrides);
    }

    /**
     * Create an error response
     */
    protected function mockErrorResponse(string $message = 'Test error', int $status = 400): array
    {
        return [
            'status' => $status,
            'body' => [
                'error' => [
                    'message' => $message,
                    'code' => 'test_error',
                ],
            ],
        ];
    }
}
