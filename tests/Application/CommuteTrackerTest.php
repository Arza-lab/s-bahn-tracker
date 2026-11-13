<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Application\CommuteTracker;
use App\Application\Exception\PushFailedException;
use App\Domain\Model\Phase;
use App\Domain\Model\TrackingState;
use App\Tests\Support\CommuteGraph;
use App\Tests\Support\DepartureFixture;
use App\Tests\Support\FixedDepartureProvider;
use App\Tests\Support\FixedWalkConditionsProvider;
use App\Tests\Support\InMemoryTrackingStateRepository;
use App\Tests\Support\RecordingPushNotifier;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

final class CommuteTrackerTest extends TestCase
{
    private FixedDepartureProvider $departures;
    private InMemoryTrackingStateRepository $states;
    private RecordingPushNotifier $notifier;
    private LockFactory $lockFactory;

    protected function setUp(): void
    {
        $this->departures = new FixedDepartureProvider(DepartureFixture::morningBoard());
        $this->states = new InMemoryTrackingStateRepository();
        $this->notifier = new RecordingPushNotifier();
        $this->lockFactory = new LockFactory(new InMemoryStore());
    }

    public function testRunSendsThePlanAndSavesTheState(): void
    {
        $decision = $this->trackerAt('09:04')->run();

        self::assertNotNull($decision);
        self::assertSame(['Dein Plan'], $this->notifier->sentTitles());
        self::assertSame(1, $this->states->saveCount());
        self::assertSame(Phase::Planned, $this->states->lastSaved()?->phase);
    }

    public function testRunAMinuteLaterSendsNothingNew(): void
    {
        $this->trackerAt('09:04')->run();

        $decision = $this->trackerAt('09:05')->run();

        self::assertNotNull($decision);
        self::assertCount(0, $decision->notifications);
        self::assertSame(['Dein Plan'], $this->notifier->sentTitles());
        self::assertSame(2, $this->states->saveCount());
    }

    public function testPreviewNeitherSendsNorSaves(): void
    {
        $decision = $this->trackerAt('09:04')->preview();

        self::assertCount(1, $decision->notifications);
        self::assertSame(Phase::Planned, $decision->state->phase);
        self::assertCount(0, $this->notifier->sent());
        self::assertSame(0, $this->states->saveCount());
    }

    public function testFinishedDayDoesNotAskTheDepartureProvider(): void
    {
        $this->states->seed(new TrackingState(DepartureFixture::DAY, Phase::Done));

        $decision = $this->trackerAt('09:55')->run();

        self::assertNotNull($decision);
        self::assertCount(0, $decision->notifications);
        self::assertSame(0, $this->departures->callCount());
    }

    public function testProviderFailuresAreCountedInTheState(): void
    {
        $this->departures->startFailing();

        $this->trackerAt('09:00')->run();
        $this->trackerAt('09:01')->run();

        self::assertSame(2, $this->states->lastSaved()?->providerFailures);
        self::assertCount(0, $this->notifier->sent());
    }

    public function testFifthConsecutiveProviderFailureNotifiesExactlyOnce(): void
    {
        $this->departures->startFailing();

        $this->runFailingMinutes(4);
        self::assertCount(0, $this->notifier->sent());

        $this->trackerAt('09:04')->run();
        self::assertCount(1, $this->notifier->sent());
        self::assertSame('S-Bahn-Wecker', $this->notifier->sent()[0]->title);
        self::assertTrue($this->notifier->sent()[0]->urgent);

        $this->trackerAt('09:05')->run();
        self::assertCount(1, $this->notifier->sent());
        self::assertSame(6, $this->states->lastSaved()?->providerFailures);
    }

    public function testSuccessfulRunResetsTheFailureCounter(): void
    {
        $this->departures->startFailing();
        $this->runFailingMinutes(4);
        $this->departures->stopFailing();

        $this->trackerAt('09:04')->run();

        self::assertSame(0, $this->states->lastSaved()?->providerFailures);
        self::assertSame(['Dein Plan'], $this->notifier->sentTitles());
    }

    public function testFailureCounterStartsOverAfterASuccessfulRun(): void
    {
        $this->departures->startFailing();
        $this->runFailingMinutes(4);
        $this->departures->stopFailing();
        $this->trackerAt('09:04')->run();
        $this->departures->startFailing();

        $this->trackerAt('09:05')->run();
        $this->trackerAt('09:06')->run();
        $this->trackerAt('09:07')->run();
        $this->trackerAt('09:08')->run();

        self::assertSame(4, $this->states->lastSaved()?->providerFailures);
        self::assertSame(['Dein Plan'], $this->notifier->sentTitles());
    }

    public function testPushFailureIsPropagated(): void
    {
        $this->notifier->startFailing();

        $this->expectException(PushFailedException::class);

        $this->trackerAt('09:04')->run();
    }

    public function testPushFailureLeavesTheStateUnsaved(): void
    {
        $this->notifier->startFailing();

        self::assertTrue($this->runFailsToPush('09:04'));
        self::assertSame(0, $this->states->saveCount());
        self::assertNull($this->states->lastSaved());
    }

    public function testFailedPushIsRetriedByTheNextRun(): void
    {
        $this->notifier->startFailing();
        $this->runFailsToPush('09:04');
        $this->notifier->stopFailing();

        $this->trackerAt('09:05')->run();

        self::assertSame(['Dein Plan'], $this->notifier->sentTitles());
        self::assertSame(1, $this->states->saveCount());
    }

    public function testRunReturnsNullWhileAnotherRunHoldsTheLock(): void
    {
        $lock = $this->lockFactory->createLock('commute-tracker');
        self::assertTrue($lock->acquire());

        $decision = $this->trackerAt('09:04')->run();

        self::assertNull($decision);
        self::assertSame(0, $this->departures->callCount());
        self::assertCount(0, $this->notifier->sent());
        self::assertSame(0, $this->states->saveCount());
    }

    public function testRunReleasesTheLockWhenThePushFails(): void
    {
        $this->notifier->startFailing();
        $this->runFailsToPush('09:04');

        $lock = $this->lockFactory->createLock('commute-tracker');

        self::assertTrue($lock->acquire());
    }

    private function trackerAt(string $time): CommuteTracker
    {
        $graph = new CommuteGraph();

        return new CommuteTracker(
            $this->departures,
            new FixedWalkConditionsProvider(),
            $this->states,
            $this->notifier,
            $graph->planner,
            $graph->notifications,
            $graph->settings,
            new MockClock(\sprintf('%s %s:00', DepartureFixture::DAY, $time), DepartureFixture::TIMEZONE),
            $this->lockFactory,
        );
    }

    /** Runs the tracker and tells whether it failed to push. */
    private function runFailsToPush(string $time): bool
    {
        try {
            $this->trackerAt($time)->run();
        } catch (PushFailedException) {
            return true;
        }

        return false;
    }

    /** Runs the tracker once per minute from 09:00 with a failing departure provider. */
    private function runFailingMinutes(int $count): void
    {
        for ($minute = 0; $minute < $count; ++$minute) {
            $this->trackerAt(\sprintf('09:%02d', $minute))->run();
        }
    }
}
