<?php

declare(strict_types=1);

namespace App\Domain\Model;

final readonly class Departure
{
    public function __construct(
        public string $tripId,
        public string $line,
        public \DateTimeImmutable $plannedAt,
        public ?\DateTimeImmutable $expectedAt,
        public bool $cancelled,
    ) {
    }

    /** The realtime departure if known, the timetable departure otherwise. */
    public function time(): \DateTimeImmutable
    {
        return $this->expectedAt ?? $this->plannedAt;
    }

    public function delayMinutes(): int
    {
        return (int) round(($this->time()->getTimestamp() - $this->plannedAt->getTimestamp()) / 60);
    }
}
