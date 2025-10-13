<?php

namespace Eventrel\Client\Tests\Unit;

use Eventrel\Client\Builders\EventBuilder;
use Eventrel\Client\Builders\BatchEventBuilder;
use Eventrel\Client\EventrelClient;
use Eventrel\Client\Services\EventService;
use Eventrel\Client\Services\DestinationService;
use Eventrel\Client\Services\IdempotencyService;
use Eventrel\Client\Tests\TestCase;

class EventrelClientTest extends TestCase
{
    /** @test */
    public function it_can_be_instantiated_with_api_token()
    {
        $client = new EventrelClient('test-api-token');

        $this->assertInstanceOf(EventrelClient::class, $client);
    }

    /** @test */
    public function it_can_be_instantiated_with_custom_base_url()
    {
        $client = new EventrelClient(
            apiToken: 'test-api-token',
            baseUrl: 'https://custom-api.eventrel.sh'
        );

        $this->assertInstanceOf(EventrelClient::class, $client);
    }

    /** @test */
    public function it_can_be_instantiated_with_custom_api_version()
    {
        $client = new EventrelClient(
            apiToken: 'test-api-token',
            apiVersion: 'v2'
        );

        $this->assertInstanceOf(EventrelClient::class, $client);
    }

    /** @test */
    public function it_can_be_instantiated_with_custom_timeout()
    {
        $client = new EventrelClient(
            apiToken: 'test-api-token',
            timeout: 60
        );

        $this->assertInstanceOf(EventrelClient::class, $client);
    }

    /** @test */
    public function it_provides_access_to_event_service()
    {
        $client = new EventrelClient('test-api-token');

        $this->assertInstanceOf(EventService::class, $client->events);
    }

    /** @test */
    public function it_provides_access_to_destination_service()
    {
        $client = new EventrelClient('test-api-token');

        $this->assertInstanceOf(DestinationService::class, $client->destinations);
    }

    /** @test */
    public function it_provides_access_to_idempotency_service()
    {
        $client = new EventrelClient('test-api-token');

        $this->assertInstanceOf(IdempotencyService::class, $client->idempotency);
    }

    /** @test */
    public function it_returns_same_service_instance_on_multiple_accesses()
    {
        $client = new EventrelClient('test-api-token');

        $service1 = $client->events;
        $service2 = $client->events;

        $this->assertSame($service1, $service2);
    }

    /** @test */
    public function it_throws_exception_for_nonexistent_service()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Service 'nonexistent' does not exist");

        $client = new EventrelClient('test-api-token');
        $client->nonexistent;
    }

    /** @test */
    public function it_supports_isset_check_for_services()
    {
        $client = new EventrelClient('test-api-token');

        $this->assertTrue(isset($client->events));
        $this->assertTrue(isset($client->destinations));
        $this->assertTrue(isset($client->idempotency));
        $this->assertFalse(isset($client->nonexistent));
    }

    /** @test */
    public function it_can_create_event_builder()
    {
        $client = new EventrelClient('test-api-token');

        $builder = $client->event('user.created');

        $this->assertInstanceOf(EventBuilder::class, $builder);
    }

    /** @test */
    public function it_can_create_batch_event_builder()
    {
        $client = new EventrelClient('test-api-token');

        $builder = $client->eventBatch('user.created');

        $this->assertInstanceOf(BatchEventBuilder::class, $builder);
    }

    /** @test */
    public function it_builds_correct_api_url()
    {
        $client = $this->createMockClient([
            $this->mockEventResponse(),
        ]);

        $client->events->create(
            eventType: 'test.event',
            payload: ['test' => 'data'],
            destination: 'dest_test'
        );

        $uri = $this->getLastRequestUri();
        $this->assertStringContainsString('https://api.test.eventrel.sh', $uri);
        $this->assertStringContainsString('/v1/events', $uri);
    }

    /** @test */
    public function it_includes_authorization_header()
    {
        $client = $this->createMockClient([
            $this->mockEventResponse(),
        ]);

        $client->events->create(
            eventType: 'test.event',
            payload: ['test' => 'data'],
            destination: 'dest_test'
        );

        $request = $this->getLastRequest();
        $authHeader = $request['request']->getHeader('Authorization');

        $this->assertNotEmpty($authHeader);
        $this->assertStringContainsString('Bearer', $authHeader[0]);
    }

    /** @test */
    public function it_includes_content_type_header()
    {
        $client = $this->createMockClient([
            $this->mockEventResponse(),
        ]);

        $client->events->create(
            eventType: 'test.event',
            payload: ['test' => 'data'],
            destination: 'dest_test'
        );

        $request = $this->getLastRequest();
        $contentType = $request['request']->getHeader('Content-Type');

        $this->assertEquals('application/json', $contentType[0]);
    }

    /** @test */
    public function it_includes_accept_header()
    {
        $client = $this->createMockClient([
            $this->mockEventResponse(),
        ]);

        $client->events->create(
            eventType: 'test.event',
            payload: ['test' => 'data'],
            destination: 'dest_test'
        );

        $request = $this->getLastRequest();
        $accept = $request['request']->getHeader('Accept');

        $this->assertEquals('application/json', $accept[0]);
    }

    /** @test */
    public function it_includes_user_agent_header()
    {
        $client = $this->createMockClient([
            $this->mockEventResponse(),
        ]);

        $client->events->create(
            eventType: 'test.event',
            payload: ['test' => 'data'],
            destination: 'dest_test'
        );

        $request = $this->getLastRequest();
        $userAgent = $request['request']->getHeader('User-Agent');

        $this->assertNotEmpty($userAgent);
        $this->assertStringContainsString('eventrel-php-client', $userAgent[0]);
    }

    /** @test */
    public function it_supports_method_chaining_for_fluent_api()
    {
        $client = $this->createMockClient([
            $this->mockEventResponse(),
        ]);

        $response = $client
            ->event('user.created')
            ->to('dest_abc123')
            ->with('email', 'test@example.com')
            ->tag('premium')
            ->send();

        $this->assertNotNull($response);
    }

    /** @test */
    public function it_can_handle_multiple_concurrent_builders()
    {
        $client = $this->createMockClient([
            $this->mockEventResponse(),
            $this->mockEventResponse(),
        ]);

        $builder1 = $client->event('user.created')
            ->to('dest_1')
            ->payload(['user_id' => 1]);

        $builder2 = $client->event('order.created')
            ->to('dest_2')
            ->payload(['order_id' => 1]);

        // Each builder should maintain its own state
        $this->assertEquals('user.created', $builder1->toArray()['event_type']);
        $this->assertEquals('order.created', $builder2->toArray()['event_type']);
    }
}
