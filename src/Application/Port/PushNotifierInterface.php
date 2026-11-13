<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Application\Exception\PushFailedException;
use App\Domain\Model\Notification;

interface PushNotifierInterface
{
    /**
     * @throws PushFailedException
     */
    public function send(Notification $notification): void;
}
