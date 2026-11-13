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
        private TrainSelector $trainSelector,
    ) {
    }

    public function plan(TrackedTrain $train, Situation $situation): Notification
    {
        return new Notification('Dein Plan', $this->body($train, $situation));
    }

    public function planChanged(TrackedTrain $previous, TrackedTrain $current, Situation $situation): Notification
    {
        $previousDeparture = $situation->findByTripId($previous->tripId);
        $title = $previous->tripId !== $current->tripId && true === $previousDeparture?->cancelled
            ? \sprintf('%s um %s fällt aus', $previous->line, $this->clockTime($previous->departureAt))
            : 'Plan geändert';

        return new Notification($title, $this->body($current, $situation), true);
    }

    public function leaveNow(TrackedTrain $train, Situation $situation): Notification
    {
        $body = \sprintf('%s fährt um %s.', $train->line, $this->clockTime($train->departureAt));

        return new Notification('Jetzt losgehen', $body.$this->walkNote($situation->walkConditions), true);
    }

    /**
     * @param list<Departure> $alternatives running trains after the cancelled one
     */
    public function trainCancelled(TrackedTrain $train, array $alternatives): Notification
    {
        $body = \sprintf('%s um %s fällt aus.', $train->line, $this->clockTime($train->departureAt));

        if ([] !== $alternatives) {
            $body .= \sprintf(' Nächste: %s.', implode(', ', array_map($this->describe(...), $alternatives)));
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

    private function body(TrackedTrain $train, Situation $situation): string
    {
        $lines = [$this->summary($train, $situation)];

        $followers = $this->followers($train, $situation);
        if ([] !== $followers) {
            $lines[] = 'Danach:';
            array_push($lines, ...array_map(fn (TrackedTrain $follower): string => $this->followerLine($follower, $situation), $followers));
        }

        return implode("\n", $lines);
    }

    private function summary(TrackedTrain $train, Situation $situation): string
    {
        return \sprintf(
            '%s. Losgehen um %s.%s',
            $this->trainText($train, $situation),
            $this->clockTime($train->leaveAt),
            $this->walkNote($situation->walkConditions),
        );
    }

    /**
     * The reachable trains after the tracked one, so that all shown trains together stay within the limit.
     *
     * @return list<TrackedTrain>
     */
    private function followers(TrackedTrain $train, Situation $situation): array
    {
        $shown = $this->settings->connectionsShown();
        $followers = array_filter(
            $this->trainSelector->upcoming($situation, $shown),
            static fn (TrackedTrain $candidate): bool => $candidate->tripId !== $train->tripId && $candidate->departureAt >= $train->departureAt,
        );

        return \array_slice(array_values($followers), 0, $shown - 1);
    }

    private function followerLine(TrackedTrain $follower, Situation $situation): string
    {
        return \sprintf('%s, los %s', $this->trainText($follower, $situation), $this->clockTime($follower->leaveAt));
    }

    private function trainText(TrackedTrain $train, Situation $situation): string
    {
        $departure = $situation->findByTripId($train->tripId);

        return null !== $departure
            ? $this->describe($departure)
            : \sprintf('%s um %s', $train->line, $this->clockTime($train->departureAt));
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
