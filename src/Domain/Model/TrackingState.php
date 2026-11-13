<?php

declare(strict_types=1);

namespace App\Domain\Model;

/** What the tracker remembers between two runs on the same day. */
final readonly class TrackingState
{
    public function __construct(
        public string $day,
        public Phase $phase = Phase::Waiting,
        public ?TrackedTrain $train = null,
        public bool $noConnectionReported = false,
        public int $providerFailures = 0,
    ) {
    }

    public static function startOfDay(string $day): self
    {
        return new self($day);
    }

    public function isFinished(): bool
    {
        return Phase::Done === $this->phase;
    }

    public function planned(TrackedTrain $train): self
    {
        return new self($this->day, Phase::Planned, $train, false, $this->providerFailures);
    }

    public function walking(): self
    {
        return new self($this->day, Phase::Walking, $this->train, $this->noConnectionReported, $this->providerFailures);
    }

    public function done(): self
    {
        return new self($this->day, Phase::Done, $this->train, $this->noConnectionReported, $this->providerFailures);
    }

    public function withTrain(TrackedTrain $train): self
    {
        return new self($this->day, $this->phase, $train, false, $this->providerFailures);
    }

    public function withNoConnectionReported(): self
    {
        return new self($this->day, $this->phase, $this->train, true, $this->providerFailures);
    }

    public function withProviderFailures(int $providerFailures): self
    {
        return new self($this->day, $this->phase, $this->train, $this->noConnectionReported, $providerFailures);
    }
}
