# Postmark Webhooks for Laravel
This is a very simple API that makes PostmarkApp webhooks available to Laravel using events and listeners.

## Installation

You can install the package via composer:

``` bash
composer require citricguy/laravel-postmark-webhooks
```

## Configure webhooks in your Postmark account

On the servers page of your [Postmark](https://account.postmarkapp.com/) account select the server and then the appropriate stream you would use with your laravel project.

Next go to 'settings', then 'webhooks' and finally 'add webhook'.

Add your sites URL and then the path to the webhook, which is `/api/postmark/webhook` by default. (i.e. `https://<your-domain.com>/api/postmark/webhook`)

Pick the events Postmark should send to you and save the webhook.

### Event Setup
To interact with the webhooks you will be using event listeners.  This package will fire a `PostmarkWebhookReceived` event for every webhook call.  You may register an event listener in the `EventServiceProvider`:
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

### Configuration

Though not necessary, if you would like to modify the default settings you can publish the config file to your project:

```bash
php artisan vendor:publish --provider="Citricguy\PostmarkWebhooks\PostmarkWebhooksServiceProvider" --tag="config"
```

Here you can change the API endpoint.  By default, the webhook_path is set to `/api/postmark/webhook`.
You can also configure the API endpoint by using `POSTMARK_WEBHOOK_PATH` in your `.env` file. (i.e. `POSTMARK_WEBHOOK_PATH="/api/postmark/a-different-webhook"`)

### Middleware/Firewall
There is a middleware that filters IPs that are not from Postmark. While your app is not in production (.env != APP_ENV=production) this middleware will not function. Once your app is in production however, the filtering will be enabled.