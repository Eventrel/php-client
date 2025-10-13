<?php

namespace Eventrel\Client\Tests\Feature;

use Carbon\Carbon;
use Eventrel\Client\Tests\TestCase;

/**
 * Integration tests for complete workflows
 * 
 * These tests validate end-to-end scenarios that users
 * would encounter in real-world usage.
 */
class IntegrationTest extends TestCase
{
    /** @test */
    public function it_can_complete_simple_event_workflow()
    {
        $client = $this->createMockClient([
            $this->mockEventResponse([
                'data' => [
                    'uuid' =>  'evt_test123',
                    'event_type' => 'user.created',
                    'status' => 'pending',
                ],
            ]),
        ]);

        // Send an event using fluent API
        $response = $client->event('user.created')
            ->to('dest_abc123')
            ->with('user_id', 1)
            ->with('email', 'test@example.com')
            ->with('name', 'Test User')
            ->tag('signup')
            ->tag('premium')
            ->send();

        $this->assertEquals('evt_test123', $response->id);
        $this->assertEquals('user.created', $response->eventType);
        $this->assertEquals('pending', $response->status);
    }

    /** @test */
    public function it_can_complete_batch_event_workflow()
    {
        $client = $this->createMockClient([
            $this->mockBatchEventResponse(5),
        ]);

        // Create batch of related events
        $response = $client->eventBatch('user.import')
            ->to('dest_abc123')
            ->tags(['bulk-import', 'production'])
            ->add(['user_id' => 1, 'email' => 'user1@example.com'], ['premium'])
            ->add(['user_id' => 2, 'email' => 'user2@example.com'], ['trial'])
            ->add(['user_id' => 3, 'email' => 'user3@example.com'])
            ->add(['user_id' => 4, 'email' => 'user4@example.com'], ['enterprise'])
            ->add(['user_id' => 5, 'email' => 'user5@example.com'])
            ->idempotencyKey('import-batch-12345')
            ->send();

        $this->assertEquals(5, $response->totalEvents);
        $this->assertCount(5, $response->events);
    }

    /** @test */
    public function it_can_complete_scheduled_event_workflow()
    {
        $client = $this->createMockClient([
            $this->mockEventResponse([
                'data' => [
                    'status' => 'scheduled',
                    'scheduled_at' => Carbon::parse('2025-12-31 23:59:59')->toISOString(),
                ],
            ]),
        ]);

        // Schedule event for future delivery
        $response = $client->event('reminder.new_year')
            ->to('dest_abc123')
            ->payload([
                'message' => 'Happy New Year!',
                'recipient_email' => 'user@example.com',
            ])
            ->scheduleAt(Carbon::parse('2025-12-31 23:59:59'))
            ->send();

        $this->assertEquals('scheduled', $response->status);
        $this->assertNotNull($response->scheduledAt);
    }

    /** @test */
    public function it_can_complete_idempotent_event_workflow()
    {
        $client = $this->createMockClient([
            $this->mockEventResponse([
                'data' => [
                    'idempotency_key' => 'payment-12345',
                ],
            ]),
            $this->mockEventResponse([
                'data' => [
                    'idempotency_key' => 'payment-12345',
                    'status' => 'duplicate',
                ],
            ]),
        ]);

        // Send payment event with idempotency key
        $response1 = $client->event('payment.completed')
            ->to('dest_abc123')
            ->payload([
                'payment_id' => 12345,
                'amount' => 99.99,
                'currency' => 'USD',
            ])
            ->idempotencyKey('payment-12345')
            ->send();

        // Attempt to send duplicate (simulated)
        $response2 = $client->event('payment.completed')
            ->to('dest_abc123')
            ->payload([
                'payment_id' => 12345,
                'amount' => 99.99,
                'currency' => 'USD',
            ])
            ->idempotencyKey('payment-12345')
            ->send();

        $this->assertEquals('payment-12345', $response1->idempotencyKey);
        $this->assertEquals('payment-12345', $response2->idempotencyKey);
    }

    /** @test */
    public function it_can_complete_destination_creation_workflow()
    {
        $client = $this->createMockClient([
            $this->mockDestinationResponse([
                'data' => [
                    'uuid' =>  'dest_test123',
                    'name' => 'Production API',
                    'webhook_url' => 'https://api.example.com/webhook',
                    'is_active' => true,
                ],
            ]),
        ]);

        // Create comprehensive destination
        $response = $client->destinations->builder()
            ->name('Production API')
            ->webhookUrl('https://api.example.com/webhook')
            ->bidirectional()
            ->withDescription('Main production webhook endpoint')
            ->withHeader('X-API-Key', 'secret-key')
            ->withHeader('X-Environment', 'production')
            ->withMetadata(['team' => 'engineering', 'priority' => 'high'])
            ->withTimeout(45)
            ->withRetryLimit(5)
            ->withRateLimit(perMinute: 1000, perHour: 50000)
            ->withBatching(size: 50, strategy: 'batched')
            ->withEventFiltering(['user.created', 'user.updated', 'order.completed'])
            ->verifySsl()
            ->create();

        $this->assertEquals('dest_test123', $response->id);
        $this->assertTrue($response->isActive);
    }

    /** @test */
    public function it_can_complete_event_retry_workflow()
    {
        $client = $this->createMockClient([
            // Original failed event
            $this->mockEventResponse([
                'data' => [
                    'uuid' =>  'evt_test123',
                    'status' => 'failed',
                ],
            ]),
            // Get event to check status
            $this->mockEventResponse([
                'data' => [
                    'uuid' =>  'evt_test123',
                    'status' => 'failed',
                ],
            ]),
            // Retry event
            $this->mockEventResponse([
                'data' => [
                    'uuid' =>  'evt_test123',
                    'status' => 'pending',
                ],
            ]),
        ]);

        // Simulate failed event
        $event = $client->events->create(
            eventType: 'payment.failed',
            payload: ['payment_id' => 12345],
            destination: 'dest_abc123'
        );

        // Check event status
        $eventStatus = $client->events->get($event->id);
        $this->assertEquals('failed', $eventStatus->status);

        // Retry the event
        $retried = $client->events->retry($event->id);
        $this->assertEquals('pending', $retried->status);
    }

    /** @test */
    public function it_can_complete_bulk_retry_workflow()
    {
        $client = $this->createMockClient([
            [
                'status' => 200,
                'body' => [
                    'data' => [
                        'total_retried' => 3,
                        'successful' => 3,
                        'failed' => 0,
                        'event_ids' => ['evt_1', 'evt_2', 'evt_3'],
                    ],
                ],
            ],
        ]);

        // Retry multiple failed events at once
        $result = $client->events->retryMany([
            'evt_1',
            'evt_2',
            'evt_3',
        ]);

        $this->assertEquals(3, $result->totalRetried);
        $this->assertEquals(3, $result->successful);
        $this->assertEquals(0, $result->failed);
    }

    /** @test */
    public function it_can_complete_event_cancellation_workflow()
    {
        $client = $this->createMockClient([
            // Create scheduled event
            $this->mockEventResponse([
                'data' => [
                    'uuid' =>  'evt_test123',
                    'status' => 'scheduled',
                    'scheduled_at' => Carbon::parse('2025-12-31 23:59:59')->toISOString(),
                ],
            ]),
            // Cancel the event
            ['status' => 204, 'body' => []],
        ]);

        // Create scheduled event
        $event = $client->event('reminder.scheduled')
            ->to('dest_abc123')
            ->payload(['message' => 'Test'])
            ->scheduleAt(Carbon::parse('2025-12-31 23:59:59'))
            ->send();

        $this->assertEquals('scheduled', $event->status);

        // Cancel it before it's sent
        $cancelled = $client->events->cancel($event->id);
        $this->assertTrue($cancelled);
    }

    // /** @test */
    // public function it_can_complete_destination_management_workflow()
    // {
    //     $client = $this->createMockClient([
    //         // Create destination
    //         $this->mockDestinationResponse([
    //             'data' => [
    //                 'uuid' =>  'dest_test123',
    //                 'name' => 'Test Destination',
    //                 'is_active' => true,
    //             ],
    //         ]),
    //         // Deactivate destination
    //         $this->mockDestinationResponse([
    //             'data' => [
    //                 'uuid' =>  'dest_test123',
    //                 'is_active' => false,
    //             ],
    //         ]),
    //         // Update destination
    //         $this->mockDestinationResponse([
    //             'data' => [
    //                 'uuid' =>  'dest_test123',
    //                 'name' => 'Updated Destination',
    //                 'timeout' => 60,
    //                 'is_active' => false,
    //             ],
    //         ]),
    //         // Activate destination
    //         $this->mockDestinationResponse([
    //             'data' => [
    //                 'uuid' =>  'dest_test123',
    //                 'is_active' => true,
    //             ],
    //         ]),
    //         // Delete destination
    //         ['status' => 204, 'body' => []],
    //     ]);

    //     // 1. Create destination
    //     $destination = $client->destinations->create(
    //         name: 'Test Destination',
    //         webhookUrl: 'https://example.com/webhook',
    //         webhookMode: 'outbound'
    //     );
    //     $this->assertTrue($destination->isActive);

    //     // 2. Deactivate it
    //     $deactivated = $client->destinations->deactivate($destination->id);
    //     $this->assertFalse($deactivated->isActive);

    //     // 3. Update configuration
    //     $updated = $client->destinations->update(
    //         id: $destination->id,
    //         name: 'Updated Destination',
    //         timeout: 60
    //     );
    //     $this->assertEquals('Updated Destination', $updated->name);

    //     // 4. Reactivate it
    //     $activated = $client->destinations->activate($destination->id);
    //     $this->assertTrue($activated->isActive);

    //     // 5. Delete it
    //     $deleted = $client->destinations->delete($destination->id);
    //     $this->assertTrue($deleted);
    // }

    // /** @test */
    // public function it_can_complete_pagination_workflow()
    // {
    //     $client = $this->createMockClient([
    //         // Page 1
    //         [
    //             'status' => 200,
    //             'body' => [
    //                 'data' => array_map(fn($i) => [
    //                     'uuid' =>  "evt_{$i}",
    //                     'event_type' => 'user.created',
    //                 ], range(1, 20)),
    //                 'pagination' => [
    //                     'current_page' => 1,
    //                     'per_page' => 20,
    //                     'total' => 45,
    //                     'last_page' => 3,
    //                 ],
    //             ],
    //         ],
    //         // Page 2
    //         [
    //             'status' => 200,
    //             'body' => [
    //                 'data' => array_map(fn($i) => [
    //                     'uuid' =>  "evt_{$i}",
    //                     'event_type' => 'user.created',
    //                 ], range(21, 40)),
    //                 'pagination' => [
    //                     'current_page' => 2,
    //                     'per_page' => 20,
    //                     'total' => 45,
    //                     'last_page' => 3,
    //                 ],
    //             ],
    //         ],
    //         // Page 3
    //         [
    //             'status' => 200,
    //             'body' => [
    //                 'data' => array_map(fn($i) => [
    //                     'uuid' =>  "evt_{$i}",
    //                     'event_type' => 'user.created',
    //                 ], range(41, 45)),
    //                 'pagination' => [
    //                     'current_page' => 3,
    //                     'per_page' => 20,
    //                     'total' => 45,
    //                     'last_page' => 3,
    //                 ],
    //             ],
    //         ],
    //     ]);

    //     // Paginate through all events
    //     $allEvents = [];

    //     $page1 = $client->events->list(page: 1, perPage: 20);
    //     $allEvents = array_merge($allEvents, $page1->events);

    //     $page2 = $client->events->list(page: 2, perPage: 20);
    //     $allEvents = array_merge($allEvents, $page2->events);

    //     $page3 = $client->events->list(page: 3, perPage: 20);
    //     $allEvents = array_merge($allEvents, $page3->events);

    //     $this->assertCount(45, $allEvents);
    // }
}
