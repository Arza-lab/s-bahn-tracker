<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Application\Exception\DepartureProviderException;
use App\Application\Port\DepartureProviderInterface;
use App\Domain\Model\Departure;

final class FixedDepartureProvider implements DepartureProviderInterface
{
    private bool $failing = false;
    private int $callCount = 0;

    /**
     * @param list<Departure> $departures
     */
    public function __construct(
        private readonly array $departures = [],
    ) {
    }

    public function upcoming(\DateTimeImmutable $from): array
    {
        ++$this->callCount;

        if ($this->failing) {
            throw new DepartureProviderException('The departure board is unavailable.');
        }

        return $this->departures;
    }

    public function startFailing(): void
    {
        $this->failing = true;
    }

    public function stopFailing(): void
    {
        $this->failing = false;
    }

    public function callCount(): int
    {
        return $this->callCount;
    }
}
