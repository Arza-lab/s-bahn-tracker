<?php

declare(strict_types=1);

namespace App\Domain\Model;

/** Everything known about the outside world at one point in time. */
final readonly class Situation
{
    /** @var list<Departure> ordered by departure time */
    public array $departures;

    /**
     * @param iterable<Departure> $departures
     */
    public function __construct(
        public \DateTimeImmutable $now,
        iterable $departures,
        public WalkConditions $walkConditions,
    ) {
        $sorted = [...$departures];
        usort($sorted, static fn (Departure $a, Departure $b): int => $a->time() <=> $b->time());

        $this->departures = $sorted;
    }

    public function findByTripId(string $tripId): ?Departure
    {
        foreach ($this->departures as $departure) {
            if ($departure->tripId === $tripId) {
                return $departure;
            }
        }

        return null;
    }

    /** The first train that actually runs and leaves later than the given time. */
    public function nextRunningDepartureAfter(\DateTimeImmutable $time): ?Departure
    {
        foreach ($this->departures as $departure) {
            if (!$departure->cancelled && $departure->time() > $time) {
                return $departure;
            }
        }

        return null;
    }
}
