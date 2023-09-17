<?php

return [

    /*
     * The webhook_path is the path where Postmark will post the webhook events to.
     * This is configured in your Postmark account.
     */
    'webhook_path' => env('POSTMARK_WEBHOOK_PATH', '/api/postmark/webhook'),

    /*
     * By default, postmark-webhooks must come from a valid Postmark IP address.
     * When running outside of production (ie., local, staging, etc) this is disabled.
     *
     * If you need to disable the IP check in production, set this value to `false`.
     */
    'firewall_enabled' => env('POSTMARK_WEBHOOK_FIREWALL_ENABLED', true),

    'auth_user' => env('POSTMARK_WEBHOOK_AUTH_USER', null),
    'auth_pass' => env('POSTMARK_WEBHOOK_AUTH_PASS', null),
];