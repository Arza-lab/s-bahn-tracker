<?php

declare(strict_types=1);

namespace App\Domain;

use App\Domain\Model\Decision;
use App\Domain\Model\Phase;
use App\Domain\Model\Situation;
use App\Domain\Model\TrackingState;
use App\Domain\Phase\PhaseHandlerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/** Runs the handler of the current phase and follows phase changes within one run. */
final readonly class CommutePlanner
{
    /** Waiting, Planned, Walking: more transitions in a single run would be a loop. */
    private const MAX_TRANSITIONS_PER_RUN = 3;

    /**
     * @param iterable<PhaseHandlerInterface> $handlers
     */
    public function __construct(
        #[AutowireIterator(PhaseHandlerInterface::TAG)]
        private iterable $handlers,
    ) {
    }

    public function decide(TrackingState $state, Situation $situation): Decision
    {
        $notifications = [];

        for ($transition = 0; $transition < self::MAX_TRANSITIONS_PER_RUN; ++$transition) {
            $handler = $this->handlerFor($state->phase);
            if (null === $handler) {
                break;
            }

            $decision = $handler->handle($state, $situation);
            $phaseChanged = $decision->state->phase !== $state->phase;

            $notifications = [...$notifications, ...$decision->notifications];
            $state = $decision->state;

            if (!$phaseChanged) {
                break;
            }
        }

        return new Decision($state, $notifications);
    }

    private function handlerFor(Phase $phase): ?PhaseHandlerInterface
    {
        foreach ($this->handlers as $handler) {
            if ($handler->supports($phase)) {
                return $handler;
            }
        }

        return null;
    }
}
