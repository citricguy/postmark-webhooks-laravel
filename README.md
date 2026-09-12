# Postmark Webhooks for Laravel
You're looking for an easy to implement package, that accepts webhooks from PostmarkApp.com. This package does just that.

This no-frills solution provides a simple api to accept webhooks from PostmarkApp.com and then fire an event that you can listen for in your application. 

There are no added migrations, no models, only a single event that you can listen for and then do whatever you want with the payload.

It is configurable, easy to use and utilizes middleware to ensure the webhooks are coming from PostmarkApp.com.

### Requirements
- Laravel 12.x or 13.x
- PHP 8.3|8.4|8.5
- An active PostmarkApp.com account.

## Installation
You can install this package using composer:

``` bash
composer require citricguy/postmark-webhooks-laravel
```

## Configure webhooks in your Postmark account

On the servers page of your Postmark account choose the server and stream you would like to receive webhooks from.

Once there, go to 'settings' -> 'webhooks' -> 'add webhook'.

Add your webhook URL which is `https://<your-domain.com>/api/postmark/webhook` by default (this is configurable).

Select the events Postmark should send to your webhook and then save.

## Event Configuration
Listening for the `PostmarkWebhookReceived` event is the primary way we'll interact with the webhooks.

If you haven't used events or listener before, please see the Laravel documentation regarding [events](https://laravel.com/docs/events).

In short, we'll create a listener, register it in our `EventServiceProvider` and then handle the event in our listener.
```php
/**
 * The event listener mappings for the application.
 *
 * @var array
 */
protected $listen = [
    PostmarkWebhookReceived::class => [
        ListenerClasses::class, // Create with `php artisan make:listener <listener name>`
    ],
];
```

Here is an example listener: 

```php
<?php

namespace App\Listeners;

use Citricguy\PostmarkWebhooks\Events\PostmarkWebhookReceived;

class ProcessPostmarkWebhooks
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(PostmarkWebhookReceived $event): void
    {

        // Process Webhook Data Here.

        // Access the PostmarkApp payload using: $event->payload.
        // The email address, record type and message ID are also made available:
        // $event->email;
        // $event->recordType;
        // $event->messageId;
        
    }
}
```

## Advanced Configuration

Though not necessary, if you would like to configure the webhook's path, basic-auth or disable the auth middleware you can publish the config file.

```bash
php artisan vendor:publish --provider="Citricguy\PostmarkWebhooks\PostmarkWebhooksServiceProvider" --tag="config"
```

You can change your settings in that config, or use your .env file instead if you prefer. 

The following .env values are available:

```dotenv
POSTMARK_WEBHOOK_PATH=/api/postmark/webhook
POSTMARK_WEBHOOK_FIREWALL_ENABLED=true
POSTMARK_WEBHOOK_AUTH_USER=
POSTMARK_WEBHOOK_AUTH_PASS=
```

## About the Firewall
By default, the firewall is disabled unless you are in a 'production' environment. (i.e. `APP_ENV=production`).

The middleware will do 'basic-auth' if configured. To use this feature, you will need to configure your Postmark webhook to include Basic auth credentials by configuring your wehbook on PostmarkApp.com.

Finally, you will need to set up your .env file:

```dotenv
POSTMARK_WEBHOOK_AUTH_USER=<username matching webhook configuration>
POSTMARK_WEBHOOK_AUTH_PASS=<password matching webhook configuration>
```

The middleware also confirms the source of the webhook is from PostmarkApp.com. This is done by checking the IP address of the request against the [list of IP addresses](https://postmarkapp.com/support/article/800-ips-for-firewalls#webhooks) provided by PostmarkApp.com.

To disable the firewall, set `POSTMARK_WEBHOOK_FIREWALL_ENABLED=false` in your .env file or simply be in any environment except for production.

## Testing

Run the full test suite:
``` bash
composer test
```

Run static analysis with PHPStan (level 9):
``` bash
composer analyse
```

Run code formatting checks with Laravel Pint:
``` bash
composer test:lint
```

Format code automatically:
``` bash
composer lint
```

Run all checks (tests, static analysis, linting):
``` bash
composer test:lint && composer test:analyse && composer test
```

## Development

This package uses modern PHP tooling to ensure code quality:

- **Pest 5** for development testing (PHP 8.4 or 8.5)
- **PHPStan (level 9)** with Larastan for static analysis
- **Laravel Pint** for code formatting

All checks are run in CI across PHP 8.3, 8.4, and 8.5 with:

- Laravel 12 + Testbench 10
- Laravel 13 + Testbench 11

See [verification results](docs/verification.md), the [maintainer tools guide](tools/README.md), and the [release runbook](docs/releasing.md).

### Runtime versus development dependencies

Consumers need PHP `^8.3` and Laravel `^12.0 || ^13.0`. Composer does not install this package's development dependencies into your application. Package development uses Pest 5 and normally PHP 8.5 with mbstring, curl, XML/DOM, and SQLite extensions; coverage and mutation tests also need Xdebug.

The root Composer configuration sets `platform.php` to `8.4.1` so development dependency updates, including Dependabot, resolve against the minimum PHP version of Pest 5 and its Symfony dependencies rather than the package's PHP 8.3 runtime minimum. Composer configuration is root-only: consuming applications retain their own platform settings and PHP 8.3 support. The isolated compatibility harnesses do not inherit this setting and continue testing their actual PHP versions. Run `composer check-platform-reqs` to verify your actual development environment as well.

The same portable, explicitly typed test classes run in all 12 latest/lowest dependency lanes. Laravel 13 on PHP 8.4/8.5 uses Pest 5. PHP 8.3 and all Laravel 12 lanes use isolated PHPUnit 12 harnesses: Pest 5 requires Symfony Process 8, whereas Testbench 10 requires Process 7. This tooling distinction does not reduce runtime support.

```bash
composer update --prefer-stable
composer validate --strict
composer audit --locked
composer analyse
composer test:lint
composer test
XDEBUG_MODE=coverage composer test:coverage
XDEBUG_MODE=coverage composer test:mutate
PHP_BINARY=php8.5 bash tools/check-matrix.sh 13 latest
PHP_BINARY=php8.3 bash tools/check-matrix.sh 12 lowest
```

PHPStan level 9 checks source, tests, and PHP tooling without baselines, ignores, or excluded files. Coverage is reported, not used as an arbitrary acceptance percentage. Focused mutation tests check authentication, validation, dispatch, and each allowlisted IP.

## Upgrading to 2.2

Successful requests now return HTTP **200** with the unchanged JSON body `["success"]`, matching [Postmark's verification requirement](https://postmarkapp.com/developer/webhooks/webhooks-overview). Existing `^2.0` constraints permit this release; update the application's lock file through its normal dependency-update process.

If an integration specifically requires the old 202 response, set:

```dotenv
POSTMARK_WEBHOOK_SUCCESS_STATUS=202
```

The configuration key is `postmark-webhooks.success_status`. Only integer/string 200 or 202 are accepted; explicit invalid values throw before dispatch. Older published configuration inherits the new default without republishing. Rebuild your application's configuration cache after deployment or environment changes. Prefer 200 for Postmark verification and update exact-202 tests/monitoring accordingly.

### Payloads and processing guarantees

Delivery, Bounce, SpamComplaint, Open, Click, and SubscriptionChange outbound webhook payloads are tested. Unknown record types continue to be accepted when required fields are valid. Postmark's separate inbound-email webhook format is not this package's supported payload contract.

`RecordType` and a nonempty string `Recipient` or fallback `Email` are required. Recipient takes precedence; no new email-format validation is imposed. `MessageID` can be absent or null, including SubscriptionChange notifications. Additional nested fields remain available in the event payload. Laravel's normal request middleware still applies.

The event constructor, public properties, route name, and synchronous dispatch are unchanged. Synchronous listener failures propagate before acknowledgment. Queued listeners run according to your application's queue configuration; a later queued failure cannot change a response already sent. The package adds no persistence, deduplication, tenant selection, or queue policy. Repeated deliveries dispatch repeatedly; implement idempotency in the application, and do not assume MessageID alone uniquely identifies every webhook event.

Postmark documents retries for server errors, 408, and 429; other client errors may be dropped. Invalid payloads remain 422 and authentication failures remain 401. Review [Postmark's webhook guidance](https://postmarkapp.com/developer/webhooks/webhooks-overview) when designing listeners and operational alerts.

### Firewall and proxy compatibility

The allowlist was checked against [Postmark's webhook IP list](https://postmarkapp.com/support/article/800-ips-for-firewalls#webhooks) on September 12, 2026: `3.134.147.250`, `50.31.156.6`, `50.31.156.77`, and `18.217.206.57`. These are webhook addresses, not the SMTP ranges on that page.

Enforcement remains production-only and can still be disabled with the existing switch. Basic-auth string comparisons are timing-safe; the string `"0"` now correctly counts as a credential. Ordinary absent/empty credential behavior is preserved. Configure both username and password when enabling basic auth.

The package uses Laravel's trusted-proxy handling and does not change which proxies your application trusts. Only trust your actual ingress proxies; incorrectly trusting arbitrary forwarded headers can undermine IP filtering. Both trusted-proxy forwarding and untrusted spoofed headers are regression-tested. Non-production environments bypass this middleware, so staging success alone does not verify production ingress/authentication.

## Credits

This project was inspired by [Laravel Postmark Webooks](https://github.com/mvdnbrk/laravel-postmark-webhooks) and is a simpler alternative without any added models or migrations. It also allows for basic-auth webhook integration with PostmarkApp while trying to keep up with current versions of Laravel and PHP.


## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
