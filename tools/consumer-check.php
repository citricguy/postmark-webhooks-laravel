<?php

declare(strict_types=1);

use Citricguy\PostmarkWebhooks\Events\PostmarkWebhookReceived;
use Citricguy\PostmarkWebhooks\PostmarkWebhooksServiceProvider;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

$directory = $argv[1] ?? '';
$matrixRoot = realpath(dirname(__DIR__).'/.build/matrix');
$resolved = realpath($directory);
if ($matrixRoot === false || $resolved === false || ! str_starts_with($resolved, $matrixRoot.DIRECTORY_SEPARATOR)) {
    throw new InvalidArgumentException('Expected a generated .build/matrix consumer directory.');
}
$directory = $resolved;
require $directory.'/vendor/autoload.php';

foreach (['bootstrap/cache', 'config', 'storage/logs', 'storage/framework/views'] as $path) {
    if (! is_dir($directory.'/'.$path)) {
        mkdir($directory.'/'.$path, 0777, true);
    }
}
file_put_contents($directory.'/bootstrap/app.php', <<<'BOOTSTRAP'
<?php
return Illuminate\Foundation\Application::configure(basePath: dirname(__DIR__))
    ->withRouting(using: static function (): void {})
    ->withMiddleware()
    ->create();
BOOTSTRAP);

// Simulate a previously published config that has no new success_status key.
file_put_contents($directory.'/config/postmark-webhooks.php', <<<'CONFIG'
<?php
return [
    'webhook_path' => '/consumer/postmark',
    'firewall_enabled' => false,
    'auth_user' => null,
    'auth_pass' => null,
];
CONFIG);
$_ENV['APP_ENV'] = 'testing';
$_ENV['APP_KEY'] = 'base64:'.base64_encode(str_repeat('x', 32));

/** @return Application */
$boot = static function () use ($directory): Application {
    $application = require $directory.'/bootstrap/app.php';
    if (! $application instanceof Application) {
        throw new RuntimeException('Expected a Laravel application.');
    }
    $application->make(ConsoleKernel::class)->bootstrap();

    return $application;
};

/** @param Application $application */
$check = static function (Application $application, bool $cached): void {
    if (! $application->getProvider(PostmarkWebhooksServiceProvider::class)) {
        throw new RuntimeException('Automatic Composer package discovery failed.');
    }
    if ($application->configurationIsCached() !== $cached || $application->routesAreCached() !== $cached) {
        throw new RuntimeException('Unexpected deployment cache state.');
    }
    $count = 0;
    $payload = ['RecordType' => 'Delivery', 'Recipient' => 'consumer@example.com', 'MessageID' => 'consumer-123', 'Metadata' => ['tenant' => 'consumer']];
    $application->make('events')->listen(PostmarkWebhookReceived::class, static function (PostmarkWebhookReceived $event) use (&$count, $payload): void {
        if ($event->payload !== $payload || $event->email !== 'consumer@example.com' || $event->messageId !== 'consumer-123' || $event->recordType !== 'Delivery') {
            throw new RuntimeException('Consumer event contract changed.');
        }
        $count++;
    });
    $request = Request::create('/consumer/postmark', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], json_encode($payload, JSON_THROW_ON_ERROR));
    $kernel = $application->make(HttpKernel::class);
    $response = $kernel->handle($request);
    if ($response->getStatusCode() !== 200 || $response->getContent() !== '["success"]' || $count !== 1) {
        throw new RuntimeException('Consumer webhook failed: HTTP '.$response->getStatusCode().', dispatched '.$count.' events; '.$response->getContent());
    }
    $kernel->terminate($request, $response);
    $routes = $application->make('router')->getRoutes();
    if ($routes->getByName('postmark-webhooks.process-webhook')?->uri() !== 'consumer/postmark') {
        throw new RuntimeException('Named consumer route changed.');
    }
};

$app = $boot();
$console = $app->make(ConsoleKernel::class);
$console->call('config:clear');
$console->call('route:clear');
$app = $boot();
$check($app, false);
$console = $app->make(ConsoleKernel::class);
try {
    foreach (['config:cache', 'route:cache'] as $command) {
        if ($console->call($command) !== 0) {
            throw new RuntimeException($command.' failed: '.$console->output());
        }
    }
    $app = $boot();
    $check($app, true);
    echo 'Consumer PASS: PHP '.PHP_VERSION.' / Laravel '.Application::VERSION.'; discovery, legacy config, listener, config/route caches.', PHP_EOL;
} finally {
    $console = $app->make(ConsoleKernel::class);
    $console->call('route:clear');
    $console->call('config:clear');
}
