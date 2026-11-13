<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Domain\Model\TrackingState;

interface TrackingStateRepositoryInterface
{
    /**
     * The state stored for the given day (Y-m-d), or a fresh one if nothing was stored for it.
     */
    public function load(string $day): TrackingState;

    public function save(TrackingState $state): void;
}
