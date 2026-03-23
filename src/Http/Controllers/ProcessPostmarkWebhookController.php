<?php

namespace Citricguy\PostmarkWebhooks\Http\Controllers;

use Citricguy\PostmarkWebhooks\Events\PostmarkWebhookReceived;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProcessPostmarkWebhookController
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->input();

        $recordType = $this->stringValue($payload, 'RecordType');
        $email = $this->stringValue($payload, 'Recipient') ?? $this->stringValue($payload, 'Email');

        if ($recordType === null || $email === null) {
            return response()->json(['error' => 'Invalid Postmark webhook payload.'], 422);
        }

        $messageId = $this->stringValue($payload, 'MessageID');

        PostmarkWebhookReceived::dispatch($email, $recordType, $messageId, $payload);

        return response()->json(['success'])->setStatusCode(202);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function stringValue(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
