<?php

declare(strict_types=1);

namespace App\Domain;

use App\Domain\Model\Situation;
use App\Domain\Model\TrackedTrain;

/** Picks the running trains the commuter can still reach on foot. */
final readonly class TrainSelector
{
    /** A train still counts as reachable if its leave time passed this recently. */
    private const LEAVE_GRACE_SECONDS = 120;

    public function __construct(
        private CommuteSettings $settings,
    ) {
    }

    public function select(Situation $situation): ?TrackedTrain
    {
        return $this->upcoming($situation, 1)[0] ?? null;
    }

    /**
     * The first reachable trains in departure order.
     *
     * @return list<TrackedTrain>
     */
    public function upcoming(Situation $situation, int $limit): array
    {
        $earliest = $this->settings->earliestDepartureOn($situation->now);
        $latestLeave = $situation->now->modify(\sprintf('-%d seconds', self::LEAVE_GRACE_SECONDS));

        $trains = [];
        foreach ($situation->departures as $departure) {
            if (\count($trains) >= $limit) {
                break;
            }

            if ($departure->cancelled || $departure->time() < $earliest) {
                continue;
            }

            $leaveAt = $this->settings->leaveTimeFor($departure, $situation->walkConditions);
            if ($leaveAt >= $latestLeave) {
                $trains[] = new TrackedTrain($departure->tripId, $departure->line, $departure->time(), $leaveAt);
            }
        }

        return $trains;
    }
}
