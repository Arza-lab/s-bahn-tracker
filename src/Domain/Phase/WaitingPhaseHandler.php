<?php

declare(strict_types=1);

namespace App\Domain\Phase;

use App\Domain\CommuteSettings;
use App\Domain\Model\Decision;
use App\Domain\Model\Phase;
use App\Domain\Model\Situation;
use App\Domain\Model\TrackingState;
use App\Domain\NotificationFactory;
use App\Domain\TrainSelector;

/** Announces the first plan of the day once leaving home comes close. */
final readonly class WaitingPhaseHandler implements PhaseHandlerInterface
{
    public function __construct(
        private TrainSelector $trainSelector,
        private NotificationFactory $notifications,
        private CommuteSettings $settings,
    ) {
    }

    public function supports(Phase $phase): bool
    {
        return Phase::Waiting === $phase;
    }

    public function handle(TrackingState $state, Situation $situation): Decision
    {
        $train = $this->trainSelector->select($situation);

        if (null === $train) {
            return $this->reportMissingConnection($state, $situation);
        }

        $announceFrom = $train->leaveAt->modify(\sprintf('-%d seconds', $this->settings->planLeadSeconds()));
        if ($situation->now < $announceFrom) {
            return Decision::unchanged($state);
        }

        return new Decision($state->planned($train), [$this->notifications->plan($train, $situation)]);
    }

    private function reportMissingConnection(TrackingState $state, Situation $situation): Decision
    {
        $earliestLeave = $this->settings->earliestDepartureOn($situation->now)->modify(
            \sprintf('-%d seconds', $this->settings->doorToPlatformSeconds($situation->walkConditions)),
        );

        if ($state->noConnectionReported || $situation->now < $earliestLeave) {
            return Decision::unchanged($state);
        }

        return new Decision($state->withNoConnectionReported(), [$this->notifications->noConnection()]);
    }
}
