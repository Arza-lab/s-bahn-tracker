<?php

declare(strict_types=1);

namespace App\Tests\Domain\Model;

use App\Domain\Model\Departure;
use App\Domain\Model\Situation;
use App\Domain\Model\WalkConditions;
use App\Tests\Support\DepartureFixture;
use PHPUnit\Framework\TestCase;

final class SituationTest extends TestCase
{
    public function testRunningDeparturesAfterRespectsTheLimit(): void
    {
        $situation = $this->situation(DepartureFixture::longBoard());

        self::assertSame(['t3', 't4'], $this->tripIds($situation->runningDeparturesAfter(DepartureFixture::at('09:51'), 2)));
    }

    public function testRunningDeparturesAfterSkipsCancelledTrainsAndTheGivenTime(): void
    {
        $situation = $this->situation(DepartureFixture::longBoard(DepartureFixture::cancelled('t3', 'S25', '10:01')));

        self::assertSame(['t4', 't5', 't6'], $this->tripIds($situation->runningDeparturesAfter(DepartureFixture::at('09:51'), 10)));
    }

    public function testRunningDeparturesAfterReturnsNothingWhenNoTrainFollows(): void
    {
        $situation = $this->situation(DepartureFixture::morningBoard());

        self::assertSame([], $situation->runningDeparturesAfter(DepartureFixture::at('10:11'), 3));
    }

    /**
     * @param list<Departure> $departures
     */
    private function situation(array $departures): Situation
    {
        return new Situation(DepartureFixture::at('09:00'), $departures, WalkConditions::normal());
    }

    /**
     * @param list<Departure> $departures
     *
     * @return list<string>
     */
    private function tripIds(array $departures): array
    {
        return array_map(static fn (Departure $departure): string => $departure->tripId, $departures);
    }
}
