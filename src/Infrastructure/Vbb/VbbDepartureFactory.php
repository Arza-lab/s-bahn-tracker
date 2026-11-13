<?php

declare(strict_types=1);

namespace App\Infrastructure\Vbb;

use App\Domain\Model\Departure;

/** Builds departures from the hafas-style rows of the VBB API. */
final readonly class VbbDepartureFactory
{
    private const DEFAULT_LINE = 'S-Bahn';

    /**
     * @param array<string, mixed> $row
     */
    public function fromArray(array $row): ?Departure
    {
        $expectedAt = $this->parseTime($row['when'] ?? null);
        $plannedAt = $this->parseTime($row['plannedWhen'] ?? null) ?? $expectedAt;

        if (null === $plannedAt) {
            return null;
        }

        return new Departure(
            (string) ($row['tripId'] ?? ''),
            $this->lineName($row),
            $plannedAt,
            $expectedAt,
            true === ($row['cancelled'] ?? false),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function lineName(array $row): string
    {
        $name = \is_array($row['line'] ?? null) ? ($row['line']['name'] ?? null) : null;

        return \is_string($name) && '' !== $name ? $name : self::DEFAULT_LINE;
    }

    private function parseTime(mixed $value): ?\DateTimeImmutable
    {
        if (!\is_string($value) || '' === $value) {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }
}
