<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Push;

use App\Domain\Model\Notification;
use App\Infrastructure\Push\SelectablePushNotifier;
use App\Tests\Support\RecordingPushNotifier;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class SelectablePushNotifierTest extends TestCase
{
    public function testItDelegatesToTheConfiguredTransportOnly(): void
    {
        $ntfy = new RecordingPushNotifier();
        $pushover = new RecordingPushNotifier();
        $notification = new Notification('Losgehen', 'Um 10:05 Uhr los.');

        (new SelectablePushNotifier($this->locator(['ntfy' => $ntfy, 'pushover' => $pushover]), 'pushover'))->send($notification);

        self::assertSame([], $ntfy->sent());
        self::assertSame([$notification], $pushover->sent());
    }

    public function testAnUnknownTransportIsAConfigurationError(): void
    {
        $ntfy = new RecordingPushNotifier();
        $notifier = new SelectablePushNotifier($this->locator(['ntfy' => $ntfy]), 'carrier-pigeon');

        try {
            $notifier->send(new Notification('a', 'b'));
            self::fail('Expected a LogicException.');
        } catch (\LogicException $e) {
            self::assertStringContainsString('carrier-pigeon', $e->getMessage());
        }

        self::assertSame([], $ntfy->sent());
    }

    /** @param array<string, object> $services */
    private function locator(array $services): ContainerInterface
    {
        return new class($services) implements ContainerInterface {
            /** @param array<string, object> $services */
            public function __construct(private readonly array $services)
            {
            }

            public function get(string $id): object
            {
                return $this->services[$id] ?? throw new \OutOfBoundsException(\sprintf('Unknown service "%s".', $id));
            }

            public function has(string $id): bool
            {
                return isset($this->services[$id]);
            }
        };
    }
}
