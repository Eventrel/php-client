<?php

namespace Eventrel\Client\Tests\Unit;

use Carbon\Carbon;
use Eventrel\Client\Entities\OutboundEvent;
use Eventrel\Client\Exceptions\EventrelException;
use Eventrel\Client\Responses\EventResponse;
use Eventrel\Client\Responses\BatchEventResponse;
use Eventrel\Client\Tests\TestCase;

class EventServiceTest extends TestCase
{
    /** @test */
    public function it_can_create_a_simple_event()
    {
        $client = $this->createMockClient([
            $this->mockEventResponse([
                'data' => [
                    'event_type' => 'user.created',
                    'destination' => 'dest_abc123',
                    'payload' => ['email' => 'test@example.com'],
                ],
            ]),
        ]);

        $response = $client->events->create(
            eventType: 'user.created',
            payload: ['email' => 'test@example.com'],
            destination: 'dest_abc123',
            asOutboundEvent: true
        );

        $this->assertInstanceOf(EventResponse::class, $response);
        $this->assertEquals('user.created', $response->eventType);
        // $this->assertEquals('dest_abc123', $response->destination); //TODO: Fix this assertion
        $this->assertRequestMadeTo('/events');
        $this->assertRequestMethod('POST');
        $this->assertRequestBodyContains([
            'event_type' => 'user.created',
            'destination' => 'dest_abc123',
            'payload' => ['email' => 'test@example.com'],
        ]);
    }

    /** @test */
    public function it_can_create_event_with_tags()
    {
        $client = $this->createMockClient([
            $this->mockEventResponse([
                'data' => [
                    'tags' => ['premium', 'verified'],
                ],
            ]),
        ]);

        $response = $client->events->create(
            eventType: 'user.created',
            payload: ['email' => 'test@example.com'],
            destination: 'dest_abc123',
            tags: ['premium', 'verified']
        );

        $this->assertRequestBodyContains([
            'tags' => ['premium', 'verified'],
        ]);
    }

    /** @test */
    public function it_can_create_event_with_idempotency_key()
    {
        $client = $this->createMockClient([
            $this->mockEventResponse([
                'data' => [
                    'idempotency_key' => 'custom-idem-key',
                ],
            ]),
        ]);

        $response = $client->events->create(
            eventType: 'payment.completed',
            payload: ['amount' => 100],
            destination: 'dest_abc123',
            idempotencyKey: 'custom-idem-key'
        );

        $this->assertEquals('custom-idem-key', $response->idempotencyKey);
        $this->assertRequestBodyContains([
            'idempotency_key' => 'custom-idem-key',
        ]);
    }

    /** @test */
    public function it_can_create_scheduled_event()
    {
        $scheduledAt = Carbon::parse('2025-12-31 23:59:59');

        $client = $this->createMockClient([
            $this->mockEventResponse([
                'data' => [
                    'scheduled_at' => $scheduledAt->toISOString(),
                ],
            ]),
        ]);

        $response = $client->events->create(
            eventType: 'reminder.scheduled',
            payload: ['message' => 'Happy New Year!'],
            destination: 'dest_abc123',
            scheduledAt: $scheduledAt
        );

        $body = $this->getLastRequestBody();
        $this->assertArrayHasKey('scheduled_at', $body);
    }

    /** @test */
    public function it_can_create_event_as_outbound_entity()
    {
        $client = $this->createMockClient([
            $this->mockEventResponse(),
        ]);

        $event = $client->events->create(
            eventType: 'user.created',
            payload: ['email' => 'test@example.com'],
            destination: 'dest_abc123',
            asOutboundEvent: true
        );

        $this->assertInstanceOf(OutboundEvent::class, $event);
    }

    /** @test */
    public function it_can_create_batch_events()
    {
        $client = $this->createMockClient([
            $this->mockBatchEventResponse(3),
        ]);

        $events = [
            ['payload' => ['user_id' => 1], 'tags' => ['premium']],
            ['payload' => ['user_id' => 2], 'tags' => ['trial']],
            ['payload' => ['user_id' => 3]],
        ];

        $response = $client->events->createMany(
            destination: 'dest_abc123',
            eventType: 'user.activity',
            events: $events,
            tags: ['bulk-import']
        );

        $this->assertInstanceOf(BatchEventResponse::class, $response);
        $this->assertEquals(3, $response->getTotalEvents());
        $this->assertRequestMadeTo('/events/batch');
        $this->assertRequestMethod('POST');

        $body = $this->getLastRequestBody();
        $this->assertArrayHasKey('events', $body);
        $this->assertCount(3, $body['events']);
        $this->assertEquals(['bulk-import'], $body['tags']);
    }

    /** @test */
    public function it_can_get_event_by_id()
    {
        $eventId = 'evt_test123';

        $client = $this->createMockClient([
            $this->mockEventResponse([
                'data' => [
                    'uuid' => $eventId,
                ],
            ]),
        ]);

        $response = $client->events->get($eventId, true);

        $this->assertInstanceOf(EventResponse::class, $response);
        $this->assertEquals($eventId, $response->uuid);
        $this->assertRequestMadeTo("/events/{$eventId}");
        $this->assertRequestMethod('GET');
    }

    /** @test */
    public function it_throws_exception_when_event_not_found()
    {
        $this->expectException(EventrelException::class);

        $client = $this->createMockClient([
            $this->mockErrorResponse('Event not found', 404),
        ]);

        $client->events->get('evt_nonexistent');
    }

    /** @test */
    public function it_can_list_events()
    {
        $client = $this->createMockClient([
            [
                'status' => 200,
                'body' => [
                    'data' => [
                        ['uuid' =>  'evt_1', 'event_type' => 'user.created'],
                        ['uuid' =>  'evt_2', 'event_type' => 'user.updated'],
                        ['uuid' =>  'evt_3', 'event_type' => 'user.deleted'],
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

        $response = $client->events->list();

        $this->assertCount(3, $response->get());
        $this->assertEquals(1, $response->getCurrentPage());
        $this->assertEquals(3, $response->getTotal());
        $this->assertRequestMadeTo('/events');
        $this->assertRequestMethod('GET');
    }

    /** @test */
    public function it_can_list_events_with_filters()
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

        $response = $client->events->list(
            page: 2,
            perPage: 50,
            eventType: 'user.created',
            status: 'delivered',
            additionalFilters: [
                'destination' => 'dest_abc123',
                'tags' => ['premium', 'verified']
            ]
        );

        $uri = $this->getLastRequestUri();
        $this->assertStringContainsString('page=2', $uri);
        $this->assertStringContainsString('per_page=50', $uri);
        $this->assertStringContainsString('event_type=user.created', $uri);
        $this->assertStringContainsString('destination=dest_abc123', $uri);
        $this->assertStringContainsString('status=delivered', $uri);
        $this->assertStringContainsString('tags=premium,verified', $uri);
    }

    /** @test */
    public function it_can_retry_single_event()
    {
        $eventId = 'evt_test123';

        $client = $this->createMockClient([
            $this->mockEventResponse([
                'data' => [
                    'uuid' =>  $eventId,
                    'status' => 'pending',
                ],
            ]),
        ]);

        $response = $client->events->retry($eventId);

        $this->assertInstanceOf(EventResponse::class, $response);
        $this->assertEquals('pending', $response->status);
        $this->assertRequestMadeTo("/events/{$eventId}/retry");
        $this->assertRequestMethod('POST');
    }

    /** @test */
    public function it_can_bulk_retry_events()
    {
        $eventIds = ['evt_1', 'evt_2', 'evt_3'];

        $client = $this->createMockClient([
            [
                'status' => 200,
                'body' => [
                    'data' => [
                        'total_retried' => 3,
                        'successful' => 3,
                        'failed' => 0,
                        'event_ids' => $eventIds,
                    ],
                ],
            ],
        ]);

        $response = $client->events->retryMany($eventIds);

        $this->assertEquals(3, $response->getRetriedCount());
        $this->assertEquals(3, $response->getPendingEvents());
        $this->assertEquals(0, $response->getFailedEvents());
        $this->assertRequestMadeTo('/events/bulk-retry');
        $this->assertRequestMethod('POST');
        $this->assertRequestBodyContains([
            'event_ids' => $eventIds,
        ]);
    }

    /** @test */
    public function it_can_cancel_event()
    {
        $eventId = 'evt_test123';

        $client = $this->createMockClient([
            ['status' => 204, 'body' => []],
        ]);

        $result = $client->events->cancel($eventId);

        $this->assertTrue($result);
        $this->assertRequestMadeTo("/events/{$eventId}/cancel");
        $this->assertRequestMethod('POST');
    }

    /** @test */
    public function it_returns_builder_instance()
    {
        $client = $this->createMockClient([]);

        $builder = $client->events->builder();

        $this->assertInstanceOf(\Eventrel\Client\Builders\EventBuilder::class, $builder);
    }

    /** @test */
    public function it_handles_api_errors_gracefully()
    {
        $this->expectException(EventrelException::class);
        $this->expectExceptionMessage('Validation failed');

        $client = $this->createMockClient([
            $this->mockErrorResponse('Validation failed', 422),
        ]);

        $client->events->create(
            eventType: '',
            payload: [],
            destination: ''
        );
    }

    /** @test */
    public function it_handles_network_errors()
    {
        $this->expectException(EventrelException::class);

        $client = $this->createMockClient([
            $this->mockErrorResponse('Network error', 500),
        ]);

        $client->events->create(
            eventType: 'test.event',
            payload: ['test' => 'data'],
            destination: 'dest_test'
        );
    }
}
