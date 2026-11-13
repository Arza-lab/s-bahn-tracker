<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Exception\DepartureProviderException;
use App\Application\Exception\PushFailedException;
use App\Application\Port\DepartureProviderInterface;
use App\Application\Port\PushNotifierInterface;
use App\Application\Port\TrackingStateRepositoryInterface;
use App\Application\Port\WalkConditionsProviderInterface;
use App\Domain\CommutePlanner;
use App\Domain\CommuteSettings;
use App\Domain\Model\Decision;
use App\Domain\Model\Situation;
use App\Domain\Model\TrackingState;
use App\Domain\NotificationFactory;
use Psr\Clock\ClockInterface;
use Symfony\Component\Lock\LockFactory;

/** One tracker run: look at the departure board, decide, notify, remember. */
final readonly class CommuteTracker
{
    private const LOCK_NAME = 'commute-tracker';

    /** After this many failed runs in a row the commuter is told once. */
    private const PROVIDER_FAILURES_BEFORE_ALERT = 5;

    public function __construct(
        private DepartureProviderInterface $departures,
        private WalkConditionsProviderInterface $walkConditions,
        private TrackingStateRepositoryInterface $states,
        private PushNotifierInterface $notifier,
        private CommutePlanner $planner,
        private NotificationFactory $notifications,
        private CommuteSettings $settings,
        private ClockInterface $clock,
        private LockFactory $lockFactory,
    ) {
    }

    /** What a run would do right now, without sending or storing anything. */
    public function preview(): Decision
    {
        $now = $this->clock->now()->setTimezone($this->settings->timezone);
        $state = $this->states->load($now->format('Y-m-d'));

        if ($state->isFinished()) {
            return Decision::unchanged($state);
        }

        try {
            $departures = $this->departures->upcoming($now);
        } catch (DepartureProviderException) {
            return $this->afterProviderFailure($state);
        }

        $situation = new Situation($now, $departures, $this->walkConditions->current());

        return $this->planner->decide($state->withProviderFailures(0), $situation);
    }

    /**
     * Sends the notifications and stores the new state.
     *
     * The state is only stored once every notification went out, so a failed
     * push is retried by the next run.
     *
     * @return Decision|null null if another run is still in progress
     *
     * @throws PushFailedException
     */
    public function run(): ?Decision
    {
        $lock = $this->lockFactory->createLock(self::LOCK_NAME);
        if (!$lock->acquire()) {
            return null;
        }

        try {
            $decision = $this->preview();

            foreach ($decision->notifications as $notification) {
                $this->notifier->send($notification);
            }
            $this->states->save($decision->state);

            return $decision;
        } finally {
            $lock->release();
        }
    }

    private function afterProviderFailure(TrackingState $state): Decision
    {
        $failures = $state->providerFailures + 1;
        $notifications = self::PROVIDER_FAILURES_BEFORE_ALERT === $failures
            ? [$this->notifications->providerUnavailable()]
            : [];

        return new Decision($state->withProviderFailures($failures), $notifications);
    }
}
