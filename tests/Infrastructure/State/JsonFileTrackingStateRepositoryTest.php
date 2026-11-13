<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\State;

use App\Domain\Model\Phase;
use App\Domain\Model\TrackedTrain;
use App\Domain\Model\TrackingState;
use App\Infrastructure\State\JsonFileTrackingStateRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class JsonFileTrackingStateRepositoryTest extends TestCase
{
    private string $directory;
    private string $path;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/commute-state-'.bin2hex(random_bytes(6));
        $this->path = $this->directory.'/nested/state.json';
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->directory);
    }

    public function testAMissingFileYieldsAFreshState(): void
    {
        $state = $this->repository()->load('2026-11-13');

        self::assertSame('2026-11-13', $state->day);
        self::assertSame(Phase::Waiting, $state->phase);
        self::assertNull($state->train);
    }

    public function testAStateWithATrainSurvivesARoundTrip(): void
    {
        $train = new TrackedTrain(
            '1|123|0',
            'S2',
            new \DateTimeImmutable('2026-11-13T10:05:00+01:00'),
            new \DateTimeImmutable('2026-11-13T09:38:00+01:00'),
        );
        $state = TrackingState::startOfDay('2026-11-13')
            ->planned($train)
            ->walking()
            ->withNoConnectionReported()
            ->withProviderFailures(2);

        $repository = $this->repository();
        $repository->save($state);
        $loaded = $repository->load('2026-11-13');

        self::assertSame('2026-11-13', $loaded->day);
        self::assertSame(Phase::Walking, $loaded->phase);
        self::assertTrue($loaded->noConnectionReported);
        self::assertSame(2, $loaded->providerFailures);
        self::assertNotNull($loaded->train);
        self::assertSame('1|123|0', $loaded->train->tripId);
        self::assertSame('S2', $loaded->train->line);
        self::assertSame('2026-11-13T10:05:00+01:00', $loaded->train->departureAt->format(\DATE_ATOM));
        self::assertSame('2026-11-13T09:38:00+01:00', $loaded->train->leaveAt->format(\DATE_ATOM));
    }

    public function testAStateWithoutATrainSurvivesARoundTrip(): void
    {
        $repository = $this->repository();
        $repository->save(TrackingState::startOfDay('2026-11-13')->done());
        $loaded = $repository->load('2026-11-13');

        self::assertSame(Phase::Done, $loaded->phase);
        self::assertNull($loaded->train);
    }

    public function testAStateOfAnotherDayIsDiscarded(): void
    {
        $repository = $this->repository();
        $repository->save(TrackingState::startOfDay('2026-11-12')->done());

        $loaded = $repository->load('2026-11-13');

        self::assertSame('2026-11-13', $loaded->day);
        self::assertSame(Phase::Waiting, $loaded->phase);
    }

    public function testACorruptFileYieldsAFreshState(): void
    {
        (new Filesystem())->dumpFile($this->path, '{not json');

        self::assertSame(Phase::Waiting, $this->repository()->load('2026-11-13')->phase);
    }

    public function testAFileWithAnUnknownPhaseYieldsAFreshState(): void
    {
        (new Filesystem())->dumpFile($this->path, '{"day":"2026-11-13","phase":"flying"}');

        self::assertSame(Phase::Waiting, $this->repository()->load('2026-11-13')->phase);
    }

    private function repository(): JsonFileTrackingStateRepository
    {
        return new JsonFileTrackingStateRepository(new Filesystem(), $this->path);
    }
}
