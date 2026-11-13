<?php

declare(strict_types=1);

namespace App\Domain\Model;

/** The train the commuter has been told to catch, and when to leave home for it. */
final readonly class TrackedTrain
{
    public function __construct(
        public string $tripId,
        public string $line,
        public \DateTimeImmutable $departureAt,
        public \DateTimeImmutable $leaveAt,
    ) {
    }

    public function withDepartureAt(\DateTimeImmutable $departureAt): self
    {
        return new self($this->tripId, $this->line, $departureAt, $this->leaveAt);
    }
}
