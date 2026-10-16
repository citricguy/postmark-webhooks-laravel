<?php

namespace Citricguy\PostmarkWebhooks\Tests\Feature;

use Citricguy\PostmarkWebhooks\Events\PostmarkWebhookReceived;
use Citricguy\PostmarkWebhooks\Http\Middleware\VerifyPostmarkWebhookAuth;
use Citricguy\PostmarkWebhooks\PostmarkWebhooksServiceProvider;
use Citricguy\PostmarkWebhooks\Tests\Support\Payloads;
use Citricguy\PostmarkWebhooks\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;

class ConfigurationTest extends TestCase
{
    /** @var array<string, array{bool, mixed}> */
    private array $environment = [];

    /** @var list<string> */
    private array $createdFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withServerVariables(['REMOTE_ADDR' => '18.217.206.57']);
    }

    protected function tearDown(): void
    {
        try {
            Artisan::call('route:clear');
            Artisan::call('config:clear');
            foreach ($this->createdFiles as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            foreach ($this->environment as $name => [$existed, $value]) {
                if ($existed) {
                    $_ENV[$name] = $value;
                } else {
                    unset($_ENV[$name]);
                }
            }
        } finally {
            parent::tearDown();
        }
    }

    private function setEnvironment(string $name, string $value): void
    {
        $this->environment[$name] = [array_key_exists($name, $_ENV), $_ENV[$name] ?? null];
        $_ENV[$name] = $value;
    }

    public function test_default_route_name_method_and_middleware(): void
    {
        $route = Route::getRoutes()->getByName('postmark-webhooks.process-webhook');
        self::assertNotNull($route);
        self::assertSame('api/postmark/webhook', $route->uri());
        self::assertSame(['POST'], $route->methods());
        self::assertContains('api', $route->middleware());
        self::assertContains(VerifyPostmarkWebhookAuth::class, $route->middleware());
        self::assertStringEndsWith('/api/postmark/webhook', route('postmark-webhooks.process-webhook'));
    }

    public function test_invalid_route_configuration_fails_explicitly(): void
    {
        config(['postmark-webhooks.webhook_path' => []]);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('postmark-webhooks.webhook_path must be a string.');
        (new PostmarkWebhooksServiceProvider($this->application()))->boot();
    }

    /** @return iterable<string, array{string}> */
    public static function unsupportedMethods(): iterable
    {
        foreach (['GET', 'PUT', 'PATCH', 'DELETE', 'HEAD'] as $method) {
            yield $method => [$method];
        }
    }

    #[DataProvider('unsupportedMethods')]
    public function test_only_post_dispatches(string $method): void
    {
        Event::fake([PostmarkWebhookReceived::class]);
        $this->json($method, '/api/postmark/webhook', Payloads::delivery())->assertStatus(405);
        Event::assertNotDispatched(PostmarkWebhookReceived::class);
    }

    public function test_environment_configures_path_and_legacy_success_status(): void
    {
        $this->setEnvironment('POSTMARK_WEBHOOK_PATH', '/hooks/postmark');
        $this->setEnvironment('POSTMARK_WEBHOOK_SUCCESS_STATUS', '202');
        $this->refreshApplication();
        Event::fake([PostmarkWebhookReceived::class]);
        $this->postJson('/hooks/postmark', Payloads::delivery())->assertStatus(202)->assertExactJson(['success']);
        $this->postJson('/api/postmark/webhook', Payloads::delivery())->assertNotFound();
        Event::assertDispatchedTimes(PostmarkWebhookReceived::class, 1);
    }

    public function test_publishing_does_not_overwrite_existing_configuration(): void
    {
        $path = config_path('postmark-webhooks.php');
        self::assertFileDoesNotExist($path);
        $this->createdFiles[] = $path;
        self::assertSame(0, Artisan::call('vendor:publish', ['--provider' => PostmarkWebhooksServiceProvider::class, '--tag' => 'config']));
        self::assertFileExists($path);
        $published = file_get_contents($path);
        self::assertIsString($published);
        self::assertStringContainsString('POSTMARK_WEBHOOK_SUCCESS_STATUS', $published);
        file_put_contents($path, "<?php\nreturn ['webhook_path' => '/existing'];\n");
        self::assertSame(0, Artisan::call('vendor:publish', ['--provider' => PostmarkWebhooksServiceProvider::class, '--tag' => 'config']));
        self::assertSame("<?php\nreturn ['webhook_path' => '/existing'];\n", file_get_contents($path));
    }

    public function test_existing_environment_switch_explicitly_disables_the_firewall(): void
    {
        $this->setEnvironment('POSTMARK_WEBHOOK_FIREWALL_ENABLED', 'false');
        $this->setEnvironment('POSTMARK_WEBHOOK_AUTH_USER', 'required');
        $this->setEnvironment('POSTMARK_WEBHOOK_AUTH_PASS', 'required');
        $this->refreshApplication();
        Event::fake([PostmarkWebhookReceived::class]);
        self::assertFalse(config('postmark-webhooks.firewall_enabled'));
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->postJson('/api/postmark/webhook', Payloads::delivery())->assertOk();
        Event::assertDispatchedTimes(PostmarkWebhookReceived::class, 1);
    }

    public function test_old_published_configuration_inherits_new_default(): void
    {
        $path = config_path('postmark-webhooks.php');
        self::assertFileDoesNotExist($path);
        $this->createdFiles[] = $path;
        file_put_contents($path, "<?php\nreturn ['webhook_path' => '/legacy/postmark', 'firewall_enabled' => false, 'auth_user' => 'old-user', 'auth_pass' => 'old-pass'];\n");
        $this->refreshApplication();
        Event::fake([PostmarkWebhookReceived::class]);
        self::assertSame(200, config('postmark-webhooks.success_status'));
        self::assertSame('old-user', config('postmark-webhooks.auth_user'));
        self::assertSame('old-pass', config('postmark-webhooks.auth_pass'));
        $this->application()->instance('env', 'production');
        $this->postJson('/legacy/postmark', Payloads::delivery())->assertOk();
        Event::assertDispatchedTimes(PostmarkWebhookReceived::class, 1);
    }
}
