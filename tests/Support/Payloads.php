<?php

namespace Citricguy\PostmarkWebhooks\Tests\Support;

final class Payloads
{
    /** @return array<string, mixed> */
    public static function delivery(): array
    {
        return [
            'RecordType' => 'Delivery',
            'Recipient' => 'recipient@example.com',
            'MessageID' => '883953f4-6105-42a2-a16a-77a8eac79483',
            'ServerID' => 23,
            'MessageStream' => 'outbound',
            'Tag' => 'receipt',
            'DeliveredAt' => '2026-08-11T16:33:54Z',
            'Details' => 'Test delivery accepted',
            'Metadata' => ['order_id' => '00123', 'nested' => ['keep' => true]],
        ];
    }

    /** @return iterable<string, array{array<string, mixed>, string, string, ?string}> */
    public static function webhooks(): iterable
    {
        $base = self::delivery();
        foreach (['Delivery', 'Bounce', 'SpamComplaint', 'Open', 'Click', 'SubscriptionChange'] as $type) {
            $payload = $base;
            $payload['RecordType'] = $type;
            if (in_array($type, ['Bounce', 'SpamComplaint'], true)) {
                unset($payload['Recipient'], $payload['DeliveredAt']);
                $payload['Email'] = 'recipient@example.com';
                $payload['ID'] = 123456;
                $payload['Type'] = $type === 'Bounce' ? 'HardBounce' : 'SpamComplaint';
                $payload['TypeCode'] = $type === 'Bounce' ? 1 : 100001;
                $payload['BouncedAt'] = '2026-08-11T16:33:54Z';
                $payload['Inactive'] = true;
            } elseif ($type === 'Open' || $type === 'Click') {
                $payload['ReceivedAt'] = '2026-08-11T16:33:54Z';
                $payload['Client'] = ['Name' => 'Test client', 'Family' => 'Browser'];
                $payload['OS'] = ['Name' => 'Test OS'];
                $payload['Geo'] = ['CountryISOCode' => 'US'];
                $payload['OriginalLink'] = 'https://example.com/orders/123';
            } elseif ($type === 'SubscriptionChange') {
                $payload['MessageID'] = null;
                $payload['ChangedAt'] = '2026-08-11T16:33:54Z';
                $payload['Origin'] = 'Customer';
                $payload['SuppressSending'] = true;
                $payload['SuppressionReason'] = 'ManualSuppression';
            }
            yield $type => [$payload, 'recipient@example.com', $type, $type === 'SubscriptionChange' ? null : '883953f4-6105-42a2-a16a-77a8eac79483'];
        }
    }
}
