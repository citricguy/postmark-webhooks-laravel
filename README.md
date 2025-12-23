# Postmark Webhooks for Laravel
You're looking for an easy to implement package, that accepts webhooks from PostmarkApp.com. This package does just that.

This no-frills solution provides a simple api to accept webhooks from PostmarkApp.com and then fire an event that you can listen for in your application. 

There are no added migrations, no models, only a single event that you can listen for and then do whatever you want with the payload.

It is configurable, easy to use and utilizes middleware to ensure the webhooks are coming from PostmarkApp.com.

### Requirements
- Laravel 12.x
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

If you haven't used events or listener before, please see the laravel documentation regarding [events](https://laravel.com/docs/11.x/events).

In short, we'll create a listener, register it in our `EventServiceProvider` and then handle the event in our listener.
```php
/**
 * The event listener mappings for the application.
 *
 * @var array
 */
protected $listen = [
    PostmarkWebhookCalled::class => [
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

Run static analysis with PHPStan (level 8):
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

- **Pest 4** for testing
- **PHPStan (level 8)** with Larastan for static analysis
- **Laravel Pint** for code formatting

All checks are run in CI across PHP 8.3, 8.4, and 8.5 with Laravel 12.

## Credits

This project was inspired by [Laravel Postmark Webooks](https://github.com/mvdnbrk/laravel-postmark-webhooks) and is a simpler alternative without any added models or migrations. It also allows for basic-auth webhook integration with PostmarkApp while trying to keep up with current versions of Laravel and PHP.


## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.