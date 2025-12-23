<?php

namespace Citricguy\PostmarkWebhooks\Events;

use Illuminate\Foundation\Events\Dispatchable;

class PostmarkWebhookReceived
{
    use Dispatchable;

    public string $email;

    public string $recordType;

    public ?string $messageId;

    /** @var array<string, mixed> */
    public array $payload;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(string $email, string $recordType, ?string $messageId, array $payload)
    {
        $this->email = $email;
        $this->recordType = $recordType;
        $this->messageId = $messageId;
        $this->payload = $payload;
    }
}
