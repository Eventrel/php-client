<?php

namespace Eventrel\Client\Tests\Unit;

use Eventrel\Client\Entities\Destination;
use Eventrel\Client\Enums\WebhookMode;
use Eventrel\Client\Exceptions\EventrelException;
use Eventrel\Client\Responses\DestinationResponse;
use Eventrel\Client\Responses\DestinationListResponse;
use Eventrel\Client\Tests\TestCase;

class DestinationServiceTest extends TestCase
{
    /** @test */
    public function it_can_create_simple_destination()
    {
        $client = $this->createMockClient([
            $this->mockDestinationResponse([
                'data' => [
                    'name' => 'Production API',
                    'webhook_url' => 'https://api.example.com/webhook',
                    'webhook_mode' => 'outbound',
                ],
            ]),
        ]);

        $response = $client->destinations->create(
            name: 'Production API',
            webhookUrl: 'https://api.example.com/webhook',
            webhookMode: WebhookMode::OUTBOUND
        );

        $destination = $response->getDetails();

        $this->assertInstanceOf(DestinationResponse::class, $response);
        $this->assertEquals('Production API', $destination->name);
        $this->assertEquals('https://api.example.com/webhook', $destination->webhookUrl);
        $this->assertRequestMadeTo('/destinations');
        $this->assertRequestMethod('POST');
    }

    /** @test */
    public function it_can_create_destination_with_full_configuration()
    {
        $client = $this->createMockClient([
            $this->mockDestinationResponse([
                'data' => [
                    'name' => 'Analytics Webhook',
                    'webhook_url' => 'https://analytics.example.com/webhook',
                    'webhook_mode' => 'bidirectional',
                    'description' => 'Main analytics endpoint',
                    'headers' => ['X-Custom-Header' => 'value'],
                    'metadata' => ['environment' => 'production'],
                    'timeout' => 45,
                    'retry_limit' => 5,
                    'rate_limit_per_minute' => 1000,
                ],
            ]),
        ]);

        $response = $client->destinations->create(
            name: 'Analytics Webhook',
            webhookUrl: 'https://analytics.example.com/webhook',
            webhookMode: WebhookMode::BIDIRECTIONAL,
            description: 'Main analytics endpoint',
            headers: ['X-Custom-Header' => 'value'],
            metadata: ['environment' => 'production'],
            timeout: 45,
            retryLimit: 5,
            rateLimitPerMinute: 1000
        );

        $destination = $response->getDetails();

        $this->assertEquals('Analytics Webhook', $destination->name);
        $this->assertEquals(45, $destination->timeout);
        $this->assertEquals(5, $destination->retryLimit);
        $this->assertEquals(1000, $destination->rateLimitPerMinute);

        $body = $this->getLastRequestBody();
        $this->assertEquals('bidirectional', $body['webhook_mode']);
        $this->assertEquals(['X-Custom-Header' => 'value'], $body['headers']);
        $this->assertEquals(['environment' => 'production'], $body['metadata']);
    }

    /** @test */
    public function it_can_create_destination_with_webhook_config()
    {
        $client = $this->createMockClient([
            $this->mockDestinationResponse([
                'data' => [
                    'webhook_config' => [
                        'batching' => [
                            'enabled' => true,
                            'max_size' => 50,
                            'strategy' => 'batched',
                        ],
                        'event_filter' => [
                            'types' => ['user.created', 'user.updated'],
                        ],
                    ],
                ],
            ]),
        ]);

        $response = $client->destinations->create(
            name: 'Test Destination',
            webhookUrl: 'https://example.com/webhook',
            webhookMode: WebhookMode::OUTBOUND,
            webhookConfig: [
                'batching' => [
                    'enabled' => true,
                    'max_size' => 50,
                    'strategy' => 'batched',
                ],
                'event_filter' => [
                    'types' => ['user.created', 'user.updated'],
                ],
            ]
        );

        $body = $this->getLastRequestBody();
        $this->assertArrayHasKey('webhook_config', $body);
        $this->assertTrue($body['webhook_config']['batching']['enabled']);
    }

    /** @test */
    public function it_can_get_destination_by_id()
    {
        $destinationId = 'dest_test123';

        $client = $this->createMockClient([
            $this->mockDestinationResponse([
                'data' => [
                    'uuid' =>  $destinationId,
                    'name' => 'Test Destination',
                ],
            ]),
        ]);

        $response = $client->destinations->get($destinationId, asDestination: true);

        $this->assertInstanceOf(DestinationResponse::class, $response);
        $this->assertEquals($destinationId, $response->uuid);
        $this->assertRequestMadeTo("/destinations/{$destinationId}");
        $this->assertRequestMethod('GET');
    }

    /** @test */
    public function it_can_get_destination_as_entity()
    {
        $destinationId = 'dest_test123';

        $client = $this->createMockClient([
            $this->mockDestinationResponse([
                'data' => [
                    'uuid' =>  $destinationId,
                ],
            ]),
        ]);

        $destination = $client->destinations->get($destinationId, asDestination: true);

        $this->assertInstanceOf(Destination::class, $destination);
    }

    /** @test */
    public function it_throws_exception_when_destination_not_found()
    {
        $this->expectException(EventrelException::class);

        $client = $this->createMockClient([
            $this->mockErrorResponse('Destination not found', 404),
        ]);

        $client->destinations->get('dest_nonexistent');
    }

    /** @test */
    public function it_can_list_destinations()
    {
        $client = $this->createMockClient([
            [
                'status' => 200,
                'body' => [
                    'data' => [
                        ['uuid' =>  'dest_1', 'name' => 'Destination 1'],
                        ['uuid' =>  'dest_2', 'name' => 'Destination 2'],
                        ['uuid' =>  'dest_3', 'name' => 'Destination 3'],
                    ],
                    'pagination' => [
                        'current_page' => 1,
                        'per_page' => 20,
                        'total' => 3,
                        'last_page' => 1,
                    ],
                ],
            ],
        ]);

        $response = $client->destinations->list();

        $this->assertInstanceOf(DestinationListResponse::class, $response);
        $this->assertCount(3, $response->get());
        $this->assertEquals(1, $response->getCurrentPage());
        $this->assertRequestMadeTo('/destinations');
        $this->assertRequestMethod('GET');
    }

    /** @test */
    public function it_can_list_destinations_with_filters()
    {
        $client = $this->createMockClient([
            [
                'status' => 200,
                'body' => [
                    'data' => [],
                    'pagination' => [
                        'current_page' => 2,
                        'per_page' => 50,
                        'total' => 100,
                        'last_page' => 2,
                    ],
                ],
            ],
        ]);

        $response = $client->destinations->list(
            page: 2,
            perPage: 50,
            additionalFilters: [
                'webhook_mode' => WebhookMode::OUTBOUND,
                'is_active' => true,
            ]
        );

        $uri = $this->getLastRequestUri();

        $this->assertStringContainsString('page=2', $uri);
        $this->assertStringContainsString('per_page=50', $uri);
        $this->assertStringContainsString('webhook_mode=outbound', $uri);
        $this->assertStringContainsString('is_active=1', $uri);
    }

    /** @test */
    public function it_can_update_destination()
    {
        $destinationId = 'dest_test123';

        $client = $this->createMockClient([
            $this->mockDestinationResponse([
                'data' => [
                    'uuid' =>  $destinationId,
                    'name' => 'Updated Name',
                    'timeout' => 60,
                ],
            ]),
        ]);

        $response = $client->destinations->update(
            uuid: $destinationId,
            data: [
                'name' => 'Updated Name',
                'timeout' => 60,
            ],
            asDestination: true
        );

        $this->assertEquals('Updated Name', $response->name);
        $this->assertEquals(60, $response->timeout);
        $this->assertRequestMadeTo("/destinations/{$destinationId}");
        $this->assertRequestMethod('PUT');

        $body = $this->getLastRequestBody();
        $this->assertEquals('Updated Name', $body['name']);
        $this->assertEquals(60, $body['timeout']);
    }

    /** @test */
    public function it_can_delete_destination()
    {
        $destinationId = 'dest_test123';

        $client = $this->createMockClient([
            ['status' => 204, 'body' => []],
        ]);

        $result = $client->destinations->delete($destinationId);

        $this->assertTrue($result);
        $this->assertRequestMadeTo("/destinations/{$destinationId}");
        $this->assertRequestMethod('DELETE');
    }

    // /** @test */
    // public function it_can_activate_destination()
    // {
    //     $destinationId = 'dest_test123';

    //     $client = $this->createMockClient([
    //         $this->mockDestinationResponse([
    //             'data' => [
    //                 'uuid' =>  $destinationId,
    //                 'is_active' => true,
    //             ],
    //         ]),
    //     ]);

    //     $response = $client->destinations->activate($destinationId);

    //     $this->assertTrue($response->isActive);
    //     $this->assertRequestMadeTo("/destinations/{$destinationId}/activate");
    //     $this->assertRequestMethod('POST');
    // }

    // /** @test */
    // public function it_can_deactivate_destination()
    // {
    //     $destinationId = 'dest_test123';

    //     $client = $this->createMockClient([
    //         $this->mockDestinationResponse([
    //             'data' => [
    //                 'uuid' =>  $destinationId,
    //                 'is_active' => false,
    //             ],
    //         ]),
    //     ]);

    //     $response = $client->destinations->deactivate($destinationId);

    //     $this->assertFalse($response->isActive);
    //     $this->assertRequestMadeTo("/destinations/{$destinationId}/deactivate");
    //     $this->assertRequestMethod('POST');
    // }

    /** @test */
    public function it_returns_builder_instance()
    {
        $client = $this->createMockClient([]);

        $builder = $client->destinations->builder();

        $this->assertInstanceOf(\Eventrel\Client\Builders\DestinationBuilder::class, $builder);
    }

    /** @test */
    public function it_handles_validation_errors()
    {
        $this->expectException(EventrelException::class);
        $this->expectExceptionMessage('Validation failed');

        $client = $this->createMockClient([
            $this->mockErrorResponse('Validation failed', 422),
        ]);

        $client->destinations->create(
            name: '',
            webhookUrl: 'invalid-url',
            webhookMode: 'invalid'
        );
    }
}
