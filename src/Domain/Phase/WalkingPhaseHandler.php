<?php

declare(strict_types=1);

namespace App\Domain\Phase;

use App\Domain\CommuteSettings;
use App\Domain\Model\Decision;
use App\Domain\Model\Phase;
use App\Domain\Model\Situation;
use App\Domain\Model\TrackingState;
use App\Domain\NotificationFactory;

/** Watches the chosen train while the commuter walks to the station. */
final readonly class WalkingPhaseHandler implements PhaseHandlerInterface
{
    /** How long after departure the train is considered gone. */
    private const DEPARTED_AFTER_SECONDS = 60;

    public function __construct(
        private NotificationFactory $notifications,
        private CommuteSettings $settings,
    ) {
    }

    public function supports(Phase $phase): bool
    {
        return Phase::Walking === $phase;
    }

    public function handle(TrackingState $state, Situation $situation): Decision
    {
        $train = $state->train;
        if (null === $train) {
            throw new \LogicException('A commute in progress needs a tracked train.');
        }

        $departure = $situation->findByTripId($train->tripId);

        if (true === $departure?->cancelled) {
            $alternatives = $situation->runningDeparturesAfter($train->departureAt, $this->settings->connectionsShown());

            return new Decision($state->done(), [$this->notifications->trainCancelled($train, $alternatives)]);
        }

        if (null !== $departure) {
            $shift = abs($departure->time()->getTimestamp() - $train->departureAt->getTimestamp());

            if ($shift >= $this->settings->changeThresholdSeconds()) {
                return new Decision(
                    $state->withTrain($train->withDepartureAt($departure->time())),
                    [$this->notifications->trainDelayed($departure)],
                );
            }
        }

        $goneAt = $train->departureAt->modify(\sprintf('+%d seconds', self::DEPARTED_AFTER_SECONDS));

        return $situation->now > $goneAt ? Decision::unchanged($state->done()) : Decision::unchanged($state);
    }
}
