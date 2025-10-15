<?php

namespace Eventrel\Tests\Unit;

use Carbon\Carbon;
use Eventrel\Builders\EventBuilder;
use Eventrel\Responses\EventResponse;
use Eventrel\Tests\TestCase;

class EventBuilderTest extends TestCase
{
    /** @test */
    public function it_can_build_and_send_simple_event()
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

        $response = $client->event('user.created')
            ->to('dest_abc123')
            ->with('email', 'test@example.com')
            ->send();

        $this->assertInstanceOf(EventResponse::class, $response);
        $this->assertEquals('user.created', $response->eventType);
        $this->assertRequestBodyContains([
            'event_type' => 'user.created',
            'destination' => 'dest_abc123',
            'payload' => ['email' => 'test@example.com'],
        ]);
    }

    /** @test */
    public function it_can_set_event_type_fluently()
    {
        $client = $this->createMockClient([
            $this->mockEventResponse(),
        ]);

        $builder = new EventBuilder($client);

        $response = $builder
            ->eventType('payment.completed')
            ->to('dest_abc123')
            ->payload(['amount' => 100])
            ->send();

        $this->assertRequestBodyContains([
            'event_type' => 'payment.completed',
        ]);
    }

    /** @test */
    public function it_can_set_destination_using_to()
    {
        $client = $this->createMockClient([
            $this->mockEventResponse(),
        ]);

        $response = $client->event('user.created')
            ->to('dest_abc123')
            ->payload(['test' => 'data'])
            ->send();

        $this->assertRequestBodyContains([
            'destination' => 'dest_abc123',
        ]);
    }

    /** @test */
    public function it_can_add_payload_fields_incrementally()
    {
        $client = $this->createMockClient([
            $this->mockEventResponse(),
        ]);

        $response = $client->event('order.created')
            ->to('dest_abc123')
            ->with('order_id', 12345)
            ->with('customer_email', 'customer@example.com')
            ->with('total_amount', 99.99)
            ->send();

        $body = $this->getLastRequestBody();
        $this->assertEquals(12345, $body['payload']['order_id']);
        $this->assertEquals('customer@example.com', $body['payload']['customer_email']);
        $this->assertEquals(99.99, $body['payload']['total_amount']);
    }

    /** @test */
    public function it_can_set_entire_payload_at_once()
    {
        $client = $this->createMockClient([
            $this->mockEventResponse(),
        ]);

        $payload = [
            'user_id' => 1,
            'email' => 'test@example.com',
            'name' => 'Test User',
        ];

        $response = $client->event('user.created')
            ->to('dest_abc123')
            ->payload($payload)
            ->send();

        $this->assertRequestBodyContains([
            'payload' => $payload,
        ]);
    }

    /** @test */
    public function it_can_merge_payload_data()
    {
        $client = $this->createMockClient([
            $this->mockEventResponse(),
        ]);

        $response = $client->event('user.updated')
            ->to('dest_abc123')
            ->payload(['user_id' => 1, 'email' => 'old@example.com'])
            ->withData(['email' => 'new@example.com', 'verified' => true])
            ->send();

        $body = $this->getLastRequestBody();
        $this->assertEquals(1, $body['payload']['user_id']);
        $this->assertEquals('new@example.com', $body['payload']['email']);
        $this->assertTrue($body['payload']['verified']);
    }

    /** @test */
    public function it_can_add_single_tag()
    {
        $client = $this->createMockClient([
            $this->mockEventResponse(),
        ]);

        $response = $client->event('user.created')
            ->to('dest_abc123')
            ->payload(['test' => 'data'])
            ->tag('premium')
            ->send();

        $body = $this->getLastRequestBody();
        $this->assertContains('premium', $body['tags']);
    }

    /** @test */
    public function it_can_add_multiple_tags()
    {
        $client = $this->createMockClient([
            $this->mockEventResponse(),
        ]);

        $response = $client->event('user.created')
            ->to('dest_abc123')
            ->payload(['test' => 'data'])
            ->tags(['premium', 'verified', 'priority'])
            ->send();

        $body = $this->getLastRequestBody();
        $this->assertContains('premium', $body['tags']);
        $this->assertContains('verified', $body['tags']);
        $this->assertContains('priority', $body['tags']);
    }

    /** @test */
    public function it_can_add_tags_incrementally()
    {
        $client = $this->createMockClient([
            $this->mockEventResponse(),
        ]);

        $response = $client->event('user.created')
            ->to('dest_abc123')
            ->payload(['test' => 'data'])
            ->tag('premium')
            ->tag('verified')
            ->tags(['priority', 'urgent'])
            ->send();

        $body = $this->getLastRequestBody();
        $this->assertCount(4, $body['tags']);
    }

    /** @test */
    public function it_can_set_idempotency_key()
    {
        $client = $this->createMockClient([
            $this->mockEventResponse(),
        ]);

        $response = $client->event('payment.completed')
            ->to('dest_abc123')
            ->payload(['amount' => 100])
            ->idempotent('payment-12345')
            ->send();

        $this->assertRequestBodyContains([
            'idempotency_key' => 'payment-12345',
        ]);
    }

    /** @test */
    public function it_auto_generates_idempotency_key_when_enabled()
    {
        $client = $this->createMockClient([
            $this->mockEventResponse(),
        ]);

        $response = $client->event('payment.completed')
            ->to('dest_abc123')
            ->payload(['amount' => 100])
            ->idempotent()
            ->send();

        $body = $this->getLastRequestBody();
        $this->assertArrayHasKey('idempotency_key', $body);
        $this->assertNotEmpty($body['idempotency_key']);
    }

    /** @test */
    public function it_can_schedule_event_for_future_delivery()
    {
        $client = $this->createMockClient([
            $this->mockEventResponse(),
        ]);

        $scheduledAt = Carbon::parse('2025-12-31 23:59:59');

        $response = $client->event('reminder.scheduled')
            ->to('dest_abc123')
            ->payload(['message' => 'Happy New Year!'])
            ->scheduleFor($scheduledAt)
            ->send();

        $body = $this->getLastRequestBody();
        $this->assertArrayHasKey('scheduled_at', $body);
    }

    /** @test */
    public function it_can_schedule_event_using_helper_methods()
    {
        $client = $this->createMockClient([
            $this->mockEventResponse(),
        ]);

        $response = $client->event('reminder.scheduled')
            ->to('dest_abc123')
            ->payload(['message' => 'Test'])
            ->scheduleIn(30, 'minutes')
            ->send();

        $body = $this->getLastRequestBody();
        $this->assertArrayHasKey('scheduled_at', $body);
    }

    /** @test */
    public function it_can_return_outbound_event_entity()
    {
        $client = $this->createMockClient([
            $this->mockEventResponse(),
        ]);

        $response = $client->event('user.created')
            ->to('dest_abc123')
            ->payload(['test' => 'data'])
            ->asEntity()
            ->send();

        $this->assertInstanceOf(\Eventrel\Entities\OutboundEvent::class, $response);
    }

    /** @test */
    public function it_can_convert_builder_to_array()
    {
        $client = $this->createMockClient([]);

        $builder = $client->event('user.created')
            ->to('dest_abc123')
            ->payload(['email' => 'test@example.com'])
            ->tags(['premium', 'verified'])
            ->idempotent('custom-key');

        $array = $builder->toArray();

        $this->assertEquals('user.created', $array['event_type']);
        $this->assertEquals('dest_abc123', $array['destination']);
        $this->assertEquals(['email' => 'test@example.com'], $array['payload']);
        $this->assertEquals(['premium', 'verified'], $array['tags']);
        $this->assertEquals('custom-key', $array['idempotency_key']);
    }

    /** @test */
    public function it_maintains_fluent_interface_on_all_methods()
    {
        $client = $this->createMockClient([
            $this->mockEventResponse(),
        ]);

        $builder = $client->event('user.created');

        $this->assertInstanceOf(EventBuilder::class, $builder->to('dest_abc123'));
        $this->assertInstanceOf(EventBuilder::class, $builder->payload([]));
        $this->assertInstanceOf(EventBuilder::class, $builder->with('key', 'value'));
        $this->assertInstanceOf(EventBuilder::class, $builder->withData([]));
        $this->assertInstanceOf(EventBuilder::class, $builder->tag('test'));
        $this->assertInstanceOf(EventBuilder::class, $builder->tags([]));
        $this->assertInstanceOf(EventBuilder::class, $builder->idempotent());
        $this->assertInstanceOf(EventBuilder::class, $builder->scheduleFor(Carbon::now()));
    }

    /** @test */
    public function it_throws_exception_when_destination_not_set()
    {
        $this->expectException(\Exception::class);

        $client = $this->createMockClient([
            $this->mockEventResponse(),
        ]);

        $client->event('user.created')
            ->payload(['test' => 'data'])
            ->send(); // No destination set
    }

    /** @test */
    public function it_throws_exception_when_event_type_not_set()
    {
        $this->expectException(\Exception::class);

        $client = $this->createMockClient([
            $this->mockEventResponse(),
        ]);

        $builder = new EventBuilder($client);

        $builder->to('dest_abc123')
            ->payload(['test' => 'data'])
            ->send(); // No event type set
    }
}
