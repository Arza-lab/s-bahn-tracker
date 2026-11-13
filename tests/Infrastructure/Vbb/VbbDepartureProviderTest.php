<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Vbb;

use App\Application\Exception\DepartureProviderException;
use App\Infrastructure\Vbb\VbbDepartureFactory;
use App\Infrastructure\Vbb\VbbDepartureProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class VbbDepartureProviderTest extends TestCase
{
    private const ROWS = [
        ['tripId' => 'a', 'line' => ['name' => 'S2'], 'plannedWhen' => '2026-11-13T10:02:00+01:00', 'when' => '2026-11-13T10:03:00+01:00'],
        ['tripId' => 'b', 'line' => ['name' => 'S2'], 'plannedWhen' => '2026-11-13T10:12:00+01:00', 'when' => null, 'cancelled' => true],
        ['tripId' => 'c', 'plannedWhen' => null, 'when' => null],
    ];

    public function testItRequestsTheDepartureBoardAndMapsTheRows(): void
    {
        $captured = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = ['method' => $method, 'url' => $url, 'options' => $options];

            return new MockResponse(json_encode(['departures' => self::ROWS], \JSON_THROW_ON_ERROR));
        });

        $departures = $this->provider($client)->upcoming(new \DateTimeImmutable('2026-11-13T10:00:00+01:00'));

        self::assertSame('GET', $captured['method']);
        self::assertSame('https://vbb.test/stops/900064301/departures', strtok($captured['url'], '?'));
        parse_str((string) parse_url($captured['url'], \PHP_URL_QUERY), $query);
        self::assertSame('900007104', $query['direction']);
        self::assertSame('2026-11-13T10:00:00+01:00', $query['when']);
        self::assertSame('120', $query['duration']);
        self::assertSame('30', $query['results']);
        self::assertSame('true', $query['suburban']);
        self::assertSame('false', $query['bus']);
        self::assertSame('false', $query['regional']);
        self::assertEquals(15, $captured['options']['timeout']);

        self::assertCount(2, $departures);
        self::assertSame('a', $departures[0]->tripId);
        self::assertTrue($departures[1]->cancelled);
    }

    public function testItAcceptsABareListResponse(): void
    {
        $client = new MockHttpClient(new MockResponse(json_encode(self::ROWS, \JSON_THROW_ON_ERROR)));

        $departures = $this->provider($client)->upcoming(new \DateTimeImmutable('2026-11-13T10:00:00+01:00'));

        self::assertCount(2, $departures);
        self::assertSame(['a', 'b'], [$departures[0]->tripId, $departures[1]->tripId]);
    }

    public function testAServerErrorBecomesADepartureProviderException(): void
    {
        $client = new MockHttpClient(new MockResponse('', ['http_code' => 500]));

        try {
            $this->provider($client)->upcoming(new \DateTimeImmutable('2026-11-13T10:00:00+01:00'));
            self::fail('Expected a DepartureProviderException.');
        } catch (DepartureProviderException $e) {
            self::assertNotNull($e->getPrevious());
        }
    }

    private function provider(MockHttpClient $client): VbbDepartureProvider
    {
        return new VbbDepartureProvider($client, new VbbDepartureFactory(), 'https://vbb.test', '900064301', '900007104');
    }
}
