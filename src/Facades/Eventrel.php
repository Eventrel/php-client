<?php

namespace Eventrel\Facades;

use Eventrel\Services\{DestinationService, EventService, IdempotencyService};
use Illuminate\Support\Facades\Facade;

/**
 * Eventrel API Client Facade
 * 
 * @method static string version()
 * @method static \GuzzleHttp\Psr7\Response makeRequest(string $method, string $path, array $options = [])
 * 
 * @property-read EventService $events
 * @property-read DestinationService $destinations
 * @property-read IdempotencyService $idempotency
 * 
 * @see \Eventrel\EventrelClient
 */
class Eventrel extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'eventrel';
    }

    /**
     * Handle dynamic, static calls to the facade
     * 
     * This method intercepts all static method calls to the facade and intelligently
     * routes them to either:
     * 1. Service accessors (if no arguments and property exists) - e.g., events(), idempotency()
     * 2. Direct method calls on the client - e.g., version(), makeRequest()
     * 
     * The logic checks if the method is being called without arguments and corresponds
     * to a property on the underlying EventrelClient instance. If so, it returns the
     * service instance. Otherwise, it forwards the call as a normal method invocation.
     * 
     * This enables a clean API where services are accessed via method calls without
     * requiring explicit service accessor methods in the facade.
     * 
     * @param string $method The name of the method being called statically
     * @param array<int, mixed> $args The arguments passed to the method
     * @return mixed Returns either:
     *               - A service instance (EventService, DestinationService, IdempotencyService)
     *                 when accessing service properties with no arguments
     *               - The result of a direct method call on the EventrelClient
     * 
     * @throws \BadMethodCallException When the method doesn't exist on the client
     * 
     * @example
     * // Service accessor (no args) - returns EventService instance
     * $service = Eventrel::events();
     * 
     * // Then call methods on the service
     * $response = Eventrel::events()->create(...);
     * 
     * // Direct client method - forwards call with arguments
     * $version = Eventrel::version();
     * 
     * // Method with arguments - forwards normally
     * $response = Eventrel::makeRequest('GET', '/events');
     */
    public static function __callStatic($method, $args)
    {
        $instance = static::getFacadeRoot();

        if (empty($args) && isset($instance->$method)) {
            return $instance->$method;
        }

        return $instance->$method(...$args);
    }
}
