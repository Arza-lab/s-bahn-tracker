<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Ntfy;

use App\Application\Exception\PushFailedException;
use App\Domain\Model\Notification;
use App\Infrastructure\Ntfy\NtfyPushNotifier;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class NtfyPushNotifierTest extends TestCase
{
    public function testItPostsTheNotificationAsJson(): void
    {
        $captured = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = ['method' => $method, 'url' => $url, 'options' => $options];

            return new MockResponse('{}');
        });

        (new NtfyPushNotifier($client, 'https://ntfy.test', 'my-topic'))->send(new Notification('Losgehen', 'Um 10:05 Uhr los.'));

        self::assertSame('POST', $captured['method']);
        self::assertSame('https://ntfy.test/', $captured['url']);
        self::assertEquals(10, $captured['options']['timeout']);
        self::assertSame(
            ['topic' => 'my-topic', 'title' => 'Losgehen', 'message' => 'Um 10:05 Uhr los.', 'priority' => 3],
            json_decode($captured['options']['body'], true, 512, \JSON_THROW_ON_ERROR),
        );
    }

    public function testAnUrgentNotificationGetsTheHighestPriority(): void
    {
        $body = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$body): MockResponse {
            $body = json_decode($options['body'], true, 512, \JSON_THROW_ON_ERROR);

            return new MockResponse('{}');
        });

        (new NtfyPushNotifier($client, 'https://ntfy.test', 'my-topic'))->send(new Notification('Jetzt los', 'Sofort!', true));

        self::assertSame(5, $body['priority']);
    }

    public function testANonSuccessStatusBecomesAPushFailedException(): void
    {
        $client = new MockHttpClient(new MockResponse('', ['http_code' => 403]));

        $this->expectException(PushFailedException::class);

        (new NtfyPushNotifier($client, 'https://ntfy.test', 'my-topic'))->send(new Notification('a', 'b'));
    }

    public function testATransportErrorBecomesAPushFailedException(): void
    {
        $client = new MockHttpClient(static function (): never {
            throw new TransportException('offline');
        });

        try {
            (new NtfyPushNotifier($client, 'https://ntfy.test', 'my-topic'))->send(new Notification('a', 'b'));
            self::fail('Expected a PushFailedException.');
        } catch (PushFailedException $e) {
            self::assertInstanceOf(TransportException::class, $e->getPrevious());
        }
    }
}
