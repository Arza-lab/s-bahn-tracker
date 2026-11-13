<?php

declare(strict_types=1);

namespace App\Domain\Phase;

use App\Domain\CommuteSettings;
use App\Domain\Model\Decision;
use App\Domain\Model\Phase;
use App\Domain\Model\Situation;
use App\Domain\Model\TrackedTrain;
use App\Domain\Model\TrackingState;
use App\Domain\NotificationFactory;
use App\Domain\TrainSelector;

/** Keeps the announced plan up to date and says when it is time to leave. */
final readonly class PlannedPhaseHandler implements PhaseHandlerInterface
{
    /** The tracker runs once a minute, so "leave now" may fire this much early. */
    private const RUN_INTERVAL_SECONDS = 60;

    public function __construct(
        private TrainSelector $trainSelector,
        private NotificationFactory $notifications,
        private CommuteSettings $settings,
    ) {
    }

    public function supports(Phase $phase): bool
    {
        return Phase::Planned === $phase;
    }

    public function handle(TrackingState $state, Situation $situation): Decision
    {
        $planned = $state->train;
        if (null === $planned) {
            throw new \LogicException('A planned commute needs a tracked train.');
        }

        $current = $this->trainSelector->select($situation);
        if (null === $current) {
            return $state->noConnectionReported
                ? Decision::unchanged($state)
                : new Decision($state->withNoConnectionReported(), [$this->notifications->noConnection()]);
        }

        $notifications = [];
        if ($this->planDiffers($planned, $current)) {
            $notifications[] = $this->notifications->planChanged($planned, $current, $situation);
            $state = $state->withTrain($current);
            $planned = $current;
        }

        $leaveFrom = $planned->leaveAt->modify(\sprintf('-%d seconds', self::RUN_INTERVAL_SECONDS));
        if ($situation->now >= $leaveFrom) {
            $notifications[] = $this->notifications->leaveNow($planned, $situation);
            $state = $state->walking();
        }

        return new Decision($state, $notifications);
    }

    private function planDiffers(TrackedTrain $planned, TrackedTrain $current): bool
    {
        if ($planned->tripId !== $current->tripId) {
            return true;
        }

        $shift = abs($current->leaveAt->getTimestamp() - $planned->leaveAt->getTimestamp());

        return $shift >= $this->settings->changeThresholdSeconds();
    }
}
