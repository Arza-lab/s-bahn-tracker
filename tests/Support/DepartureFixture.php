<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Domain\Model\Departure;

/** Short-hand builders for departures on Monday 2026-11-16 in Europe/Berlin. */
final class DepartureFixture
{
    public const DAY = '2026-11-16';
    public const TIMEZONE = 'Europe/Berlin';

    /** @param string $time clock time such as "09:51" or "09:23:30" */
    public static function at(string $time): \DateTimeImmutable
    {
        return new \DateTimeImmutable(\sprintf('%s %s', self::DAY, $time), new \DateTimeZone(self::TIMEZONE));
    }

    /** A running train; without an expected time the timetable time applies. */
    public static function departure(string $tripId, string $line, string $plannedAt, ?string $expectedAt = null): Departure
    {
        return new Departure(
            $tripId,
            $line,
            self::at($plannedAt),
            null === $expectedAt ? null : self::at($expectedAt),
            false,
        );
    }

    public static function cancelled(string $tripId, string $line, string $plannedAt): Departure
    {
        return new Departure($tripId, $line, self::at($plannedAt), null, true);
    }

    /**
     * The morning board plus t5 S25 10:21 and t6 S26 10:31, so that more than three trains follow the earliest departure.
     *
     * @return list<Departure>
     */
    public static function longBoard(Departure ...$replacements): array
    {
        return [
            ...self::morningBoard(...$replacements),
            self::departure('t5', 'S25', '10:21', '10:21'),
            self::departure('t6', 'S26', '10:31', '10:31'),
        ];
    }

    /**
     * Four on-time trains: t1 S25 09:41, t2 S26 09:51, t3 S25 10:01, t4 S26 10:11.
     *
     * Departures passed in replace the on-time train with the same trip id.
     *
     * @return list<Departure>
     */
    public static function morningBoard(Departure ...$replacements): array
    {
        $replacementsByTripId = [];
        foreach ($replacements as $replacement) {
            $replacementsByTripId[$replacement->tripId] = $replacement;
        }

        $board = [
            self::departure('t1', 'S25', '09:41', '09:41'),
            self::departure('t2', 'S26', '09:51', '09:51'),
            self::departure('t3', 'S25', '10:01', '10:01'),
            self::departure('t4', 'S26', '10:11', '10:11'),
        ];

        return array_map(
            static fn (Departure $departure): Departure => $replacementsByTripId[$departure->tripId] ?? $departure,
            $board,
        );
    }
}
