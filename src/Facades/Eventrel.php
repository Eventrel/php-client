<?php

namespace Eventrel\Client\Facades;

use Illuminate\Support\Facades\Facade;

// /**
//  * Eventrel facade for Laravel
//  * 
//  * @method static \Eventrel\Client\WebhookBuilder event(string $eventType)
//  * @method static \Eventrel\Client\EventResponse sendWebhook(string $eventType, array $payload, ?string $idempotencyKey = null, ?\Carbon\Carbon $scheduledAt = null)
//  * @method static \Eventrel\Client\EventResponse getWebhook(string $webhookId)
//  * @method static \Eventrel\Client\WebhookListResponse getWebhooks(int $page = 1, array $filters = [])
//  * @method static \Eventrel\Client\EndpointResponse createEndpoint(string $name, string $url, ?array $events = null, ?int $retryLimit = null, ?array $headers = null)
//  * @method static array getEndpoints()
//  * @method static \Eventrel\Client\EndpointResponse getEndpoint(int $endpointId)
//  * @method static \Eventrel\Client\EndpointResponse updateEndpoint(int $endpointId, array $data)
//  * @method static bool deleteEndpoint(int $endpointId)
//  * @method static string regenerateEndpointSecret(int $endpointId)
//  * @method static \Eventrel\Client\TeamResponse getTeam()
//  * @method static array getUsage()
//  * @method static bool inviteMember(string $email, string $role = 'developer')
//  * @method static array getAllTeams()
//  * @method static \Eventrel\Client\TeamClient forTeam(string $teamSlug)
//  * @method static \Eventrel\Client\TeamResponse createTeam(string $name, ?string $slug = null)
//  *
//  * @see \Eventrel\Client\EventrelClient
//  */
class Eventrel extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'eventrel';
    }
}
