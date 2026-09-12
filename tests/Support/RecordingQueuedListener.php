<?php

namespace Citricguy\PostmarkWebhooks\Tests\Support;

use Citricguy\PostmarkWebhooks\Events\PostmarkWebhookReceived;
use Illuminate\Contracts\Queue\ShouldQueue;

class RecordingQueuedListener implements ShouldQueue
{
    public static ?PostmarkWebhookReceived $received = null;

    public function handle(PostmarkWebhookReceived $event): void
    {
        self::$received = $event;
    }
}
