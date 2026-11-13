<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Vbb;

use App\Infrastructure\Vbb\VbbDepartureFactory;
use PHPUnit\Framework\TestCase;

final class VbbDepartureFactoryTest extends TestCase
{
    public function testItBuildsADepartureWithRealtimeData(): void
    {
        $departure = (new VbbDepartureFactory())->fromArray([
            'tripId' => '1|123|0',
            'line' => ['name' => 'S2'],
            'plannedWhen' => '2026-11-13T10:02:00+01:00',
            'when' => '2026-11-13T10:05:00+01:00',
        ]);

        self::assertNotNull($departure);
        self::assertSame('1|123|0', $departure->tripId);
        self::assertSame('S2', $departure->line);
        self::assertSame('2026-11-13T10:02:00+01:00', $departure->plannedAt->format(\DATE_ATOM));
        self::assertSame('2026-11-13T10:05:00+01:00', $departure->expectedAt?->format(\DATE_ATOM));
        self::assertFalse($departure->cancelled);
        self::assertSame(3, $departure->delayMinutes());
    }

    public function testItMarksACancelledDeparture(): void
    {
        $departure = (new VbbDepartureFactory())->fromArray([
            'tripId' => '1|123|0',
            'line' => ['name' => 'S2'],
            'plannedWhen' => '2026-11-13T10:02:00+01:00',
            'when' => null,
            'cancelled' => true,
        ]);

        self::assertNotNull($departure);
        self::assertTrue($departure->cancelled);
        self::assertNull($departure->expectedAt);
        self::assertSame('2026-11-13T10:02:00+01:00', $departure->plannedAt->format(\DATE_ATOM));
    }

    public function testItFallsBackToTheRealtimeTimeWhenThePlannedTimeIsMissing(): void
    {
        $departure = (new VbbDepartureFactory())->fromArray([
            'tripId' => 'a',
            'plannedWhen' => null,
            'when' => '2026-11-13T10:05:00+01:00',
        ]);

        self::assertNotNull($departure);
        self::assertSame('2026-11-13T10:05:00+01:00', $departure->plannedAt->format(\DATE_ATOM));
    }

    public function testItDefaultsTheLineName(): void
    {
        $departure = (new VbbDepartureFactory())->fromArray([
            'tripId' => 'a',
            'plannedWhen' => '2026-11-13T10:02:00+01:00',
        ]);

        self::assertNotNull($departure);
        self::assertSame('S-Bahn', $departure->line);
    }

    public function testItReturnsNullWithoutAnyTime(): void
    {
        self::assertNull((new VbbDepartureFactory())->fromArray(['tripId' => 'a', 'plannedWhen' => null, 'when' => null]));
    }

    public function testItReturnsNullForAnUnparseableTime(): void
    {
        self::assertNull((new VbbDepartureFactory())->fromArray(['tripId' => 'a', 'plannedWhen' => 'not a date']));
    }
}
