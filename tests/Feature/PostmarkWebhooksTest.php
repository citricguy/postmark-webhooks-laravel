<?php

use Citricguy\PostmarkWebhooks\Events\PostmarkWebhookReceived;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Event::fake();
});

afterEach(function () {
    unset($_ENV['POSTMARK_WEBHOOK_PATH']);
});

/**
 * @param  array<string, mixed>  $attributes
 * @return array<string, mixed>
 */
function validPayload(array $attributes = []): array
{
    return array_merge([
        'Recipient' => 'john@example.com',
        'RecordType' => 'Delivery',
        'MessageID' => '9999-9999-9999-9999-9999',
    ], $attributes);
}

test('middleware blocks non postmark ips in production', function () {
    $this->app['env'] = 'production';
    $response = $this->postJson('/api/postmark/webhook', validPayload());
    $response->assertStatus(401);
    Event::assertNotDispatched(PostmarkWebhookReceived::class);
});

test('middleware does not block outside of production', function () {
    $this->app['env'] = 'local';
    $response = $this->postJson('/api/postmark/webhook', validPayload());
    $response->assertStatus(202);
    Event::assertDispatched(PostmarkWebhookReceived::class);
});

test('middleware allows known good postmark ips in production', function () {
    $this->app['env'] = 'production';
    $response = $this->postJson('/api/postmark/webhook', validPayload(), ['REMOTE_ADDR' => '18.217.206.57']);
    $response->assertStatus(202);
    Event::assertDispatched(PostmarkWebhookReceived::class);
});

test('that config is publishable', function () {
    expect(config_path('postmark-webhooks.php'))->not->toBeFile();

    $this->artisan('vendor:publish', ['--provider' => 'Citricguy\PostmarkWebhooks\PostmarkWebhooksServiceProvider', '--tag' => 'config']);
    expect(config_path('postmark-webhooks.php'))->toBeFile();

    // Clean up
    unlink(config_path('postmark-webhooks.php'));
    expect(config_path('postmark-webhooks.php'))->not->toBeFile();
});

test('that postmark ip firewall can be disabled using config', function () {
    $this->app['env'] = 'production';
    config(['postmark-webhooks.firewall_enabled' => false]);

    $response = $this->postJson('/api/postmark/webhook', validPayload());
    $response->assertStatus(202);
    Event::assertDispatched(PostmarkWebhookReceived::class);
});

test('postmark webhook path is configurable using config', function () {
    expect($_ENV)->not->toHaveKey('POSTMARK_WEBHOOK_PATH');
    $_ENV['POSTMARK_WEBHOOK_PATH'] = '/api/postmark/webhook-new';

    $this->refreshApplication();

    $response = $this->postJson('/api/postmark/webhook-new', validPayload());
    $response->assertStatus(202);
});

test('event type of bounce uses the email field instead of recipient', function () {
    $response = $this->postJson('/api/postmark/webhook', [
        'MessageID' => '1234',
        'RecordType' => 'Bounce',
        'Email' => 'jane@example.com',
    ]);

    $response->assertStatus(202);

    Event::assertDispatched(PostmarkWebhookReceived::class, function ($event) {
        return $event->email === 'jane@example.com';
    });
});

test('event type of spam complaint uses the email field instead of recipient', function () {
    $response = $this->postJson('/api/postmark/webhook', [
        'MessageID' => '1234',
        'RecordType' => 'SpamComplaint',
        'Email' => 'jane@example.com',
    ]);

    $response->assertStatus(202);

    Event::assertDispatched(PostmarkWebhookReceived::class, function ($event) {
        return $event->email === 'jane@example.com';
    });
});

test('that type of subscription change uses recipient field for email address', function () {
    $response = $this->postJson('/api/postmark/webhook', [
        'MessageID' => '1234',
        'RecordType' => 'SubscriptionChange',
        'Recipient' => 'jane@example.com',
    ]);

    $response->assertStatus(202);

    Event::assertDispatched(PostmarkWebhookReceived::class, function ($event) {
        return $event->email === 'jane@example.com';
    });
});

test('that basic auth protection can be used for postmark webhooks', function () {
    $this->app['env'] = 'production'; // Middleware is skipped if not in production.

    config(['postmark-webhooks.auth_user' => 'testuser']);
    config(['postmark-webhooks.auth_pass' => 'password']);

    // Post to json endpoint using auth
    $response = $this->postJson('/api/postmark/webhook', validPayload(), [
        'REMOTE_ADDR' => '18.217.206.57', // Needed because we needed to fake production environment
        'PHP_AUTH_USER' => 'testuser',
        'PHP_AUTH_PW' => 'password',
    ]);

    $response->assertStatus(202);
});

test('that basic auth fails if username and password do not match', function () {
    $this->app['env'] = 'production'; // Middleware is skipped if not in production.

    config(['postmark-webhooks.auth_user' => 'testuser']);
    config(['postmark-webhooks.auth_pass' => 'password']);

    // Post to json endpoint using auth
    $response = $this->postJson('/api/postmark/webhook', validPayload(), [
        'REMOTE_ADDR' => '18.217.206.57', // Needed because we needed to fake production environment
        'PHP_AUTH_USER' => 'testuser',
        'PHP_AUTH_PW' => 'badpass',
    ]);

    $response->assertStatus(401);
});

test('that basic auth is triggered if postmark supplies username and password', function () {
    $this->app['env'] = 'production'; // Middleware is skipped if not in production.

    // Post to json endpoint using auth
    $response = $this->postJson('/api/postmark/webhook', validPayload(), [
        'REMOTE_ADDR' => '18.217.206.57', // Needed because we needed to fake production environment
        'PHP_AUTH_USER' => 'testuser',
        'PHP_AUTH_PW' => 'badpass',
    ]);

    $response->assertStatus(401);
});

test('that basic auth is triggered if username and password are configured but not sent by postmark', function () {
    $this->app['env'] = 'production'; // Middleware is skipped if not in production.

    config(['postmark-webhooks.auth_user' => 'testuser']);
    config(['postmark-webhooks.auth_pass' => 'password']);

    // Post to json endpoint using auth
    $response = $this->postJson('/api/postmark/webhook', validPayload(), ['REMOTE_ADDR' => '18.217.206.57']);

    $response->assertStatus(401);
});
