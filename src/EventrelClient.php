<?php

namespace Eventrel\Client;

use Carbon\Carbon;
use Eventrel\Client\Exceptions\EventrelException;
use Eventrel\Client\Responses\{WebhookResponse};
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;

class EventrelClient
{
    /**
     * The HTTP client instance.
     * @var Client
     */
    private Client $client;

    /**
     * Create a new Eventrel client instance.
     *
     * @param string $apiToken
     * @param string $baseUrl
     * @param string $apiVersion
     * @param int $timeout
     */
    public function __construct(
        protected string $apiToken,
        protected string $baseUrl = 'https://api.eventrel.sh',
        protected string $apiVersion = 'v1',
        protected int $timeout = 30
    ) {
        $this->client = new Client([
            'base_uri' => $this->buildUri(),
            'headers' => [
                'Authorization' => "Bearer {$apiToken}",
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => 'eventrel-php-client/' . $this->version(),
            ],
            'timeout' => $this->timeout,
        ]);
    }

    // Add your client methods here

    /**
     * Start building a webhook with fluent API
     * Since API keys are team-scoped, no need to specify team
     */
    public function event(string $eventType): WebhookBuilder
    {
        return new WebhookBuilder($this, $eventType);
    }

    /**
     * Send a webhook directly (non-fluent method)
     */
    public function sendWebhook(
        string $eventType,
        array $payload,
        ?string $application = null,
        ?string $idempotencyKey = null,
        ?Carbon $scheduledAt = null
    ): WebhookResponse {
        $data = [
            'event_type' => $eventType,
            'payload' => $payload,
        ];

        if ($application) {
            $data['application'] = $application;
        }

        if ($scheduledAt) {
            $data['scheduled_at'] = $scheduledAt->toISOString();
        }

        $response = $this->makeRequest('POST', 'events', [
            'json' => $data,
            'headers' => [
                'X-Idempotency-Key' => $idempotencyKey ?? $this->generateIdempotencyKey(),
            ]
        ]);

        return new WebhookResponse($response);
    }

    // /**
    //  * Get a specific webhook by ID
    //  */
    // public function getWebhook(string $webhookId): WebhookResponse
    // {
    //     $response = $this->makeRequest('GET', "/api/v1/webhooks/{$webhookId}");
    //     return new WebhookResponse($response['data'] ?? []);
    // }

    // /**
    //  * List webhooks with pagination and filters
    //  */
    // public function getWebhooks(int $page = 1, array $filters = []): WebhookListResponse
    // {
    //     $query = array_merge(['page' => $page], $filters);

    //     $response = $this->makeRequest('GET', '/api/v1/webhooks', [
    //         'query' => $query,
    //     ]);

    //     return new WebhookListResponse(
    //         $response['data'] ?? [],
    //         $response['pagination'] ?? []
    //     );
    // }

    // /**
    //  * Create a new webhook endpoint
    //  */
    // public function createEndpoint(
    //     string $name,
    //     string $url,
    //     ?array $events = null,
    //     ?int $retryLimit = null,
    //     ?array $headers = null
    // ): EndpointResponse {
    //     $data = [
    //         'name' => $name,
    //         'url' => $url,
    //     ];

    //     if ($events !== null) {
    //         $data['events'] = $events;
    //     }

    //     if ($retryLimit !== null) {
    //         $data['retry_limit'] = $retryLimit;
    //     }

    //     if ($headers !== null) {
    //         $data['headers'] = $headers;
    //     }

    //     $response = $this->makeRequest('POST', '/api/v1/endpoints', [
    //         'json' => $data,
    //     ]);

    //     return new EndpointResponse($response['data'] ?? []);
    // }

    // /**
    //  * Get all endpoints for this team
    //  */
    // public function getEndpoints(): array
    // {
    //     $response = $this->makeRequest('GET', '/api/v1/endpoints');

    //     return array_map(
    //         fn($endpoint) => new EndpointResponse($endpoint),
    //         $response['data'] ?? []
    //     );
    // }

    // /**
    //  * Get a specific endpoint
    //  */
    // public function getEndpoint(int $endpointId): EndpointResponse
    // {
    //     $response = $this->makeRequest('GET', "/api/v1/endpoints/{$endpointId}");
    //     return new EndpointResponse($response['data'] ?? []);
    // }

    // /**
    //  * Update an endpoint
    //  */
    // public function updateEndpoint(int $endpointId, array $data): EndpointResponse
    // {
    //     $response = $this->makeRequest('PUT', "/api/v1/endpoints/{$endpointId}", [
    //         'json' => $data,
    //     ]);

    //     return new EndpointResponse($response['data'] ?? []);
    // }

    // /**
    //  * Delete an endpoint
    //  */
    // public function deleteEndpoint(int $endpointId): bool
    // {
    //     $this->makeRequest('DELETE', "/api/v1/endpoints/{$endpointId}");
    //     return true;
    // }

    // /**
    //  * Regenerate endpoint secret
    //  */
    // public function regenerateEndpointSecret(int $endpointId): string
    // {
    //     $response = $this->makeRequest('POST', "/api/v1/endpoints/{$endpointId}/regenerate-secret");
    //     return $response['data']['secret'] ?? '';
    // }

    // /**
    //  * Get current team information (the team this API key belongs to)
    //  */
    // public function getTeam(): TeamResponse
    // {
    //     $response = $this->makeRequest('GET', '/api/v1/team');
    //     return new TeamResponse($response['data'] ?? []);
    // }

    // /**
    //  * Get team usage statistics
    //  */
    // public function getUsage(): array
    // {
    //     $response = $this->makeRequest('GET', '/api/v1/team/usage');
    //     return $response['data'] ?? [];
    // }

    // /**
    //  * Invite a member to the team
    //  */
    // public function inviteMember(string $email, string $role = 'developer'): bool
    // {
    //     $this->makeRequest('POST', '/api/v1/team/invite', [
    //         'json' => [
    //             'email' => $email,
    //             'role' => $role,
    //         ],
    //     ]);

    //     return true;
    // }

    // // ===============================================
    // // ADMIN/MULTI-TEAM OPERATIONS (for platform admins)
    // // ===============================================

    // /**
    //  * For admin users: get all teams they have access to
    //  * Most users won't need this - only for multi-team admin scenarios
    //  */
    // public function getAllTeams(): array
    // {
    //     $response = $this->makeRequest('GET', '/api/v1/admin/teams');

    //     return array_map(
    //         fn($team) => new TeamResponse($team),
    //         $response['data'] ?? []
    //     );
    // }

    // /**
    //  * For admin users: work with a specific team (override API key team)
    //  * Use case: platform administrators managing multiple teams
    //  */
    // public function forTeam(string $teamSlug): TeamClient
    // {
    //     return new TeamClient($this, $teamSlug);
    // }

    // /**
    //  * For admin users: create a new team
    //  */
    // public function createTeam(string $name, ?string $slug = null): TeamResponse
    // {
    //     $data = ['name' => $name];

    //     if ($slug) {
    //         $data['slug'] = $slug;
    //     }

    //     $response = $this->makeRequest('POST', '/api/v1/admin/teams', [
    //         'json' => $data,
    //     ]);

    //     return new TeamResponse($response['data'] ?? []);
    // }

    /**
     * Internal method for making HTTP requests
     *
     * @param string $method
     * @param string $path
     * @param array $options
     * @return ResponseInterface
     */
    public function makeRequest(string $method, string $path, array $options = []): ResponseInterface
    {
        try {
            return $this->client->request($method, $path, $options);
        } catch (RequestException $e) {
            throw new EventrelException(
                message: "Request failed: " . $e->getMessage(),
                code: $e->getCode(),
                previous: $e
            );
        }
    }

    /**
     * Get HTTP client instance for advanced usage
     */
    public function getHttpClient(): Client
    {
        return $this->client;
    }

    /**
     * Get base URL
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Get API token
     */
    public function getApiToken(): string
    {
        return $this->apiToken;
    }


    /**
     * Get the current version of the Eventrel API.
     */
    public function version(): string
    {
        // TODO: Implement version retrieval
        return '1.0.0';
    }

    /**
     * Build the URI for a given API endpoint.
     *
     * @param string $path
     * @return string
     */
    protected function buildUri(string $path = ''): string
    {
        $base = rtrim($this->baseUrl, '/') . '/' . ltrim($this->apiVersion, '/');

        return $base . '/' . ltrim($path, '/');
    }

    /**
     * Generate a unique idempotency key.
     */
    protected function generateIdempotencyKey(): string
    {
        // TODO: Implement idempotency key generation logic

        return bin2hex(random_bytes(16));
    }
}
