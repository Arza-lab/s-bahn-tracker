<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\Model\Departure;
use App\Domain\Model\Situation;
use App\Domain\Model\TrackedTrain;
use App\Domain\Model\WalkConditions;
use App\Tests\Support\CommuteGraph;
use App\Tests\Support\DepartureFixture;
use PHPUnit\Framework\TestCase;

/**
 * The commuter is told about the tracked train and, below "Danach:", about the trains after it.
 *
 * Leaving takes 27 minutes before departure: S26 09:51 means 09:24, S25 10:01 means 09:34, and so on.
 */
final class NotificationFactoryTest extends TestCase
{
    public function testPlanListsTheTwoFollowingTrainsWithTheirLeaveTimes(): void
    {
        $body = $this->planBody(DepartureFixture::morningBoard());

        self::assertSame(
            "S26 um 09:51 (pünktlich). Losgehen um 09:24.\nDanach:\nS25 um 10:01 (pünktlich), los 09:34\nS26 um 10:11 (pünktlich), los 09:44",
            $body,
        );
    }

    public function testPlanShowsAtMostThreeTrainsInTotal(): void
    {
        $body = $this->planBody(DepartureFixture::longBoard());

        self::assertStringContainsString('S26 um 10:11 (pünktlich), los 09:44', $body);
        self::assertFalse(str_contains($body, '10:21'));
        self::assertFalse(str_contains($body, '10:31'));
    }

    public function testPlanSkipsACancelledFollower(): void
    {
        $board = DepartureFixture::longBoard(DepartureFixture::cancelled('t3', 'S25', '10:01'));

        $body = $this->planBody($board);

        self::assertFalse(str_contains($body, '10:01'));
        self::assertStringContainsString("Danach:\nS26 um 10:11 (pünktlich), los 09:44\nS25 um 10:21 (pünktlich), los 09:54", $body);
    }

    public function testPlanShowsTheDelayAndTheShiftedLeaveTimeOfAFollower(): void
    {
        $board = DepartureFixture::morningBoard(DepartureFixture::departure('t3', 'S25', '10:01', '10:04'));

        $body = $this->planBody($board);

        self::assertStringContainsString("Danach:\nS25 um 10:04 (+3 Min), los 09:37\n", $body);
    }

    public function testPlanWithoutFollowersHasNoDanachPart(): void
    {
        $body = $this->planBody([DepartureFixture::departure('t2', 'S26', '09:51', '09:51')]);

        self::assertSame('S26 um 09:51 (pünktlich). Losgehen um 09:24.', $body);
    }

    public function testPlanShowsOnlyTheTrackedTrainWhenOneConnectionIsShown(): void
    {
        $body = $this->planBody(DepartureFixture::longBoard(), new CommuteGraph(1));

        self::assertSame('S26 um 09:51 (pünktlich). Losgehen um 09:24.', $body);
        self::assertFalse(str_contains($body, 'Danach'));
    }

    public function testPlanKeepsTheWeatherNoteOnTheFirstLine(): void
    {
        $situation = $this->situation(DepartureFixture::morningBoard(), new WalkConditions(3, 'Regen'));
        $graph = new CommuteGraph();
        $train = $graph->selector->select($situation);
        self::assertNotNull($train);

        $lines = explode("\n", $graph->notifications->plan($train, $situation)->body);

        self::assertSame('S26 um 09:51 (pünktlich). Losgehen um 09:21. Regen: +3 Min Fußweg eingerechnet.', $lines[0]);
        self::assertSame('Danach:', $lines[1]);
        self::assertSame('S25 um 10:01 (pünktlich), los 09:31', $lines[2]);
    }

    public function testPlanChangedAlsoListsTheFollowers(): void
    {
        $graph = new CommuteGraph();
        $previous = $graph->selector->select($this->situation(DepartureFixture::longBoard()));
        $situation = $this->situation(DepartureFixture::longBoard(DepartureFixture::cancelled('t2', 'S26', '09:51')));
        $current = $graph->selector->select($situation);
        self::assertNotNull($previous);
        self::assertNotNull($current);

        $notification = $graph->notifications->planChanged($previous, $current, $situation);

        self::assertSame('S26 um 09:51 fällt aus', $notification->title);
        self::assertSame(
            "S25 um 10:01 (pünktlich). Losgehen um 09:34.\nDanach:\nS26 um 10:11 (pünktlich), los 09:44\nS25 um 10:21 (pünktlich), los 09:54",
            $notification->body,
        );
    }

    public function testCancellationListsAllAlternatives(): void
    {
        $graph = new CommuteGraph();
        $train = new TrackedTrain('t2', 'S26', DepartureFixture::at('09:51'), DepartureFixture::at('09:24'));
        $alternatives = array_slice(DepartureFixture::longBoard(), 2, 3);

        $notification = $graph->notifications->trainCancelled($train, $alternatives);

        self::assertSame(
            'S26 um 09:51 fällt aus. Nächste: S25 um 10:01 (pünktlich), S26 um 10:11 (pünktlich), S25 um 10:21 (pünktlich).',
            $notification->body,
        );
    }

    public function testCancellationWithoutAlternativesOnlyReportsTheCancellation(): void
    {
        $train = new TrackedTrain('t2', 'S26', DepartureFixture::at('09:51'), DepartureFixture::at('09:24'));

        $notification = (new CommuteGraph())->notifications->trainCancelled($train, []);

        self::assertSame('S26 um 09:51 fällt aus.', $notification->body);
    }

    /**
     * @param list<Departure> $departures
     */
    private function planBody(array $departures, ?CommuteGraph $graph = null): string
    {
        $graph ??= new CommuteGraph();
        $situation = $this->situation($departures);
        $train = $graph->selector->select($situation);
        self::assertNotNull($train);

        return $graph->notifications->plan($train, $situation)->body;
    }

    /**
     * @param list<Departure> $departures
     */
    private function situation(array $departures, ?WalkConditions $conditions = null): Situation
    {
        return new Situation(DepartureFixture::at('09:00'), $departures, $conditions ?? WalkConditions::normal());
    }
}
