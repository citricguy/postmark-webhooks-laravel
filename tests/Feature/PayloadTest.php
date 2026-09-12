<?php

namespace Citricguy\PostmarkWebhooks\Tests\Feature;

use Citricguy\PostmarkWebhooks\Events\PostmarkWebhookReceived;
use Citricguy\PostmarkWebhooks\Tests\Support\Payloads;
use Citricguy\PostmarkWebhooks\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DataProviderExternal;

class PayloadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([PostmarkWebhookReceived::class]);
    }

    /** @param array<string, mixed> $payload */
    #[DataProviderExternal(Payloads::class, 'webhooks')]
    public function test_realistic_webhooks_preserve_every_event_property(array $payload, string $email, string $type, ?string $messageId): void
    {
        $this->postJson('/api/postmark/webhook', $payload)->assertOk()->assertExactJson(['success']);
        Event::assertDispatchedTimes(PostmarkWebhookReceived::class, 1);
        Event::assertDispatched(PostmarkWebhookReceived::class, function (PostmarkWebhookReceived $event) use ($payload, $email, $type, $messageId): bool {
            self::assertSame($payload, $event->payload);
            self::assertSame($email, $event->email);
            self::assertSame($type, $event->recordType);
            self::assertSame($messageId, $event->messageId);

            return true;
        });
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidPayloads(): iterable
    {
        yield 'empty body' => [[]];
        foreach (['RecordType', 'Recipient'] as $key) {
            $payload = Payloads::delivery();
            unset($payload[$key]);
            yield 'missing '.$key => [$payload];
            foreach ([null, '', 42, true, false, [], ['nested' => 'value']] as $index => $value) {
                yield $key.' invalid '.$index => [array_replace(Payloads::delivery(), [$key => $value])];
            }
        }
    }

    /** @param array<string, mixed> $payload */
    #[DataProvider('invalidPayloads')]
    public function test_invalid_payloads_never_dispatch(array $payload): void
    {
        $this->postJson('/api/postmark/webhook', $payload)
            ->assertStatus(422)->assertExactJson(['error' => 'Invalid Postmark webhook payload.']);
        Event::assertNotDispatched(PostmarkWebhookReceived::class);
    }

    public function test_malformed_json_never_dispatches(): void
    {
        $this->call('POST', '/api/postmark/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
        ], '{"RecordType":')->assertStatus(422);
        Event::assertNotDispatched(PostmarkWebhookReceived::class);
    }

    /** @return iterable<string, array{mixed, ?string}> */
    public static function messageIds(): iterable
    {
        yield 'string' => ['000123', '000123'];
        yield 'zero string' => ['0', '0'];
        foreach ([null, '', 123, true, false, []] as $index => $value) {
            yield 'nullable '.$index => [$value, null];
        }
    }

    #[DataProvider('messageIds')]
    public function test_message_id_normalization_remains_compatible(mixed $value, ?string $expected): void
    {
        $payload = array_replace(Payloads::delivery(), ['MessageID' => $value]);
        $expectedPayload = array_replace($payload, ['MessageID' => $value === '' ? null : $value]);
        $this->postJson('/api/postmark/webhook', $payload)->assertOk();
        Event::assertDispatched(PostmarkWebhookReceived::class, fn (PostmarkWebhookReceived $event): bool => $event->messageId === $expected && $event->payload === $expectedPayload);
    }

    public function test_missing_message_id_and_future_record_types_are_accepted(): void
    {
        $payload = Payloads::delivery();
        unset($payload['MessageID']);
        $payload['RecordType'] = 'FutureEvent';
        $this->postJson('/api/postmark/webhook', $payload)->assertOk();
        Event::assertDispatched(PostmarkWebhookReceived::class, fn (PostmarkWebhookReceived $event): bool => $event->messageId === null && $event->recordType === 'FutureEvent');
    }

    /** @return iterable<string, array{mixed, string}> */
    public static function recipients(): iterable
    {
        yield 'recipient wins' => ['first@example.com', 'first@example.com'];
        yield 'no email-format restriction' => ['legacy-recipient', 'legacy-recipient'];
        yield 'string zero' => ['0', '0'];
        foreach ([null, '', 42, false, []] as $index => $value) {
            yield 'fallback '.$index => [$value, 'fallback@example.com'];
        }
    }

    #[DataProvider('recipients')]
    public function test_recipient_precedence(mixed $recipient, string $expected): void
    {
        $payload = array_replace(Payloads::delivery(), ['Recipient' => $recipient, 'Email' => 'fallback@example.com']);
        $this->postJson('/api/postmark/webhook', $payload)->assertOk();
        Event::assertDispatched(PostmarkWebhookReceived::class, fn (PostmarkWebhookReceived $event): bool => $event->email === $expected);
    }

    /** @return iterable<string, array{int|string, int}> */
    public static function successStatuses(): iterable
    {
        yield '200' => [200, 200];
        yield '202' => [202, 202];
        yield 'environment 200' => ['200', 200];
        yield 'environment 202' => ['202', 202];
    }

    #[DataProvider('successStatuses')]
    public function test_success_status_is_configurable(int|string $configured, int $expected): void
    {
        config(['postmark-webhooks.success_status' => $configured]);
        $this->postJson('/api/postmark/webhook', Payloads::delivery())->assertStatus($expected)->assertExactJson(['success']);
        Event::assertDispatchedTimes(PostmarkWebhookReceived::class, 1);
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidStatuses(): iterable
    {
        foreach ([null, '', 'invalid', 201, 204, 500, 200.0, true, false, [], '0200', '200foo'] as $index => $status) {
            yield 'invalid '.$index => [$status];
        }
    }

    #[DataProvider('invalidStatuses')]
    public function test_invalid_status_fails_before_listener_side_effects(mixed $status): void
    {
        config(['postmark-webhooks.success_status' => $status]);
        $this->withoutExceptionHandling();
        try {
            $this->postJson('/api/postmark/webhook', Payloads::delivery());
            self::fail('Invalid status must fail.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('postmark-webhooks.success_status must be 200 or 202.', $exception->getMessage());
            Event::assertNotDispatched(PostmarkWebhookReceived::class);
        }
    }

    public function test_invalid_status_is_an_http_failure(): void
    {
        config(['postmark-webhooks.success_status' => 204]);
        $this->postJson('/api/postmark/webhook', Payloads::delivery())->assertStatus(500);
        Event::assertNotDispatched(PostmarkWebhookReceived::class);
    }

    public function test_query_parameters_keep_existing_payload_semantics(): void
    {
        $payload = Payloads::delivery();
        $this->postJson('/api/postmark/webhook?source=relay', $payload)->assertOk();
        Event::assertDispatched(PostmarkWebhookReceived::class, fn (PostmarkWebhookReceived $event): bool => $event->payload === $payload + ['source' => 'relay']);
    }
}
