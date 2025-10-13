<?php

namespace Eventrel\Client\Tests\Unit;

use Carbon\Carbon;
use Eventrel\Client\Builders\BatchEventBuilder;
use Eventrel\Client\Responses\BatchEventResponse;
use Eventrel\Client\Tests\TestCase;

class BatchEventBuilderTest extends TestCase
{
    /** @test */
    public function it_can_build_and_send_batch_events()
    {
        $client = $this->createMockClient([
            $this->mockBatchEventResponse(3),
        ]);

        $response = $client->eventBatch('user.created')
            ->to('dest_abc123')
            ->add(['user_id' => 1, 'email' => 'user1@example.com'])
            ->add(['user_id' => 2, 'email' => 'user2@example.com'])
            ->add(['user_id' => 3, 'email' => 'user3@example.com'])
            ->send();

        $this->assertInstanceOf(BatchEventResponse::class, $response);
        $this->assertEquals(3, $response->totalEvents);
        $this->assertRequestMadeTo('/events/batch');
        $this->assertRequestMethod('POST');
    }

    /** @test */
    public function it_can_add_events_with_individual_tags()
    {
        $client = $this->createMockClient([
            $this->mockBatchEventResponse(3),
        ]);

        $response = $client->eventBatch('user.activity')
            ->to('dest_abc123')
            ->add(['action' => 'login'], ['premium'])
            ->add(['action' => 'view_page'], ['trial'])
            ->add(['action' => 'logout'])
            ->send();

        $body = $this->getLastRequestBody();
        $this->assertCount(3, $body['events']);
        $this->assertEquals(['premium'], $body['events'][0]['tags']);
        $this->assertEquals(['trial'], $body['events'][1]['tags']);
        $this->assertArrayNotHasKey('tags', $body['events'][2]);
    }

    /** @test */
    public function it_can_set_batch_level_tags()
    {
        $client = $this->createMockClient([
            $this->mockBatchEventResponse(2),
        ]);

        $response = $client->eventBatch('user.created')
            ->to('dest_abc123')
            ->tags(['bulk-import', 'production'])
            ->add(['user_id' => 1])
            ->add(['user_id' => 2])
            ->send();

        $body = $this->getLastRequestBody();
        $this->assertEquals(['bulk-import', 'production'], $body['tags']);
    }

    /** @test */
    public function it_can_set_all_events_at_once()
    {
        $client = $this->createMockClient([
            $this->mockBatchEventResponse(3),
        ]);

        $events = [
            ['payload' => ['user_id' => 1], 'tags' => ['premium']],
            ['payload' => ['user_id' => 2], 'tags' => ['trial']],
            ['payload' => ['user_id' => 3]],
        ];

        $response = $client->eventBatch('user.created')
            ->to('dest_abc123')
            ->events($events)
            ->send();

        $body = $this->getLastRequestBody();
        $this->assertCount(3, $body['events']);
        $this->assertEquals($events, $body['events']);
    }

    /** @test */
    public function it_can_get_events_array()
    {
        $client = $this->createMockClient([]);

        $builder = $client->eventBatch('user.created')
            ->add(['user_id' => 1])
            ->add(['user_id' => 2])
            ->add(['user_id' => 3]);

        $events = $builder->getEvents();

        $this->assertCount(3, $events);
        $this->assertEquals(['user_id' => 1], $events[0]['payload']);
        $this->assertEquals(['user_id' => 2], $events[1]['payload']);
        $this->assertEquals(['user_id' => 3], $events[2]['payload']);
    }

    /** @test */
    public function it_can_get_event_type()
    {
        $client = $this->createMockClient([]);

        $builder = $client->eventBatch('payment.completed');

        $this->assertEquals('payment.completed', $builder->getEventType());
    }

    /** @test */
    public function it_can_count_events()
    {
        $client = $this->createMockClient([]);

        $builder = $client->eventBatch('user.created')
            ->add(['user_id' => 1])
            ->add(['user_id' => 2])
            ->add(['user_id' => 3])
            ->add(['user_id' => 4])
            ->add(['user_id' => 5]);

        $this->assertEquals(5, $builder->count());
    }

    /** @test */
    public function it_can_schedule_batch_events()
    {
        $client = $this->createMockClient([
            $this->mockBatchEventResponse(2),
        ]);

        $scheduledAt = Carbon::parse('2025-12-31 23:59:59');

        $response = $client->eventBatch('reminder.scheduled')
            ->to('dest_abc123')
            ->add(['message' => 'Happy New Year!'])
            ->add(['message' => 'Party time!'])
            ->scheduleAt($scheduledAt)
            ->send();

        $body = $this->getLastRequestBody();
        $this->assertArrayHasKey('scheduled_at', $body);
    }

    /** @test */
    public function it_can_set_idempotency_key_for_batch()
    {
        $client = $this->createMockClient([
            $this->mockBatchEventResponse(2),
        ]);

        $response = $client->eventBatch('user.created')
            ->to('dest_abc123')
            ->add(['user_id' => 1])
            ->add(['user_id' => 2])
            ->idempotencyKey('batch-import-12345')
            ->send();

        $body = $this->getLastRequestBody();
        $this->assertEquals('batch-import-12345', $body['idempotency_key']);
    }

    /** @test */
    public function it_auto_generates_idempotency_key_for_batch()
    {
        $client = $this->createMockClient([
            $this->mockBatchEventResponse(2),
        ]);

        $response = $client->eventBatch('user.created')
            ->to('dest_abc123')
            ->add(['user_id' => 1])
            ->add(['user_id' => 2])
            ->idempotencyKey()
            ->send();

        $body = $this->getLastRequestBody();
        $this->assertArrayHasKey('idempotency_key', $body);
        $this->assertNotEmpty($body['idempotency_key']);
    }

    /** @test */
    public function it_can_convert_batch_to_array()
    {
        $client = $this->createMockClient([]);

        $scheduledAt = Carbon::parse('2025-12-31 23:59:59');

        $builder = $client->eventBatch('user.created')
            ->to('dest_abc123')
            ->tags(['bulk-import'])
            ->add(['user_id' => 1], ['premium'])
            ->add(['user_id' => 2])
            ->idempotencyKey('test-key')
            ->scheduleAt($scheduledAt);

        $array = $builder->toArray();

        $this->assertEquals('user.created', $array['event_type']);
        $this->assertEquals('dest_abc123', $array['destination']);
        $this->assertEquals(['bulk-import'], $array['tags']);
        $this->assertCount(2, $array['events']);
        $this->assertEquals('test-key', $array['idempotency_key']);
        $this->assertNotNull($array['scheduled_at']);
    }

    /** @test */
    public function it_maintains_fluent_interface_on_all_methods()
    {
        $client = $this->createMockClient([]);

        $builder = $client->eventBatch('user.created');

        $this->assertInstanceOf(BatchEventBuilder::class, $builder->to('dest_abc123'));
        $this->assertInstanceOf(BatchEventBuilder::class, $builder->tags([]));
        $this->assertInstanceOf(BatchEventBuilder::class, $builder->add([]));
        $this->assertInstanceOf(BatchEventBuilder::class, $builder->events([]));
        $this->assertInstanceOf(BatchEventBuilder::class, $builder->idempotencyKey());
        $this->assertInstanceOf(BatchEventBuilder::class, $builder->scheduleAt(Carbon::now()));
    }

    /** @test */
    public function it_throws_exception_when_destination_not_set()
    {
        $this->expectException(\Exception::class);

        $client = $this->createMockClient([
            $this->mockBatchEventResponse(1),
        ]);

        $client->eventBatch('user.created')
            ->add(['user_id' => 1])
            ->send(); // No destination set
    }

    /** @test */
    public function it_throws_exception_when_no_events_added()
    {
        $this->expectException(\Exception::class);

        $client = $this->createMockClient([
            $this->mockBatchEventResponse(0),
        ]);

        $client->eventBatch('user.created')
            ->to('dest_abc123')
            ->send(); // No events added
    }

    /** @test */
    public function it_can_chain_multiple_operations()
    {
        $client = $this->createMockClient([
            $this->mockBatchEventResponse(5),
        ]);

        $response = $client->eventBatch('user.activity')
            ->to('dest_abc123')
            ->tags(['production', 'analytics'])
            ->add(['action' => 'login', 'user_id' => 1], ['premium'])
            ->add(['action' => 'view_page', 'user_id' => 1])
            ->add(['action' => 'add_to_cart', 'user_id' => 1])
            ->add(['action' => 'checkout', 'user_id' => 1], ['high-value'])
            ->add(['action' => 'logout', 'user_id' => 1])
            ->idempotencyKey('session-tracking-12345')
            ->send();

        $this->assertInstanceOf(BatchEventResponse::class, $response);
        $this->assertEquals(5, $response->totalEvents);

        $body = $this->getLastRequestBody();
        $this->assertCount(5, $body['events']);
        $this->assertEquals(['production', 'analytics'], $body['tags']);
        $this->assertEquals('session-tracking-12345', $body['idempotency_key']);
    }

    /** @test */
    public function it_handles_large_batches()
    {
        $client = $this->createMockClient([
            $this->mockBatchEventResponse(100),
        ]);

        $builder = $client->eventBatch('user.import')
            ->to('dest_abc123')
            ->tags(['bulk-import']);

        // Add 100 events
        for ($i = 1; $i <= 100; $i++) {
            $builder->add([
                'user_id' => $i,
                'email' => "user{$i}@example.com",
            ]);
        }

        $response = $builder->send();

        $this->assertEquals(100, $response->totalEvents);
        $this->assertEquals(100, $builder->count());
    }
}
