<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Application\Exception\DepartureProviderException;
use App\Domain\Model\Departure;

interface DepartureProviderInterface
{
    /**
     * Departures of the commuter's line from the home station, starting at the given time.
     *
     * @return list<Departure>
     *
     * @throws DepartureProviderException
     */
    public function upcoming(\DateTimeImmutable $from): array;
}
