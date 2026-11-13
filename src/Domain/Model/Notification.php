<?php

declare(strict_types=1);

namespace App\Domain\Model;

final readonly class Notification
{
    public function __construct(
        public string $title,
        public string $body,
        public bool $urgent = false,
    ) {
    }
}
