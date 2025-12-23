<?php

namespace Citricguy\PostmarkWebhooks\Http\Controllers;

use Citricguy\PostmarkWebhooks\Events\PostmarkWebhookReceived;
use Illuminate\Http\Request;

class ProcessPostmarkWebhookController
{
    public function __invoke(Request $request): \Illuminate\Http\JsonResponse
    {
        $payload = $request->input();
        $recordType = $request->input('RecordType');
        $messageId = $request->input('MessageID');
        $email = $request->input('Recipient') ?? $request->input('Email');

        PostmarkWebhookReceived::dispatch($email, $recordType, $messageId, $payload);

        return response()->json(['success'])->setStatusCode(202);
    }
}
