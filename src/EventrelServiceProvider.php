<?php

namespace Eventrel\Client;

use Illuminate\Support\ServiceProvider;

class EventrelServiceProvider extends ServiceProvider
{
    /**
     * Register any services.
     */
    public function register(): void
    {
        // // Merge config
        // $this->mergeConfigFrom(__DIR__ . '/../config/eventrel.php', 'eventrel');

        // // Bind the main client - much simpler now!
        // $this->app->singleton(EventrelClient::class, function ($app) {
        //     $config = $app['config']['eventrel'];

        //     return new EventrelClient(
        //         $config['api_token'],
        //         $config['base_url'] ?? 'https://api.eventrel.sh'
        //     );
        // });

        // // Alias for easier access
        // $this->app->alias(EventrelClient::class, 'eventrel');
    }

    /**
     * Bootstrap any services.
     */
    public function boot(): void
    {
        // // Publish config file
        // $this->publishes([
        //     __DIR__ . '/../config/eventrel.php' => config_path('eventrel.php'),
        // ], 'eventrel-config');

        // // Laravel 11+ callback style registration
        // if (method_exists($this->app, 'configure')) {
        //     $this->app->configure('eventrel');
        // }
    }

    // public function provides(): array
    // {
    //     return [EventrelClient::class, 'eventrel'];
    // }
}
