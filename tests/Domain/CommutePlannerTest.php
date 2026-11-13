<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\CommutePlanner;
use App\Domain\Model\Decision;
use App\Domain\Model\Departure;
use App\Domain\Model\Notification;
use App\Domain\Model\Phase;
use App\Domain\Model\Situation;
use App\Domain\Model\TrackingState;
use App\Domain\Model\WalkConditions;
use App\Tests\Support\CommuteGraph;
use App\Tests\Support\DepartureFixture;
use PHPUnit\Framework\TestCase;

/**
 * Drives the planner through a Monday morning by feeding each returned state into the next run.
 *
 * Door to platform takes 27 minutes (25 walk + 2 buffer), so the 09:51 train means leaving at 09:24.
 */
final class CommutePlannerTest extends TestCase
{
    private CommutePlanner $planner;
    private TrackingState $state;

    protected function setUp(): void
    {
        $this->planner = (new CommuteGraph())->planner;
        $this->state = TrackingState::startOfDay(DepartureFixture::DAY);
    }

    public function testNormalMorningFromPlanToLeavingToDone(): void
    {
        $board = DepartureFixture::morningBoard();

        $tooEarly = $this->decideAt('09:00', $board);
        self::assertCount(0, $tooEarly->notifications);
        self::assertSame(Phase::Waiting, $tooEarly->state->phase);

        $plan = $this->decideAt('09:04', $board);
        $planNotification = $this->onlyNotification($plan);
        self::assertSame('Dein Plan', $planNotification->title);
        self::assertFalse($planNotification->urgent);
        self::assertStringContainsString('S26 um 09:51', $planNotification->body);
        self::assertStringContainsString('pünktlich', $planNotification->body);
        self::assertStringContainsString('Losgehen um 09:24', $planNotification->body);
        self::assertSame(Phase::Planned, $plan->state->phase);

        self::assertCount(0, $this->decideAt('09:10', $board)->notifications);

        $leave = $this->decideAt('09:23', $board);
        $leaveNotification = $this->onlyNotification($leave);
        self::assertSame('Jetzt losgehen', $leaveNotification->title);
        self::assertTrue($leaveNotification->urgent);
        self::assertSame(Phase::Walking, $leave->state->phase);

        self::assertCount(0, $this->decideAt('09:40', $board)->notifications);

        $done = $this->decideAt('09:53', $board);
        self::assertCount(0, $done->notifications);
        self::assertSame(Phase::Done, $done->state->phase);
    }

    public function testCancellationBeforeLeavingSwitchesToTheNextTrain(): void
    {
        $this->announcePlan();
        $board = DepartureFixture::morningBoard(DepartureFixture::cancelled('t2', 'S26', '09:51'));

        $decision = $this->decideAt('09:15', $board);

        $notification = $this->onlyNotification($decision);
        self::assertTrue($notification->urgent);
        self::assertStringContainsString('S26 um 09:51 fällt aus', $notification->title);
        self::assertStringContainsString('S25 um 10:01', $notification->body);
        self::assertStringContainsString('Losgehen um 09:34', $notification->body);
        self::assertSame(Phase::Planned, $decision->state->phase);
        self::assertSame('t3', $decision->state->train?->tripId);
    }

    public function testDelayBeforeLeavingChangesThePlan(): void
    {
        $this->announcePlan();
        $board = DepartureFixture::morningBoard(DepartureFixture::departure('t2', 'S26', '09:51', '09:55'));

        $decision = $this->decideAt('09:10', $board);

        $notification = $this->onlyNotification($decision);
        self::assertSame('Plan geändert', $notification->title);
        self::assertStringContainsString('+4 Min', $notification->body);
        self::assertStringContainsString('Losgehen um 09:28', $notification->body);
        self::assertSame(Phase::Planned, $decision->state->phase);
    }

    public function testOneMinuteDelayBeforeLeavingIsIgnored(): void
    {
        $this->announcePlan();
        $board = DepartureFixture::morningBoard(DepartureFixture::departure('t2', 'S26', '09:51', '09:52'));

        $decision = $this->decideAt('09:10', $board);

        self::assertCount(0, $decision->notifications);
        self::assertSame('09:24', $decision->state->train?->leaveAt->format('H:i'));
    }

    public function testDelayWhileWalkingIsReportedOnce(): void
    {
        $this->startWalking();
        $board = DepartureFixture::morningBoard(DepartureFixture::departure('t2', 'S26', '09:51', '09:55'));

        $delayed = $this->decideAt('09:30', $board);

        $notification = $this->onlyNotification($delayed);
        self::assertSame('Verspätung', $notification->title);
        self::assertFalse($notification->urgent);
        self::assertStringContainsString('09:55', $notification->body);
        self::assertStringContainsString('+4 Min', $notification->body);
        self::assertSame(Phase::Walking, $delayed->state->phase);

        $nextRun = $this->decideAt('09:31', $board);
        self::assertCount(0, $nextRun->notifications);
        self::assertSame(Phase::Walking, $nextRun->state->phase);
    }

    public function testCancellationWhileWalkingNamesTheNextTrainAndFinishesTheDay(): void
    {
        $this->startWalking();
        $board = DepartureFixture::morningBoard(DepartureFixture::cancelled('t2', 'S26', '09:51'));

        $decision = $this->decideAt('09:30', $board);

        $notification = $this->onlyNotification($decision);
        self::assertSame('Zug fällt aus', $notification->title);
        self::assertTrue($notification->urgent);
        self::assertStringContainsString('S26 um 09:51 fällt aus', $notification->body);
        self::assertStringContainsString('S25 um 10:01', $notification->body);
        self::assertSame(Phase::Done, $decision->state->phase);
    }

    public function testMissingConnectionIsReportedOnceFromTheEarliestLeaveTime(): void
    {
        $board = array_map(
            static fn (Departure $departure): Departure => DepartureFixture::cancelled($departure->tripId, $departure->line, $departure->plannedAt->format('H:i')),
            DepartureFixture::morningBoard(),
        );

        self::assertCount(0, $this->decideAt('09:00', $board)->notifications);
        self::assertCount(0, $this->decideAt('09:22', $board)->notifications);

        $notification = $this->onlyNotification($this->decideAt('09:23', $board));
        self::assertSame('Keine Verbindung', $notification->title);
        self::assertTrue($notification->urgent);

        self::assertCount(0, $this->decideAt('09:24', $board)->notifications);
        self::assertCount(0, $this->decideAt('09:30', $board)->notifications);
    }

    public function testRainMovesTheLeaveTimeForwardAndIsMentionedInThePlan(): void
    {
        $decision = $this->decideAt('09:01', DepartureFixture::morningBoard(), new WalkConditions(3, 'Regen'));

        $notification = $this->onlyNotification($decision);
        self::assertSame('Dein Plan', $notification->title);
        self::assertStringContainsString('Losgehen um 09:21', $notification->body);
        self::assertStringContainsString('Regen', $notification->body);
        self::assertSame('09:21', $decision->state->train?->leaveAt->format('H:i'));
    }

    public function testFirstRunShortlyBeforeLeavingAnnouncesPlanAndLeavesInOneRun(): void
    {
        $decision = $this->decideAt('09:23:30', DepartureFixture::morningBoard());

        self::assertSame(['Dein Plan', 'Jetzt losgehen'], $this->titlesOf($decision));
        self::assertSame(Phase::Walking, $decision->state->phase);
    }

    /**
     * @param list<Departure> $departures
     */
    private function decideAt(string $time, array $departures, ?WalkConditions $conditions = null): Decision
    {
        $situation = new Situation(DepartureFixture::at($time), $departures, $conditions ?? WalkConditions::normal());
        $decision = $this->planner->decide($this->state, $situation);
        $this->state = $decision->state;

        return $decision;
    }

    private function announcePlan(): void
    {
        $this->decideAt('09:04', DepartureFixture::morningBoard());
    }

    private function startWalking(): void
    {
        $this->announcePlan();
        $this->decideAt('09:23', DepartureFixture::morningBoard());
    }

    private function onlyNotification(Decision $decision): Notification
    {
        self::assertCount(1, $decision->notifications);

        return $decision->notifications[0];
    }

    /**
     * @return list<string>
     */
    private function titlesOf(Decision $decision): array
    {
        return array_map(static fn (Notification $notification): string => $notification->title, $decision->notifications);
    }
}
