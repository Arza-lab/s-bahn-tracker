<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Weather;

use App\Infrastructure\Weather\OpenMeteoWalkConditionsProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class OpenMeteoWalkConditionsProviderTest extends TestCase
{
    public function testItDoesNotCallTheApiWhenDisabled(): void
    {
        $calls = 0;
        $client = new MockHttpClient(static function () use (&$calls): MockResponse {
            ++$calls;

            return new MockResponse('{}');
        });

        $conditions = $this->provider($client, false)->current();

        self::assertSame(0, $calls);
        self::assertFalse($conditions->slowsWalkDown());
    }

    public function testItRequestsTheCurrentWeather(): void
    {
        $captured = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = ['method' => $method, 'url' => $url, 'options' => $options];

            return new MockResponse(json_encode(['current' => []], \JSON_THROW_ON_ERROR));
        });

        $conditions = $this->provider($client)->current();

        self::assertSame('GET', $captured['method']);
        self::assertSame('https://weather.test/v1/forecast', strtok($captured['url'], '?'));
        parse_str((string) parse_url($captured['url'], \PHP_URL_QUERY), $query);
        self::assertSame('52.4293', $query['latitude']);
        self::assertSame('13.3277', $query['longitude']);
        self::assertSame('precipitation,snowfall,snow_depth', $query['current']);
        self::assertEquals(10, $captured['options']['timeout']);
        self::assertFalse($conditions->slowsWalkDown());
    }

    public function testRainAddsTheRainExtraMinutes(): void
    {
        $conditions = $this->provider($this->clientReturning(['precipitation' => 0.4, 'snowfall' => 0.0, 'snow_depth' => 0.0]))->current();

        self::assertSame(3, $conditions->extraMinutes);
        self::assertSame('Regen', $conditions->reason);
    }

    public function testDrizzleBelowTheThresholdIsIgnored(): void
    {
        $conditions = $this->provider($this->clientReturning(['precipitation' => 0.1]))->current();

        self::assertFalse($conditions->slowsWalkDown());
    }

    public function testFallingSnowAddsTheSnowExtraMinutes(): void
    {
        $conditions = $this->provider($this->clientReturning(['precipitation' => 0.5, 'snowfall' => 0.3]))->current();

        self::assertSame(6, $conditions->extraMinutes);
        self::assertSame('Schnee', $conditions->reason);
    }

    public function testSettledSnowAddsTheSnowExtraMinutes(): void
    {
        $conditions = $this->provider($this->clientReturning(['snow_depth' => 0.02]))->current();

        self::assertSame(6, $conditions->extraMinutes);
        self::assertSame('Schnee', $conditions->reason);
    }

    public function testAnApiFailureCountsAsNormalConditions(): void
    {
        $client = new MockHttpClient(static function (): never {
            throw new TransportException('offline');
        });

        self::assertFalse($this->provider($client)->current()->slowsWalkDown());
    }

    public function testAServerErrorCountsAsNormalConditions(): void
    {
        $client = new MockHttpClient(new MockResponse('', ['http_code' => 503]));

        self::assertFalse($this->provider($client)->current()->slowsWalkDown());
    }

    /**
     * @param array<string, float> $current
     */
    private function clientReturning(array $current): MockHttpClient
    {
        return new MockHttpClient(new MockResponse(json_encode(['current' => $current], \JSON_THROW_ON_ERROR)));
    }

    private function provider(MockHttpClient $client, bool $enabled = true): OpenMeteoWalkConditionsProvider
    {
        return new OpenMeteoWalkConditionsProvider($client, new NullLogger(), $enabled, 'https://weather.test', 52.4293, 13.3277, 3, 6);
    }
}
