<?php

namespace Eventrel\Client\Tests\Unit;

use Eventrel\Client\EventrelClient;
use Eventrel\Client\Tests\TestCase;

class IdempotencyServiceTest extends TestCase
{
    /** @test */
    public function it_can_generate_unique_idempotency_key()
    {
        $client = new EventrelClient('test-token');

        $key = $client->idempotency->generate();

        $this->assertNotEmpty($key);
        $this->assertIsString($key);
    }

    /** @test */
    public function it_generates_different_keys_each_time()
    {
        $client = new EventrelClient('test-token');

        $key1 = $client->idempotency->generate();
        $key2 = $client->idempotency->generate();
        $key3 = $client->idempotency->generate();

        $this->assertNotEquals($key1, $key2);
        $this->assertNotEquals($key2, $key3);
        $this->assertNotEquals($key1, $key3);
    }

    /** @test */
    public function it_can_generate_key_with_prefix()
    {
        $client = new EventrelClient('test-token');

        $key = $client->idempotency->generate('payment');

        $this->assertStringStartsWith('payment', $key);
    }

    /** @test */
    public function it_can_generate_key_from_data()
    {
        $client = new EventrelClient('test-token');

        $data = [
            'user_id' => 123,
            'email' => 'test@example.com',
            'timestamp' => '2025-01-15T10:00:00Z',
        ];

        $key1 = $client->idempotency->generateContextual($data);
        $key2 = $client->idempotency->generateContextual($data);

        // Same data should produce same key
        $this->assertEquals($key1, $key2);
        $this->assertNotEmpty($key1);
    }

    /** @test */
    public function it_generates_different_keys_for_different_data()
    {
        $client = new EventrelClient('test-token');

        $data1 = ['user_id' => 123, 'action' => 'login'];
        $data2 = ['user_id' => 456, 'action' => 'login'];

        $key1 = $client->idempotency->generateTimeBound($data1, 'login');
        $key2 = $client->idempotency->generateTimeBound($data2, 'login');

        $this->assertNotEquals($key1, $key2);
    }

    /** @test */
    public function it_validates_idempotency_key_format()
    {
        $client = new EventrelClient('test-token');

        $validKey = $client->idempotency->generate();

        $this->assertTrue($client->idempotency->isValid($validKey));
    }

    /** @test */
    public function it_rejects_invalid_idempotency_keys()
    {
        $client = new EventrelClient('test-token');

        $this->assertFalse($client->idempotency->isValid(''));
        $this->assertFalse($client->idempotency->isValid('abc'));
        $this->assertFalse($client->idempotency->isValid('spaces not allowed'));
    }

    /** @test */
    public function it_accepts_custom_format_keys()
    {
        $client = new EventrelClient('test-token');

        $customKeys = [
            'payment-12345',
            'order_abc123',
            'user.created.123',
            'idem_key_test',
        ];

        foreach ($customKeys as $key) {
            $this->assertTrue($client->idempotency->isValid($key));
        }
    }

    /** @test */
    public function generated_keys_have_consistent_format()
    {
        $client = new EventrelClient('test-token');

        $keys = array_map(
            fn() => $client->idempotency->generate(),
            range(1, 10)
        );

        foreach ($keys as $key) {
            $this->assertMatchesRegularExpression('/^[a-zA-Z0-9_-]+$/', $key);
            $this->assertGreaterThan(20, strlen($key));
        }
    }

    /** @test */
    public function it_handles_complex_data_structures()
    {
        $client = new EventrelClient('test-token');

        $complexData = [
            'user' => [
                'uuid' =>  123,
                'profile' => [
                    'name' => 'Test User',
                    'email' => 'test@example.com',
                ],
            ],
            'metadata' => [
                'source' => 'api',
                'version' => 'v2',
            ],
            'items' => [
                ['uuid' =>  1, 'qty' => 2],
                ['uuid' =>  2, 'qty' => 1],
            ],
        ];

        $key = $client->idempotency->generateContextual($complexData);

        $this->assertNotEmpty($key);
        $this->assertTrue($client->idempotency->isValid($key));
    }
}
