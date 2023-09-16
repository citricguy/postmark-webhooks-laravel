<?php

namespace Citricguy\PostmarkWebhooks\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Facades\Log;

class PostmarkWebhookReceived
{

    use Dispatchable;

    /** @var string */
    public string $email;

    /** @var string */
    public string $recordType;

    /** @var string */
    public ?string $messageId;

    /** @var array */
    public array $payload;

    public function __construct(string $email, string $recordType, ?string $messageId, array $payload)
    {
        $this->email = $email;
        $this->recordType = $recordType;
        $this->messageId = $messageId;
        $this->payload = $payload;
    }
}