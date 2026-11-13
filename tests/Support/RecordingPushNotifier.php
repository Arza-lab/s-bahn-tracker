<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Application\Exception\PushFailedException;
use App\Application\Port\PushNotifierInterface;
use App\Domain\Model\Notification;

final class RecordingPushNotifier implements PushNotifierInterface
{
    private bool $failing = false;

    /** @var list<Notification> */
    private array $sent = [];

    public function send(Notification $notification): void
    {
        if ($this->failing) {
            throw new PushFailedException('The push service rejected the notification.');
        }

        $this->sent[] = $notification;
    }

    public function startFailing(): void
    {
        $this->failing = true;
    }

    public function stopFailing(): void
    {
        $this->failing = false;
    }

    /** @return list<Notification> */
    public function sent(): array
    {
        return $this->sent;
    }

    /** @return list<string> */
    public function sentTitles(): array
    {
        return array_map(static fn (Notification $notification): string => $notification->title, $this->sent);
    }
}
