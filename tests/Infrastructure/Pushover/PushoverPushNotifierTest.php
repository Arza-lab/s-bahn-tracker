<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Pushover;

use App\Application\Exception\PushFailedException;
use App\Domain\Model\Notification;
use App\Infrastructure\Pushover\PushoverPushNotifier;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class PushoverPushNotifierTest extends TestCase
{
    public function testItPostsTheNotificationAsFormData(): void
    {
        $captured = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = ['method' => $method, 'url' => $url, 'options' => $options];

            return new MockResponse('{"status":1}');
        });

        $this->notifier($client)->send(new Notification('Losgehen', 'Um 10:05 Uhr los.'));

        self::assertSame('POST', $captured['method']);
        self::assertSame('https://pushover.test/1/messages.json', $captured['url']);
        self::assertEquals(10, $captured['options']['timeout']);

        parse_str($captured['options']['body'], $fields);
        self::assertSame(
            ['token' => 'app-token', 'user' => 'user-key', 'title' => 'Losgehen', 'message' => 'Um 10:05 Uhr los.', 'priority' => '0'],
            $fields,
        );
    }

    public function testAnUrgentNotificationGetsHighPriority(): void
    {
        $fields = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$fields): MockResponse {
            parse_str($options['body'], $fields);

            return new MockResponse('{"status":1}');
        });

        $this->notifier($client)->send(new Notification('Jetzt los', 'Sofort!', true));

        self::assertSame('1', $fields['priority']);
    }

    public function testANonSuccessStatusBecomesAPushFailedException(): void
    {
        foreach ([400, 429, 500, 503] as $status) {
            $client = new MockHttpClient(new MockResponse('', ['http_code' => $status]));

            try {
                $this->notifier($client)->send(new Notification('a', 'b'));
                self::fail(\sprintf('Expected a PushFailedException for HTTP %d.', $status));
            } catch (PushFailedException $e) {
                self::assertStringContainsString((string) $status, $e->getMessage());
            }
        }
    }

    public function testATransportErrorBecomesAPushFailedException(): void
    {
        $client = new MockHttpClient(static function (): never {
            throw new TransportException('offline');
        });

        try {
            $this->notifier($client)->send(new Notification('a', 'b'));
            self::fail('Expected a PushFailedException.');
        } catch (PushFailedException $e) {
            self::assertInstanceOf(TransportException::class, $e->getPrevious());
        }
    }

    private function notifier(MockHttpClient $client): PushoverPushNotifier
    {
        return new PushoverPushNotifier($client, 'https://pushover.test/1/messages.json', 'app-token', 'user-key');
    }
}
