# Eventrel PHP Client

[![Latest Version on Packagist](https://img.shields.io/packagist/v/eventrel/php-client.svg?style=flat-square)](https://packagist.org/packages/eventrel/php-client)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/eventrel/php-client/run-tests?label=tests)](https://github.com/eventrel/php-client/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/eventrel/php-client.svg?style=flat-square)](https://packagist.org/packages/eventrel/php-client)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

Official PHP client library for [Eventrel](https://eventrel.sh) - reliable event delivery infrastructure for modern applications.

## Features

-   🚀 **Fluent API** - Clean, expressive syntax for sending events
-   🔑 **Team-Scoped Keys** - API tokens are team-scoped, no need to specify teams
-   ⚡ **Laravel Integration** - Service provider, facade, and configuration
-   🔄 **Retry Logic** - Automatic retries with exponential backoff
-   📅 **Scheduled Delivery** - Send events at specific times
-   🔒 **Idempotency** - Prevent duplicate event processing
-   📊 **Monitoring** - Track delivery status and failure rates

## Installation

Install via Composer:

```bash
composer require eventrel/php-client
```

### Laravel Setup

Publish the configuration file:

```bash
php artisan vendor:publish --tag=eventrel-config
```

Add your API token to `.env`:

```env
EVENTREL_API_TOKEN=your_team_scoped_api_token_here
```

> **Note:** Eventrel API tokens are team-scoped, so you don't need to specify which team to use - it's built into your token!

## Quick Start

### Basic Usage (Framework Agnostic)

```php
use Eventrel\Client\EventrelClient;

$eventrel = new EventrelClient('your_api_token');

// Send a webhook - clean and simple!
$response = $eventrel->event('user.created')
    ->payload([
        'user_id' => 12345,
        'email' => 'john@example.com',
        'name' => 'John Doe'
    ])
    ->send();

echo "Webhook sent with ID: " . $response->getId();
```

### Laravel Usage

```php
use Eventrel\Client\Facades\Eventrel;

class UserController extends Controller
{
    public function store(Request $request)
    {
        $user = User::create($request->validated());

        // Send webhook notification - no team needed!
        Eventrel::event('user.created')
            ->payload($user->toArray())
            ->idempotencyKey("user-created-{$user->id}")
            ->send();

        return response()->json($user, 201);
    }
}
```

## API Reference

### Sending Webhooks

```php
// Basic webhook
$eventrel->event('order.completed')
    ->payload(['order_id' => 123])
    ->send();

// With idempotency protection
$eventrel->event('payment.processed')
    ->payload(['payment_id' => 456])
    ->idempotencyKey('payment-456-processed')
    ->send();

// Scheduled webhook
$eventrel->event('reminder.trial_ending')
    ->payload(['user_id' => 789])
    ->scheduleInHours(24)
    ->send();

// Add data incrementally
$builder = $eventrel->event('order.created');
$builder->with('order_id', $order->id);
$builder->with('customer_email', $order->customer_email);
$builder->with('total_amount', $order->total_amount);
$response = $builder->send();

// Direct method (non-fluent)
$response = $eventrel->sendWebhook('user.updated', [
    'user_id' => 123,
    'changes' => ['email' => 'new@example.com']
]);
```

### Managing Endpoints

```php
// Create an endpoint
$endpoint = $eventrel->createEndpoint(
    name: 'User Service',
    url: 'https://api.myapp.com/webhooks/eventrel',
    events: ['user.created', 'user.updated'],
    retryLimit: 5
);

// List all endpoints
$endpoints = $eventrel->getEndpoints();

// Get specific endpoint
$endpoint = $eventrel->getEndpoint(123);

// Update endpoint
$eventrel->updateEndpoint($endpoint->getId(), [
    'url' => 'https://new-api.myapp.com/webhooks/eventrel'
]);

// Regenerate secret
$newSecret = $eventrel->regenerateEndpointSecret($endpoint->getId());

// Delete endpoint
$eventrel->deleteEndpoint($endpoint->getId());
```

### Retrieving Webhooks

```php
// Get specific webhook
$webhook = $eventrel->getWebhook('webhook_id');

if ($webhook->isSuccessful()) {
    echo "Webhook delivered successfully!";
} elseif ($webhook->isFailed()) {
    echo "Webhook failed: " . $webhook->getLastFailureReason();
}

// List webhooks with pagination
$webhooks = $eventrel->getWebhooks(page: 1);

foreach ($webhooks->getWebhooks() as $webhook) {
    echo $webhook->getEventType() . ": " . $webhook->getStatus() . "\n";
}

// Filter webhooks
$failedWebhooks = $eventrel->getWebhooks(page: 1, filters: [
    'status' => 'failed',
    'event_type' => 'payment.failed'
]);
```

### Team Information

```php
// Get current team info (the team your API key belongs to)
$team = $eventrel->getTeam();
echo "Team: " . $team->getName();
echo "Plan: " . $team->getPlan();
echo "Usage: " . $team->getUsagePercentage() . "%";

// Get detailed usage statistics
$usage = $eventrel->getUsage();
echo "Webhooks sent this month: " . $usage['webhooks_sent'];
echo "Remaining webhooks: " . $usage['webhooks_remaining'];

// Invite team member
$eventrel->inviteMember('colleague@mycompany.com', 'developer');
```

## Advanced Usage

### Error Handling

```php
use Eventrel\Client\EventrelException;

try {
    $response = $eventrel->event('user.created')
        ->payload(['user_id' => 123])
        ->send();

    echo "Success: " . $response->getId();
} catch (EventrelException $e) {
    echo "Error: " . $e->getMessage();

    if ($e->hasResponseData()) {
        var_dump($e->getResponseData());
    }
}
```

### Dependency Injection (Laravel)

```php
use Eventrel\Client\EventrelClient;

class NotificationService
{
    public function __construct(
        private EventrelClient $eventrel
    ) {}

    public function sendOrderNotification(Order $order): void
    {
        $this->eventrel
            ->event('order.completed')
            ->payload([
                'order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'total' => $order->total,
                'items' => $order->items->toArray(),
            ])
            ->idempotencyKey("order-{$order->id}-completed")
            ->send();
    }
}
```

### Batch Operations

```php
// Send multiple webhooks efficiently
$users = User::where('trial_expires_at', now()->addDay())->get();

foreach ($users as $user) {
    $eventrel->event('trial.expiring_soon')
        ->payload([
            'user_id' => $user->id,
            'email' => $user->email,
            'expires_at' => $user->trial_expires_at,
        ])
        ->idempotencyKey("trial-expiring-{$user->id}")
        ->send();
}
```

### Scheduled Webhooks

```php
// Various scheduling options
$eventrel->event('reminder.payment_due')
    ->payload(['invoice_id' => 123])
    ->scheduleIn(3600) // 1 hour in seconds
    ->send();

$eventrel->event('follow_up.trial_ended')
    ->payload(['user_id' => 456])
    ->scheduleInMinutes(30)
    ->send();

$eventrel->event('weekly.digest')
    ->payload(['user_id' => 789])
    ->scheduleAt(Carbon::nextMonday()->setTime(9, 0))
    ->send();
```

## Multi-Team Admin Operations

If you're building a platform that manages multiple Eventrel teams (like if you're an admin), you can still access team-specific operations:

```php
// For platform administrators only
$allTeams = $eventrel->getAllTeams(); // Requires admin permissions

// Work with a specific team (overrides your API key's team)
$specificTeam = $eventrel->forTeam('other-team-slug');
$specificTeam->sendWebhook('admin.notification', ['message' => 'Hello']);

// Create new teams (admin operation)
$newTeam = $eventrel->createTeam('New Company', 'new-company-slug');
```

> **Note:** Multi-team operations require special admin permissions and are typically only used by platform administrators.

## Laravel Facade Reference

```php
use Eventrel\Client\Facades\Eventrel;

// All these work the same as the client methods:
Eventrel::event('user.created')->payload($data)->send();
Eventrel::getWebhooks();
Eventrel::createEndpoint('My Service', 'https://example.com/webhook');
Eventrel::getTeam();
```

## Testing

```bash
composer test
```

## Configuration

The package uses a simple configuration with just your API token:

```php
// config/eventrel.php
return [
    'api_token' => env('EVENTREL_API_TOKEN'),
    'base_url' => env('EVENTREL_BASE_URL', 'https://api.eventrel.sh'),
    'timeout' => env('EVENTREL_TIMEOUT', 30),
];
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security vulnerabilities, please email security@eventrel.sh instead of using the issue tracker.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
