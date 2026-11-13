<?php

declare(strict_types=1);

namespace App\Domain\Model;

/** The outcome of one tracker run: the new state and what to tell the commuter. */
final readonly class Decision
{
    /**
     * @param list<Notification> $notifications
     */
    public function __construct(
        public TrackingState $state,
        public array $notifications = [],
    ) {
    }

    public static function unchanged(TrackingState $state): self
    {
        return new self($state);
    }
}
