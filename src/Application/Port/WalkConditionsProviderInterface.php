<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Domain\Model\WalkConditions;

interface WalkConditionsProviderInterface
{
    /** Never throws: unknown conditions count as normal conditions. */
    public function current(): WalkConditions;
}
