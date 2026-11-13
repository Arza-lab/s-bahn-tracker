<?php

declare(strict_types=1);

namespace App\Domain\Model;

/** How much longer the walk to the station takes right now, and why. */
final readonly class WalkConditions
{
    public function __construct(
        public int $extraMinutes,
        public ?string $reason = null,
    ) {
    }

    public static function normal(): self
    {
        return new self(0);
    }

    public function slowsWalkDown(): bool
    {
        return $this->extraMinutes > 0;
    }
}
