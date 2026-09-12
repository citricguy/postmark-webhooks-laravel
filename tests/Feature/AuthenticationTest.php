<?php

namespace Citricguy\PostmarkWebhooks\Tests\Feature;

use Citricguy\PostmarkWebhooks\Events\PostmarkWebhookReceived;
use Citricguy\PostmarkWebhooks\Http\Middleware\VerifyPostmarkWebhookAuth;
use Citricguy\PostmarkWebhooks\Tests\Support\Payloads;
use Citricguy\PostmarkWebhooks\Tests\TestCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;

class AuthenticationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->application()->instance('env', 'production');
        Event::fake([PostmarkWebhookReceived::class]);
    }

    protected function tearDown(): void
    {
        Request::setTrustedProxies([], 0);
        TrustProxies::flushState();
        parent::tearDown();
    }

    /** @return iterable<string, array{string, int}> */
    public static function addresses(): iterable
    {
        foreach (['18.217.206.57', '3.134.147.250', '50.31.156.6', '50.31.156.77'] as $ip) {
            yield $ip => [$ip, 200];
        }
        foreach (['127.0.0.1', '203.0.113.10', '50.31.156.7', '::1', '::ffff:18.217.206.57'] as $ip) {
            yield $ip => [$ip, 401];
        }
    }

    #[DataProvider('addresses')]
    public function test_production_ip_filter(string $ip, int $status): void
    {
        $response = $this->withServerVariables(['REMOTE_ADDR' => $ip])->postJson('/api/postmark/webhook', Payloads::delivery());
        $response->assertStatus($status);
        if ($status === 401) {
            $response->assertExactJson(['error' => 'Unauthorized']);
            Event::assertNotDispatched(PostmarkWebhookReceived::class);
        } else {
            Event::assertDispatchedTimes(PostmarkWebhookReceived::class, 1);
        }
    }

    public function test_missing_client_ip_is_rejected(): void
    {
        $request = Request::create('/api/postmark/webhook', 'POST');
        $request->server->remove('REMOTE_ADDR');
        $response = (new VerifyPostmarkWebhookAuth)->handle($request, function (): never {
            self::fail('Missing client IP must not reach the controller.');
        });
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(401, $response->getStatusCode());
    }

    /** @return iterable<string, array{?string, ?string, ?string, ?string, int}> */
    public static function credentials(): iterable
    {
        yield 'none' => [null, null, null, null, 200];
        yield 'empty configuration' => ['', '', null, null, 200];
        yield 'correct' => ['user', 'secret', 'user', 'secret', 200];
        yield 'wrong username' => ['user', 'secret', 'other', 'secret', 401];
        yield 'wrong password' => ['user', 'secret', 'user', 'other', 401];
        yield 'missing both' => ['user', 'secret', null, null, 401];
        yield 'missing password' => ['user', 'secret', 'user', null, 401];
        yield 'missing username' => ['user', 'secret', null, 'secret', 401];
        yield 'unsolicited credentials' => [null, null, 'user', 'secret', 401];
        yield 'partial configuration' => ['user', null, 'user', null, 401];
        yield 'partial configuration extra password' => ['user', null, 'user', 'secret', 401];
        yield 'empty password' => ['user', '', 'user', '', 200];
        yield 'zero credentials missing' => ['0', '0', null, null, 401];
        yield 'zero credentials correct' => ['0', '0', '0', '0', 200];
        yield 'zero username missing' => ['0', null, null, null, 401];
        yield 'zero password missing' => [null, '0', null, null, 401];
        yield 'unsolicited zero username' => [null, null, '0', null, 401];
        yield 'password without username is not HTTP basic auth' => [null, null, null, '0', 200];
        yield 'case sensitive username' => ['User', 'secret', 'user', 'secret', 401];
        yield 'case sensitive password' => ['user', 'Secret', 'user', 'secret', 401];
    }

    #[DataProvider('credentials')]
    public function test_basic_auth_combinations(?string $configuredUser, ?string $configuredPassword, ?string $sentUser, ?string $sentPassword, int $status): void
    {
        config(['postmark-webhooks.auth_user' => $configuredUser, 'postmark-webhooks.auth_pass' => $configuredPassword]);
        $this->withServerVariables([
            'REMOTE_ADDR' => '18.217.206.57',
            'PHP_AUTH_USER' => $sentUser,
            'PHP_AUTH_PW' => $sentPassword,
        ])->postJson('/api/postmark/webhook', Payloads::delivery())->assertStatus($status);
        Event::assertDispatchedTimes(PostmarkWebhookReceived::class, $status === 200 ? 1 : 0);
    }

    public function test_valid_auth_does_not_bypass_the_ip_filter(): void
    {
        config(['postmark-webhooks.auth_user' => 'user', 'postmark-webhooks.auth_pass' => 'secret']);
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10', 'PHP_AUTH_USER' => 'user', 'PHP_AUTH_PW' => 'secret'])
            ->postJson('/api/postmark/webhook', Payloads::delivery())->assertUnauthorized();
        Event::assertNotDispatched(PostmarkWebhookReceived::class);
    }

    public function test_real_authorization_header_is_supported(): void
    {
        config(['postmark-webhooks.auth_user' => 'user', 'postmark-webhooks.auth_pass' => 'secret']);
        $this->withServerVariables(['REMOTE_ADDR' => '18.217.206.57'])
            ->postJson('/api/postmark/webhook', Payloads::delivery(), ['Authorization' => 'Basic '.base64_encode('user:secret')])->assertOk();
        Event::assertDispatchedTimes(PostmarkWebhookReceived::class, 1);
    }

    /** @return iterable<string, array{string, bool}> */
    public static function bypasses(): iterable
    {
        yield 'local' => ['local', true];
        yield 'testing' => ['testing', true];
        yield 'staging' => ['staging', true];
        yield 'production disabled' => ['production', false];
    }

    #[DataProvider('bypasses')]
    public function test_existing_firewall_bypasses_skip_both_checks(string $environment, bool $enabled): void
    {
        $this->application()->instance('env', $environment);
        config(['postmark-webhooks.firewall_enabled' => $enabled, 'postmark-webhooks.auth_user' => 'required', 'postmark-webhooks.auth_pass' => 'required']);
        $this->postJson('/api/postmark/webhook', Payloads::delivery())->assertOk();
        Event::assertDispatchedTimes(PostmarkWebhookReceived::class, 1);
    }

    /** @return iterable<string, array{bool, string, int}> */
    public static function proxies(): iterable
    {
        yield 'trusted proxy Postmark client' => [true, '18.217.206.57', 200];
        yield 'trusted proxy rejected client' => [true, '203.0.113.10', 401];
        yield 'untrusted forwarded IP' => [false, '18.217.206.57', 401];
        yield 'spoofed first hop' => [true, '18.217.206.57, 203.0.113.10', 401];
    }

    #[DataProvider('proxies')]
    public function test_only_application_trusted_proxies_are_respected(bool $trusted, string $forwarded, int $status): void
    {
        TrustProxies::at($trusted ? ['10.0.0.1'] : []);
        TrustProxies::withHeaders(Request::HEADER_X_FORWARDED_FOR);
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->postJson('/api/postmark/webhook', Payloads::delivery(), ['X-Forwarded-For' => $forwarded])->assertStatus($status);
        Event::assertDispatchedTimes(PostmarkWebhookReceived::class, $status === 200 ? 1 : 0);
    }
}
