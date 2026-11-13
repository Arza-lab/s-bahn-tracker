<?php

declare(strict_types=1);

namespace App\Domain;

use App\Domain\Model\Departure;
use App\Domain\Model\Notification;
use App\Domain\Model\Situation;
use App\Domain\Model\TrackedTrain;
use App\Domain\Model\WalkConditions;

/** Wording of every message the commuter can receive. */
final readonly class NotificationFactory
{
    public function __construct(
        private CommuteSettings $settings,
    ) {
    }

    public function plan(TrackedTrain $train, Situation $situation): Notification
    {
        return new Notification('Dein Plan', $this->summary($train, $situation));
    }

    public function planChanged(TrackedTrain $previous, TrackedTrain $current, Situation $situation): Notification
    {
        $previousDeparture = $situation->findByTripId($previous->tripId);
        $title = $previous->tripId !== $current->tripId && true === $previousDeparture?->cancelled
            ? \sprintf('%s um %s fällt aus', $previous->line, $this->clockTime($previous->departureAt))
            : 'Plan geändert';

        return new Notification($title, $this->summary($current, $situation), true);
    }

    public function leaveNow(TrackedTrain $train, Situation $situation): Notification
    {
        $body = \sprintf('%s fährt um %s.', $train->line, $this->clockTime($train->departureAt));

        return new Notification('Jetzt losgehen', $body.$this->walkNote($situation->walkConditions), true);
    }

    public function trainCancelled(TrackedTrain $train, ?Departure $alternative): Notification
    {
        $body = \sprintf('%s um %s fällt aus.', $train->line, $this->clockTime($train->departureAt));

        if (null !== $alternative) {
            $body .= \sprintf(' Nächste: %s.', $this->describe($alternative));
        }

        return new Notification('Zug fällt aus', $body, true);
    }

    public function trainDelayed(Departure $departure): Notification
    {
        return new Notification('Verspätung', \sprintf(
            '%s fährt jetzt um %s (%s).',
            $departure->line,
            $this->clockTime($departure->time()),
            $this->punctuality($departure),
        ));
    }

    public function noConnection(): Notification
    {
        return new Notification(
            'Keine Verbindung',
            'Keine erreichbare S-Bahn gefunden. Bitte selbst nachsehen.',
            true,
        );
    }

    public function providerUnavailable(): Notification
    {
        return new Notification(
            'S-Bahn-Wecker',
            'Abfahrtsdaten sind nicht abrufbar. Bitte selbst nachsehen.',
            true,
        );
    }

    private function summary(TrackedTrain $train, Situation $situation): string
    {
        $departure = $situation->findByTripId($train->tripId);
        $trainText = null !== $departure
            ? $this->describe($departure)
            : \sprintf('%s um %s', $train->line, $this->clockTime($train->departureAt));

        return \sprintf(
            '%s. Losgehen um %s.%s',
            $trainText,
            $this->clockTime($train->leaveAt),
            $this->walkNote($situation->walkConditions),
        );
    }

    private function describe(Departure $departure): string
    {
        return \sprintf(
            '%s um %s (%s)',
            $departure->line,
            $this->clockTime($departure->time()),
            $this->punctuality($departure),
        );
    }

    private function punctuality(Departure $departure): string
    {
        $delay = $departure->delayMinutes();

        return $delay >= 1 ? \sprintf('+%d Min', $delay) : 'pünktlich';
    }

    private function walkNote(WalkConditions $conditions): string
    {
        if (!$conditions->slowsWalkDown()) {
            return '';
        }

        return \sprintf(' %s: +%d Min Fußweg eingerechnet.', $conditions->reason ?? 'Wetter', $conditions->extraMinutes);
    }

    private function clockTime(\DateTimeImmutable $time): string
    {
        return $time->setTimezone($this->settings->timezone)->format('H:i');
    }
}
