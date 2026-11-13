<?php

declare(strict_types=1);

namespace App\Infrastructure\State;

use App\Application\Port\TrackingStateRepositoryInterface;
use App\Domain\Model\Phase;
use App\Domain\Model\TrackedTrain;
use App\Domain\Model\TrackingState;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

final readonly class JsonFileTrackingStateRepository implements TrackingStateRepositoryInterface
{
    public function __construct(
        private Filesystem $filesystem,
        #[Autowire('%kernel.project_dir%/var/commute/state.json')]
        private string $path,
    ) {
    }

    public function load(string $day): TrackingState
    {
        $data = $this->read();

        if (null === $data || ($data['day'] ?? null) !== $day) {
            return TrackingState::startOfDay($day);
        }

        try {
            return $this->denormalize($data);
        } catch (\Exception|\TypeError|\ValueError) {
            return TrackingState::startOfDay($day);
        }
    }

    public function save(TrackingState $state): void
    {
        $this->filesystem->dumpFile($this->path, json_encode($this->normalize($state), \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR)."\n");
    }

    /**
     * @return array<string, mixed>|null
     */
    private function read(): ?array
    {
        if (!is_file($this->path) || !is_readable($this->path)) {
            return null;
        }

        $contents = file_get_contents($this->path);
        if (false === $contents) {
            return null;
        }

        try {
            $data = json_decode($contents, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return \is_array($data) ? $data : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalize(TrackingState $state): array
    {
        return [
            'day' => $state->day,
            'phase' => $state->phase->value,
            'providerFailures' => $state->providerFailures,
            'noConnectionReported' => $state->noConnectionReported,
            'train' => null === $state->train ? null : $this->normalizeTrain($state->train),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function normalizeTrain(TrackedTrain $train): array
    {
        return [
            'tripId' => $train->tripId,
            'line' => $train->line,
            'departureAt' => $train->departureAt->format(\DATE_ATOM),
            'leaveAt' => $train->leaveAt->format(\DATE_ATOM),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function denormalize(array $data): TrackingState
    {
        return new TrackingState(
            $data['day'],
            Phase::from($data['phase']),
            \is_array($data['train'] ?? null) ? $this->denormalizeTrain($data['train']) : null,
            (bool) ($data['noConnectionReported'] ?? false),
            (int) ($data['providerFailures'] ?? 0),
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function denormalizeTrain(array $data): TrackedTrain
    {
        return new TrackedTrain(
            $data['tripId'],
            $data['line'],
            new \DateTimeImmutable($data['departureAt']),
            new \DateTimeImmutable($data['leaveAt']),
        );
    }
}
