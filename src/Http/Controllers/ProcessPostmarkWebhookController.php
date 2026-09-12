<?php

namespace Citricguy\PostmarkWebhooks\Http\Controllers;

use Citricguy\PostmarkWebhooks\Events\PostmarkWebhookReceived;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

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
        $status = config('postmark-webhooks.success_status', 200);

        if (! in_array($status, [200, 202, '200', '202'], true)) {
            throw new InvalidArgumentException('postmark-webhooks.success_status must be 200 or 202.');
        }

        PostmarkWebhookReceived::dispatch($email, $recordType, $messageId, $payload);

        return response()->json(['success'], (int) $status);
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
