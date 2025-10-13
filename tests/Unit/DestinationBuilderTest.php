<?php

namespace Eventrel\Client\Tests\Unit;

use Eventrel\Client\Builders\DestinationBuilder;
use Eventrel\Client\Enums\WebhookMode;
use Eventrel\Client\Responses\DestinationResponse;
use Eventrel\Client\Tests\TestCase;

class DestinationBuilderTest extends TestCase
{
    /** @test */
    public function it_can_build_simple_outbound_destination()
    {
        $client = $this->createMockClient([
            $this->mockDestinationResponse([
                'data' => [
                    'name' => 'My API',
                    'webhook_url' => 'https://api.example.com/webhook',
                    'webhook_mode' => 'outbound',
                ],
            ]),
        ]);

        $response = $client->destinations->builder()
            ->name('My API')
            ->url('https://api.example.com/webhook')
            ->outbound()
            ->create();

        $this->assertInstanceOf(DestinationResponse::class, $response);
        $this->assertEquals('outbound', $response->webhookMode->value);
        
        $body = $this->getLastRequestBody();
        $this->assertEquals('My API', $body['name']);
        $this->assertEquals('https://api.example.com/webhook', $body['webhook_url']);
        $this->assertEquals('outbound', $body['webhook_mode']);
    }

    /** @test */
    public function it_can_set_webhook_mode_using_methods()
    {
        $client = $this->createMockClient([
            $this->mockDestinationResponse(),
        ]);

        // Test outbound
        $client->destinations->builder()
            ->name('Test')
            ->url('https://example.com/webhook')
            ->outbound()
            ->create();
        $this->assertRequestBodyContains(['webhook_mode' => 'outbound']);

        // Test bidirectional
        $client->destinations->builder()
            ->name('Test')
            ->url('https://example.com/webhook')
            ->bidirectional()
            ->create();
        $this->assertRequestBodyContains(['webhook_mode' => 'bidirectional']);

        // Test inbound
        $client->destinations->builder()
            ->name('Test')
            ->url('https://example.com/webhook')
            ->inbound()
            ->create();
        $this->assertRequestBodyContains(['webhook_mode' => 'inbound']);
    }

    /** @test */
    public function it_can_set_webhook_mode_directly()
    {
        $client = $this->createMockClient([
            $this->mockDestinationResponse(),
        ]);

        $response = $client->destinations->builder()
            ->name('Test')
            ->url('https://example.com/webhook')
            ->mode(WebhookMode::BIDIRECTIONAL)
            ->create();

        $this->assertRequestBodyContains([
            'webhook_mode' => 'bidirectional',
        ]);
    }

    /** @test */
    public function it_can_set_description()
    {
        $client = $this->createMockClient([
            $this->mockDestinationResponse(),
        ]);

        $response = $client->destinations->builder()
            ->name('Test')
            ->url('https://example.com/webhook')
            ->outbound()
            ->withDescription('Main production webhook endpoint')
            ->create();

        $this->assertRequestBodyContains([
            'description' => 'Main production webhook endpoint',
        ]);
    }

    /** @test */
    public function it_can_add_custom_headers()
    {
        $client = $this->createMockClient([
            $this->mockDestinationResponse(),
        ]);

        $response = $client->destinations->builder()
            ->name('Test')
            ->url('https://example.com/webhook')
            ->outbound()
            ->withHeader('X-Custom-Header', 'value1')
            ->withHeader('X-Another-Header', 'value2')
            ->create();

        $body = $this->getLastRequestBody();
        $this->assertEquals('value1', $body['headers']['X-Custom-Header']);
        $this->assertEquals('value2', $body['headers']['X-Another-Header']);
    }

    /** @test */
    public function it_can_set_all_headers_at_once()
    {
        $client = $this->createMockClient([
            $this->mockDestinationResponse(),
        ]);

        $headers = [
            'X-API-Key' => 'secret',
            'X-Environment' => 'production',
        ];

        $response = $client->destinations->builder()
            ->name('Test')
            ->url('https://example.com/webhook')
            ->outbound()
            ->withHeaders($headers)
            ->create();

        $this->assertRequestBodyContains([
            'headers' => $headers,
        ]);
    }

    /** @test */
    public function it_can_add_metadata()
    {
        $client = $this->createMockClient([
            $this->mockDestinationResponse(),
        ]);

        $response = $client->destinations->builder()
            ->name('Test')
            ->url('https://example.com/webhook')
            ->outbound()
            ->withMetadata(['environment' => 'production', 'team' => 'engineering'])
            ->create();

        $this->assertRequestBodyContains([
            'metadata' => ['environment' => 'production', 'team' => 'engineering'],
        ]);
    }

    /** @test */
    public function it_can_configure_timeout()
    {
        $client = $this->createMockClient([
            $this->mockDestinationResponse(),
        ]);

        $response = $client->destinations->builder()
            ->name('Test')
            ->url('https://example.com/webhook')
            ->outbound()
            ->withTimeout(45)
            ->create();

        $this->assertRequestBodyContains([
            'timeout' => 45,
        ]);
    }

    /** @test */
    public function it_can_configure_retry_limit()
    {
        $client = $this->createMockClient([
            $this->mockDestinationResponse(),
        ]);

        $response = $client->destinations->builder()
            ->name('Test')
            ->url('https://example.com/webhook')
            ->outbound()
            ->withRetryLimit(5)
            ->create();

        $this->assertRequestBodyContains([
            'retry_limit' => 5,
        ]);
    }

    /** @test */
    public function it_can_configure_rate_limits()
    {
        $client = $this->createMockClient([
            $this->mockDestinationResponse(),
        ]);

        $response = $client->destinations->builder()
            ->name('Test')
            ->url('https://example.com/webhook')
            ->outbound()
            ->withRateLimit(perMinute: 1000, perHour: 50000, perDay: 1000000)
            ->create();

        $body = $this->getLastRequestBody();
        $this->assertEquals(1000, $body['rate_limit_per_minute']);
        $this->assertEquals(50000, $body['rate_limit_per_hour']);
        $this->assertEquals(1000000, $body['rate_limit_per_day']);
    }

    /** @test */
    public function it_can_configure_batching()
    {
        $client = $this->createMockClient([
            $this->mockDestinationResponse(),
        ]);

        $response = $client->destinations->builder()
            ->name('Test')
            ->url('https://example.com/webhook')
            ->outbound()
            ->withBatching(size: 50, strategy: 'batched')
            ->create();

        $body = $this->getLastRequestBody();
        $this->assertTrue($body['webhook_config']['batching']['enabled']);
        $this->assertEquals(50, $body['webhook_config']['batching']['max_size']);
        $this->assertEquals('batched', $body['webhook_config']['batching']['strategy']);
    }

    /** @test */
    public function it_can_configure_event_filtering()
    {
        $client = $this->createMockClient([
            $this->mockDestinationResponse(),
        ]);

        $eventTypes = ['user.created', 'user.updated', 'order.completed'];

        $response = $client->destinations->builder()
            ->name('Test')
            ->url('https://example.com/webhook')
            ->outbound()
            ->withEventFiltering($eventTypes)
            ->create();

        $body = $this->getLastRequestBody();
        $this->assertEquals($eventTypes, $body['webhook_config']['event_filter']['types']);
    }

    /** @test */
    public function it_can_enable_dead_letter_queue()
    {
        $client = $this->createMockClient([
            $this->mockDestinationResponse(),
        ]);

        $response = $client->destinations->builder()
            ->name('Test')
            ->url('https://example.com/webhook')
            ->outbound()
            ->withDeadLetterQueue()
            ->create();

        $body = $this->getLastRequestBody();
        $this->assertTrue($body['webhook_config']['dead_letter_queue']['enabled']);
    }

    /** @test */
    public function it_can_configure_ssl_verification()
    {
        $client = $this->createMockClient([
            $this->mockDestinationResponse(),
        ]);

        $response = $client->destinations->builder()
            ->name('Test')
            ->url('https://example.com/webhook')
            ->outbound()
            ->verifySsl()
            ->create();

        $body = $this->getLastRequestBody();
        $this->assertTrue($body['webhook_config']['verify_ssl']);
    }

    /** @test */
    public function it_can_disable_ssl_verification()
    {
        $client = $this->createMockClient([
            $this->mockDestinationResponse(),
        ]);

        $response = $client->destinations->builder()
            ->name('Test')
            ->url('https://example.com/webhook')
            ->outbound()
            ->skipSslVerification()
            ->create();

        $body = $this->getLastRequestBody();
        $this->assertFalse($body['webhook_config']['verify_ssl']);
    }

    /** @test */
    public function it_can_set_destination_as_inactive()
    {
        $client = $this->createMockClient([
            $this->mockDestinationResponse(),
        ]);

        $response = $client->destinations->builder()
            ->name('Test')
            ->url('https://example.com/webhook')
            ->outbound()
            ->inactive()
            ->create();

        $this->assertRequestBodyContains([
            'is_active' => false,
        ]);
    }

    /** @test */
    public function it_can_build_complex_destination()
    {
        $client = $this->createMockClient([
            $this->mockDestinationResponse(),
        ]);

        $response = $client->destinations->builder()
            ->name('Production Analytics')
            ->url('https://analytics.example.com/webhook')
            ->bidirectional()
            ->withDescription('Main analytics endpoint for production')
            ->withHeader('X-API-Key', 'secret-key')
            ->withHeader('X-Environment', 'production')
            ->withMetadata(['team' => 'analytics', 'priority' => 'high'])
            ->withTimeout(45)
            ->withRetryLimit(5)
            ->withRateLimit(perMinute: 1000, perHour: 50000)
            ->withBatching(size: 50, strategy: 'batched')
            ->withEventFiltering(['user.created', 'user.updated', 'order.completed'])
            ->withDeadLetterQueue()
            ->verifySsl()
            ->create();

        $this->assertInstanceOf(DestinationResponse::class, $response);
        
        $body = $this->getLastRequestBody();
        $this->assertEquals('Production Analytics', $body['name']);
        $this->assertEquals('bidirectional', $body['webhook_mode']);
        $this->assertArrayHasKey('headers', $body);
        $this->assertArrayHasKey('webhook_config', $body);
    }

    /** @test */
    public function it_maintains_fluent_interface_on_all_methods()
    {
        $client = $this->createMockClient([]);

        $builder = $client->destinations->builder();
        
        $this->assertInstanceOf(DestinationBuilder::class, $builder->name('Test'));
        $this->assertInstanceOf(DestinationBuilder::class, $builder->url('https://example.com'));
        $this->assertInstanceOf(DestinationBuilder::class, $builder->outbound());
        $this->assertInstanceOf(DestinationBuilder::class, $builder->withDescription('Test'));
        $this->assertInstanceOf(DestinationBuilder::class, $builder->withHeader('key', 'value'));
        $this->assertInstanceOf(DestinationBuilder::class, $builder->withMetadata([]));
        $this->assertInstanceOf(DestinationBuilder::class, $builder->withTimeout(30));
        $this->assertInstanceOf(DestinationBuilder::class, $builder->withRetryLimit(3));
        $this->assertInstanceOf(DestinationBuilder::class, $builder->withRateLimit());
        $this->assertInstanceOf(DestinationBuilder::class, $builder->withBatching());
        $this->assertInstanceOf(DestinationBuilder::class, $builder->verifySsl());
    }

    /** @test */
    public function it_throws_exception_when_name_not_set()
    {
        $this->expectException(\Exception::class);
        
        $client = $this->createMockClient([
            $this->mockDestinationResponse(),
        ]);

        $client->destinations->builder()
            ->url('https://example.com/webhook')
            ->outbound()
            ->create(); // No name set
    }

    /** @test */
    public function it_throws_exception_when_url_not_set()
    {
        $this->expectException(\Exception::class);
        
        $client = $this->createMockClient([
            $this->mockDestinationResponse(),
        ]);

        $client->destinations->builder()
            ->name('Test')
            ->outbound()
            ->create(); // No URL set
    }
}
