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
        $this->mergeConfigFrom(__DIR__ . '/../config/eventrel.php', 'eventrel');

        $this->app->singleton(EventrelClient::class, function ($app) {
            // dd('Binding EventrelClient', $app['config']['eventrel']); // Debugging line to confirm binding

            // $config = $app['config']['eventrel'];
            // $config['api_token'];

            return new EventrelClient();
        });

        $this->app->alias(EventrelClient::class, 'eventrel');
    }

    /**
     * Bootstrap any services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/eventrel.php' => config_path('eventrel.php'),
        ], 'eventrel-config');

        // if (method_exists($this->app, 'configure')) {
        //     $this->app->configure('eventrel');
        // }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [EventrelClient::class, 'eventrel'];
    }
}
