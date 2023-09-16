<?php

return [

    /*
     * The webhook_path is the path where Postmark will post the webhook events to.
     * This is configured in your Postmark account.
     */
    'webhook_path' => env('POSTMARK_WEBHOOK_PATH', '/api/postmark/webhook'),
];