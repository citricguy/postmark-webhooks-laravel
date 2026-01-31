<?php

namespace Citricguy\PostmarkWebhooks\Http\Controllers;

use Citricguy\PostmarkWebhooks\Events\PostmarkWebhookReceived;
use Illuminate\Http\Request;

class ProcessPostmarkWebhookController
{
    public function __invoke(Request $request): \Illuminate\Http\JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->input();

        /** @var string $recordType */
        $recordType = $request->input('RecordType', '');

        /** @var string|null $messageId */
        $messageId = $request->input('MessageID');

        /** @var string $email */
        $email = $request->input('Recipient') ?? $request->input('Email') ?? '';

        PostmarkWebhookReceived::dispatch($email, $recordType, $messageId, $payload);

        return response()->json(['success'])->setStatusCode(202);
    }
}
