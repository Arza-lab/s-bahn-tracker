<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Application\Port\WalkConditionsProviderInterface;
use App\Domain\Model\WalkConditions;

final readonly class FixedWalkConditionsProvider implements WalkConditionsProviderInterface
{
    private WalkConditions $conditions;

    public function __construct(?WalkConditions $conditions = null)
    {
        $this->conditions = $conditions ?? WalkConditions::normal();
    }

    public function current(): WalkConditions
    {
        return $this->conditions;
    }
}
