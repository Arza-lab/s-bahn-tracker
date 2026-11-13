<?php

declare(strict_types=1);

namespace App\Infrastructure\Weather;

use App\Application\Port\WalkConditionsProviderInterface;
use App\Domain\Model\WalkConditions;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class OpenMeteoWalkConditionsProvider implements WalkConditionsProviderInterface
{
    private const TIMEOUT_SECONDS = 10;

    /** Millimetres of precipitation in the current measuring interval from which it counts as rain. */
    private const RAIN_THRESHOLD_MM = 0.2;

    /** Snow depth in metres from which it counts as snow on the ground. */
    private const SNOW_DEPTH_THRESHOLD_M = 0.02;

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        #[Autowire(env: 'bool:WEATHER_ENABLED')]
        private bool $enabled,
        #[Autowire(env: 'WEATHER_API_BASE_URL')]
        private string $baseUrl,
        #[Autowire(env: 'float:WEATHER_LATITUDE')]
        private float $latitude,
        #[Autowire(env: 'float:WEATHER_LONGITUDE')]
        private float $longitude,
        #[Autowire(env: 'int:WEATHER_RAIN_EXTRA_MINUTES')]
        private int $rainExtraMinutes,
        #[Autowire(env: 'int:WEATHER_SNOW_EXTRA_MINUTES')]
        private int $snowExtraMinutes,
    ) {
    }

    public function current(): WalkConditions
    {
        if (!$this->enabled) {
            return WalkConditions::normal();
        }

        try {
            $data = $this->httpClient->request('GET', $this->baseUrl.'/v1/forecast', [
                'query' => [
                    'latitude' => $this->latitude,
                    'longitude' => $this->longitude,
                    'current' => 'precipitation,snowfall,snow_depth',
                ],
                'timeout' => self::TIMEOUT_SECONDS,
            ])->toArray();
        } catch (ExceptionInterface $e) {
            $this->logger->warning('Weather forecast unavailable, assuming normal walking conditions.', ['exception' => $e]);

            return WalkConditions::normal();
        }

        $current = \is_array($data['current'] ?? null) ? $data['current'] : [];

        return $this->conditionsFor(
            (float) ($current['precipitation'] ?? 0),
            (float) ($current['snowfall'] ?? 0),
            (float) ($current['snow_depth'] ?? 0),
        );
    }

    private function conditionsFor(float $precipitation, float $snowfall, float $snowDepth): WalkConditions
    {
        if ($snowfall > 0 || $snowDepth >= self::SNOW_DEPTH_THRESHOLD_M) {
            return new WalkConditions($this->snowExtraMinutes, 'Schnee');
        }

        if ($precipitation >= self::RAIN_THRESHOLD_MM) {
            return new WalkConditions($this->rainExtraMinutes, 'Regen');
        }

        return WalkConditions::normal();
    }
}
