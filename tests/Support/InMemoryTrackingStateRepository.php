<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Application\Port\TrackingStateRepositoryInterface;
use App\Domain\Model\TrackingState;

final class InMemoryTrackingStateRepository implements TrackingStateRepositoryInterface
{
    private ?TrackingState $stored = null;
    private int $saveCount = 0;

    public function load(string $day): TrackingState
    {
        if (null !== $this->stored && $this->stored->day === $day) {
            return $this->stored;
        }

        return TrackingState::startOfDay($day);
    }

    public function save(TrackingState $state): void
    {
        $this->stored = $state;
        ++$this->saveCount;
    }

    /** Puts a state into the repository without counting it as a save. */
    public function seed(TrackingState $state): void
    {
        $this->stored = $state;
    }

    public function saveCount(): int
    {
        return $this->saveCount;
    }

    public function lastSaved(): ?TrackingState
    {
        return 0 < $this->saveCount ? $this->stored : null;
    }
}
