<?php

declare(strict_types=1);

namespace App\Domain;

use App\Domain\Model\Departure;
use App\Domain\Model\WalkConditions;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class CommuteSettings
{
    public \DateTimeZone $timezone;

    public function __construct(
        #[Autowire(env: 'COMMUTE_TIMEZONE')]
        string $timezone,
        #[Autowire(env: 'COMMUTE_EARLIEST_DEPARTURE')]
        private string $earliestDeparture,
        #[Autowire(env: 'int:COMMUTE_WALK_MINUTES')]
        private int $walkMinutes,
        #[Autowire(env: 'int:COMMUTE_BUFFER_MINUTES')]
        private int $bufferMinutes,
        #[Autowire(env: 'int:COMMUTE_PLAN_LEAD_MINUTES')]
        private int $planLeadMinutes,
        #[Autowire(env: 'int:COMMUTE_CHANGE_THRESHOLD_MINUTES')]
        private int $changeThresholdMinutes,
    ) {
        $this->timezone = new \DateTimeZone($timezone);
    }

    /** Trains leaving before this time of day are ignored. */
    public function earliestDepartureOn(\DateTimeImmutable $day): \DateTimeImmutable
    {
        $localDay = $day->setTimezone($this->timezone)->format('Y-m-d');
        $earliest = \DateTimeImmutable::createFromFormat('!Y-m-d H:i', $localDay.' '.$this->earliestDeparture, $this->timezone);

        if (false === $earliest) {
            throw new \InvalidArgumentException(\sprintf('Earliest departure "%s" must be given as HH:MM.', $this->earliestDeparture));
        }

        return $earliest;
    }

    /** When to leave home to catch the given departure. */
    public function leaveTimeFor(Departure $departure, WalkConditions $conditions): \DateTimeImmutable
    {
        return $departure->time()->modify(\sprintf('-%d seconds', $this->doorToPlatformSeconds($conditions)));
    }

    public function doorToPlatformSeconds(WalkConditions $conditions): int
    {
        return ($this->walkMinutes + $conditions->extraMinutes + $this->bufferMinutes) * 60;
    }

    public function planLeadSeconds(): int
    {
        return $this->planLeadMinutes * 60;
    }

    public function changeThresholdSeconds(): int
    {
        return $this->changeThresholdMinutes * 60;
    }
}
