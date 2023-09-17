<?php

return [

    /*
     * The webhook_path is the path where Postmark will post the webhook events to.
     * This is configured in your Postmark account.
     */
    'webhook_path' => env('POSTMARK_WEBHOOK_PATH', '/api/postmark/webhook'),

    /*
     * By default, postmark-webhooks must come from a valid Postmark IP address and might require basic-auth.
     *
     * This is disabled by default if you are not in a production environment, however, you can disable IP and
     * basic-auth checks in production by setting this to false.
     */
    'firewall_enabled' => env('POSTMARK_WEBHOOK_FIREWALL_ENABLED', true),

    'auth_user' => env('POSTMARK_WEBHOOK_AUTH_USER', null),
    'auth_pass' => env('POSTMARK_WEBHOOK_AUTH_PASS', null),
];