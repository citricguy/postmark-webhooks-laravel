<?php

namespace Citricguy\PostmarkWebhooks;

use Citricguy\PostmarkWebhooks\Http\Controllers\ProcessPostmarkWebhookController;
use Citricguy\PostmarkWebhooks\Http\Middleware\VerifyPostmarkWebhookAuth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class PostmarkWebhooksServiceProvider extends ServiceProvider
{
    public function boot(): void
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
            $webhookPath = config('postmark-webhooks.webhook_path', '/api/postmark/webhook');
            if (! is_string($webhookPath)) {
                throw new \InvalidArgumentException('postmark-webhooks.webhook_path must be a string.');
            }
            Route::post($webhookPath, ProcessPostmarkWebhookController::class)
                ->name('postmark-webhooks.process-webhook');
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function routeConfiguration(): array
    {
        return [
            'middleware' => [
                'api',
                VerifyPostmarkWebhookAuth::class,
            ],
        ];
    }
}
