<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\Model\Departure;
use App\Domain\Model\Situation;
use App\Domain\Model\WalkConditions;
use App\Domain\TrainSelector;
use App\Tests\Support\CommuteGraph;
use App\Tests\Support\DepartureFixture;
use PHPUnit\Framework\TestCase;

final class TrainSelectorTest extends TestCase
{
    private TrainSelector $selector;

    protected function setUp(): void
    {
        $this->selector = (new CommuteGraph())->selector;
    }

    public function testSelectsTheFirstRunningTrainAfterTheEarliestDeparture(): void
    {
        $train = $this->selector->select($this->situationAt('09:00', DepartureFixture::morningBoard()));

        self::assertNotNull($train);
        self::assertSame('t2', $train->tripId);
        self::assertSame('S26', $train->line);
        self::assertSame('09:51', $train->departureAt->format('H:i'));
        self::assertSame('09:24', $train->leaveAt->format('H:i'));
    }

    public function testSkipsCancelledTrains(): void
    {
        $board = DepartureFixture::morningBoard(DepartureFixture::cancelled('t2', 'S26', '09:51'));

        $train = $this->selector->select($this->situationAt('09:00', $board));

        self::assertNotNull($train);
        self::assertSame('t3', $train->tripId);
    }

    public function testSkipsTrainsBeforeTheEarliestDeparture(): void
    {
        $board = [DepartureFixture::departure('t1', 'S25', '09:41', '09:41')];

        self::assertNull($this->selector->select($this->situationAt('09:00', $board)));
    }

    public function testAcceptsATrainDepartingExactlyAtTheEarliestDeparture(): void
    {
        $board = [DepartureFixture::departure('t0', 'S25', '09:50', '09:50')];

        $train = $this->selector->select($this->situationAt('09:00', $board));

        self::assertNotNull($train);
        self::assertSame('t0', $train->tripId);
    }

    public function testSkipsTrainsWhoseLeaveTimePassedMoreThanTwoMinutesAgo(): void
    {
        $train = $this->selector->select($this->situationAt('09:27', DepartureFixture::morningBoard()));

        self::assertNotNull($train);
        self::assertSame('t3', $train->tripId);
    }

    public function testKeepsTrainsWhoseLeaveTimePassedWithinTwoMinutes(): void
    {
        $train = $this->selector->select($this->situationAt('09:25:30', DepartureFixture::morningBoard()));

        self::assertNotNull($train);
        self::assertSame('t2', $train->tripId);
    }

    public function testUsesTheRealtimeDepartureForTheLeaveTime(): void
    {
        $board = DepartureFixture::morningBoard(DepartureFixture::departure('t2', 'S26', '09:51', '09:55'));

        $train = $this->selector->select($this->situationAt('09:00', $board));

        self::assertNotNull($train);
        self::assertSame('09:55', $train->departureAt->format('H:i'));
        self::assertSame('09:28', $train->leaveAt->format('H:i'));
    }

    public function testReturnsNullWhenNoTrainFits(): void
    {
        $board = [
            DepartureFixture::departure('t1', 'S25', '09:41', '09:41'),
            DepartureFixture::cancelled('t2', 'S26', '09:51'),
        ];

        self::assertNull($this->selector->select($this->situationAt('09:00', $board)));
    }

    /**
     * @param list<Departure> $departures
     */
    private function situationAt(string $time, array $departures): Situation
    {
        return new Situation(DepartureFixture::at($time), $departures, WalkConditions::normal());
    }
}
