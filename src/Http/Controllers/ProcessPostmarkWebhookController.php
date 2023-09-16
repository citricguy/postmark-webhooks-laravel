<?php

namespace Citricguy\PostmarkWebhooks\Http\Controllers;

use Citricguy\PostmarkWebhooks\Events\PostmarkWebhookReceived;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProcessPostmarkWebhookController
{
    public function __invoke(Request $request): \Illuminate\Http\JsonResponse
    {
        $payload = $request->input();
        $recordType = $request->input('RecordType');
        $messageId = $request->input('MessageID');
        $email = $request->input('Recipient') ?? $request->input('Email');

        new PostmarkWebhookReceived(
            email: $email,
            recordType: $recordType,
            messageId: $messageId,
            payload: $payload
        );

        return response()->json(['success'])->setStatusCode(202);
    }
}