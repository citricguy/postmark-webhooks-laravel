<?php

namespace Citricguy\PostmarkWebhooks\Tests\Feature;

use Citricguy\PostmarkWebhooks\Events\PostmarkWebhookReceived;
use Citricguy\PostmarkWebhooks\Tests\Support\Payloads;
use Citricguy\PostmarkWebhooks\Tests\Support\RecordingQueuedListener;
use Citricguy\PostmarkWebhooks\Tests\TestCase;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use RuntimeException;

class ListenerTest extends TestCase
{
    protected function tearDown(): void
    {
        RecordingQueuedListener::$received = null;
        parent::tearDown();
    }

    public function test_listeners_run_synchronously_and_duplicates_are_not_suppressed(): void
    {
        $received = [];
        Event::listen(PostmarkWebhookReceived::class, function (PostmarkWebhookReceived $event) use (&$received): void {
            $received[] = $event;
        });
        $payload = Payloads::delivery();
        foreach ([1, 2] as $count) {
            $this->postJson('/api/postmark/webhook', $payload)->assertOk();
            self::assertCount($count, $received);
        }
        foreach ($received as $event) {
            self::assertSame($payload, $event->payload);
            self::assertSame('recipient@example.com', $event->email);
        }
    }

    public function test_listener_exceptions_are_not_acknowledged_as_success(): void
    {
        Event::listen(PostmarkWebhookReceived::class, function (): never {
            throw new RuntimeException('Listener failed');
        });
        $this->postJson('/api/postmark/webhook', Payloads::delivery())->assertStatus(500);
    }

    public function test_original_listener_exception_propagates(): void
    {
        $this->withoutExceptionHandling();
        Event::listen(PostmarkWebhookReceived::class, function (): never {
            throw new RuntimeException('Listener failed');
        });
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Listener failed');
        $this->postJson('/api/postmark/webhook', Payloads::delivery());
    }

    public function test_queued_listener_job_survives_serialization_and_runs(): void
    {
        Queue::fake();
        Event::listen(PostmarkWebhookReceived::class, RecordingQueuedListener::class);
        $payload = Payloads::delivery();
        $this->postJson('/api/postmark/webhook', $payload)->assertOk();
        self::assertNull(RecordingQueuedListener::$received);
        Queue::assertPushed(CallQueuedListener::class, 1);
        Queue::assertPushed(CallQueuedListener::class, function (CallQueuedListener $job) use ($payload): bool {
            self::assertSame(RecordingQueuedListener::class, $job->class);
            $restored = unserialize(serialize($job));
            self::assertInstanceOf(CallQueuedListener::class, $restored);
            $restored->setJob(new SyncJob($this->application(), '{}', 'sync', 'default'));
            $restored->handle($this->application());
            self::assertInstanceOf(PostmarkWebhookReceived::class, RecordingQueuedListener::$received);
            self::assertSame($payload, RecordingQueuedListener::$received->payload);
            self::assertSame('Delivery', RecordingQueuedListener::$received->recordType);

            return true;
        });
    }

    public function test_public_constructor_named_arguments_and_mutable_properties_remain_compatible(): void
    {
        $event = new PostmarkWebhookReceived(email: 'original@example.com', recordType: 'Delivery', messageId: null, payload: Payloads::delivery());
        $event->email = 'changed@example.com';
        $event->recordType = 'Custom';
        $event->messageId = 'replacement';
        $event->payload['consumer'] = true;
        $restored = unserialize(serialize($event));
        self::assertEquals($event, $restored);
        self::assertSame('changed@example.com', $event->email);
        self::assertSame('Custom', $event->recordType);
        self::assertSame('replacement', $event->messageId);
        self::assertTrue($event->payload['consumer']);
    }
}
