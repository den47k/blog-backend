<?php

namespace App\Providers;

use App\Broadcasting\CentrifugoBroadcaster;
use App\Services\Centrifugo\CentrifugoClient;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CentrifugoClient::class, function ($app) {
            $config = $app['config']->get('centrifugo');

            return new CentrifugoClient(
                url: $config['url'],
                apiKey: $config['api_key'] ?? null,
                tokenHmacSecret: (string) ($config['token_hmac_secret'] ?? ''),
                connectionTokenTtl: (int) ($config['connection_token_ttl'] ?? 3600),
                subscriptionTokenTtl: (int) ($config['subscription_token_ttl'] ?? 900),
                httpTimeout: (int) ($config['http_timeout'] ?? 3),
            );
        });
    }

    public function boot(): void
    {
        Broadcast::extend(
            'centrifugo',
            fn($app) =>
            new CentrifugoBroadcaster(
                $app->make(CentrifugoClient::class),
                $app['config']->get('centrifugo.namespaces', []),
            )
        );

        require base_path('routes/channels.php');
    }
}
