<?php

namespace Citricguy\PostmarkWebhooks;

use Citricguy\PostmarkWebhooks\Http\Controllers\ProcessPostmarkWebhookController;
use Citricguy\PostmarkWebhooks\Http\Middleware\VerifyPostmarkWebhookIPAddresses;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class PostmarkWebhooksServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->registerRoutes();

        $this->publishes([
            __DIR__.'/config/postmark-webhooks.php' => config_path('postmark-webhooks.php'),
        ], 'config');

    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/config/postmark-webhooks.php', 'postmark-webhooks');
    }

    private function registerRoutes(): void
    {
        Route::group($this->routeConfiguration(), function () {
            Route::post(config('postmark-webhooks.webhook_path'), ProcessPostmarkWebhookController::class);
        });
    }

    private function routeConfiguration(): array
    {
        return [
            'middleware' => [
                'api',
                VerifyPostmarkWebhookIPAddresses::class,
            ],
        ];
    }
}