<?php

namespace Citricguy\PostmarkWebhooks\Tests\Feature;

use Citricguy\PostmarkWebhooks\Events\PostmarkWebhookReceived;
use Citricguy\PostmarkWebhooks\Tests\TestCase;
use Illuminate\Support\Facades\Event;

class PostmarkWebhooksTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        Event::fake();
    }

    public function tearDown(): void
    {
        unset($_ENV['POSTMARK_WEBHOOK_PATH']);
    }

    protected function validPayload($attributes = [])
    {
        return array_merge([
            'Recipient' => 'john@example.com',
            'RecordType' => 'Delivery',
            'MessageID' => '9999-9999-9999-9999-9999',
        ], $attributes);
    }

    // @todo find a better way to setup this test
    public function test_middleware_blocks_non_postmark_ips_in_production()
    {
        app()->detectEnvironment(function() { return 'production'; });
        $response = $this->postJson('/api/postmark/webhook', $this->validPayload());
        $response->assertStatus(401);
        Event::assertNotDispatched(PostmarkWebhookReceived::class);
    }

    // @todo find a better way to setup this test
    public function test_middleware_does_not_block_outside_of_production()
    {
        app()->detectEnvironment(function() { return 'local'; });
        $response = $this->postJson('/api/postmark/webhook', $this->validPayload());
        $response->assertStatus(202);
        Event::assertDispatched(PostmarkWebhookReceived::class);
    }

    // @todo find a better way to setup this test
    public function test_middleware_allows_known_good_postmark_ips_in_production()
    {
        app()->detectEnvironment(function() { return 'production'; });
        $response = $this->postJson('/api/postmark/webhook', $this->validPayload(),
            ['REMOTE_ADDR' => '18.217.206.57']);
        $response->assertStatus(202);
        Event::assertDispatched(PostmarkWebhookReceived::class);
    }

    // @todo find a better way to setup this test
    // @todo find a better way to cleanup after this test
    public function test_that_config_is_publishable()
    {
        $this->assertFileDoesNotExist(config_path('postmark-webhooks.php'));

        $this->artisan('vendor:publish', ['--provider' => 'Citricguy\PostmarkWebhooks\PostmarkWebhooksServiceProvider', '--tag' => 'config']);
        $this->assertFileExists(config_path('postmark-webhooks.php'));

        // Clean up
        unlink(config_path('postmark-webhooks.php'));
        $this->assertFileDoesNotExist(config_path('postmark-webhooks.php'));
    }

    // @todo find a better way to setup this test
    public function test_that_postmark_ip_firewall_can_be_disabled_using_config()
    {
        app()->detectEnvironment(function() { return 'production'; });

        config(['postmark-webhooks.firewall_enabled' => false]);

        $response = $this->postJson('/api/postmark/webhook', $this->validPayload());
        $response->assertStatus(202);
        Event::assertDispatched(PostmarkWebhookReceived::class);
    }

    // @todo find a better way to setup this test
    public function test_postmark_webhook_path_is_configurable_using_config()
    {

        $this->assertFalse(array_key_exists('POSTMARK_WEBHOOK_PATH', $_ENV));
        $_ENV['POSTMARK_WEBHOOK_PATH'] = '/api/postmark/webhook-new';

        $this->refreshApplication();

        $response = $this->postJson('/api/postmark/webhook-new', $this->validPayload());
        $response->assertStatus(202);

        // @todo I want to test if the webhook was fired, however $this->refreshApplication() breaks things.
    }

    public function test_event_type_of_bounce_uses_the_email_field_instead_of_recipient()
    {
        $response = $this->postJson('/api/postmark/webhook', [
            'MessageID' => '1234',
            'RecordType' => 'Bounce',
            'Email' => 'jane@example.com',
        ]);

        $response->assertStatus(202);

        Event::assertDispatched(PostmarkWebhookReceived::class, function ($event) {
            return $event->email === 'jane@example.com';
        });

    }

    public function test_event_type_of_spam_complaint_uses_the_email_field_instead_of_recipient()
    {
        $response = $this->postJson('/api/postmark/webhook', [
            'MessageID' => '1234',
            'RecordType' => 'SpamComplaint',
            'Email' => 'jane@example.com',
        ]);

        $response->assertStatus(202);

        Event::assertDispatched(PostmarkWebhookReceived::class, function ($event) {
            return $event->email === 'jane@example.com';
        });
    }

    public function test_that_type_of_subscription_change_uses_recipient_field_for_email_address()
    {
        $response = $this->postJson('/api/postmark/webhook', [
            'MessageID' => '1234',
            'RecordType' => 'SubscriptionChange',
            'Recipient' => 'jane@example.com',
        ]);

        $response->assertStatus(202);

        Event::assertDispatched(PostmarkWebhookReceived::class, function ($event) {
            return $event->email === 'jane@example.com';
        });
    }

    public function test_that_basic_auth_protection_can_be_used_for_postmark_webhooks()
    {
        app()->detectEnvironment(function() { return 'production'; }); // Middleware is skipped if not in production.

        config(['postmark-webhooks.auth_user' => 'testuser']);
        config(['postmark-webhooks.auth_pass' => 'password']);

        // Post to json endpoint using auth
        $response = $this->postJson('/api/postmark/webhook', $this->validPayload(),
            [
                'REMOTE_ADDR' => '18.217.206.57', // Needed because we needed to fake production environment
                'PHP_AUTH_USER' => 'testuser',
                'PHP_AUTH_PW' => 'password'
            ]);

        $response->assertStatus(202);
    }

    public function test_that_basic_auth_fails_if_username_and_password_do_not_match()
    {
        app()->detectEnvironment(function() { return 'production'; }); // Middleware is skipped if not in production.

        config(['postmark-webhooks.auth_user' => 'testuser']);
        config(['postmark-webhooks.auth_pass' => 'password']);

        // Post to json endpoint using auth
        $response = $this->postJson('/api/postmark/webhook', $this->validPayload(),
            [
                'REMOTE_ADDR' => '18.217.206.57', // Needed because we needed to fake production environment
                'PHP_AUTH_USER' => 'testuser',
                'PHP_AUTH_PW' => 'badpass'
            ]);

        $response->assertStatus(401);
    }

    public function test_that_basic_auth_is_triggered_if_postmark_supplies_username_and_password()
    {
        app()->detectEnvironment(function() { return 'production'; }); // Middleware is skipped if not in production.

        // Post to json endpoint using auth
        $response = $this->postJson('/api/postmark/webhook', $this->validPayload(),
            [
                'REMOTE_ADDR' => '18.217.206.57', // Needed because we needed to fake production environment
                'PHP_AUTH_USER' => 'testuser',
                'PHP_AUTH_PW' => 'badpass'
            ]);

        $response->assertStatus(401);
    }

    public function test_that_basic_auth_is_triggered_if_username_and_password_are_configured_but_not_sent_by_postmark()
    {
        app()->detectEnvironment(function() { return 'production'; }); // Middleware is skipped if not in production.

        config(['postmark-webhooks.auth_user' => 'testuser']);
        config(['postmark-webhooks.auth_pass' => 'password']);

        // Post to json endpoint using auth
        $response = $this->postJson('/api/postmark/webhook', $this->validPayload(), ['REMOTE_ADDR' => '18.217.206.57']);

        $response->assertStatus(401);
    }


}