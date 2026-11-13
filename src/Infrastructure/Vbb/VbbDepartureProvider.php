<?php

declare(strict_types=1);

namespace App\Infrastructure\Vbb;

use App\Application\Exception\DepartureProviderException;
use App\Application\Port\DepartureProviderInterface;
use App\Domain\Model\Departure;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class VbbDepartureProvider implements DepartureProviderInterface
{
    private const TIMEOUT_SECONDS = 15;
    private const LOOKAHEAD_MINUTES = 120;
    private const MAX_RESULTS = 30;

    public function __construct(
        private HttpClientInterface $httpClient,
        private VbbDepartureFactory $factory,
        #[Autowire(env: 'VBB_API_BASE_URL')]
        private string $baseUrl,
        #[Autowire(env: 'VBB_ORIGIN_STOP_ID')]
        private string $originStopId,
        #[Autowire(env: 'VBB_DIRECTION_STOP_ID')]
        private string $directionStopId,
    ) {
    }

    public function upcoming(\DateTimeImmutable $from): array
    {
        try {
            $data = $this->httpClient->request(
                'GET',
                \sprintf('%s/stops/%s/departures', $this->baseUrl, $this->originStopId),
                ['query' => $this->query($from), 'timeout' => self::TIMEOUT_SECONDS],
            )->toArray();
        } catch (ExceptionInterface $e) {
            throw new DepartureProviderException('The VBB departure board could not be loaded.', 0, $e);
        }

        $rows = $data['departures'] ?? $data;
        $departures = array_map(
            fn (mixed $row): ?Departure => \is_array($row) ? $this->factory->fromArray($row) : null,
            \is_array($rows) ? $rows : [],
        );

        return array_values(array_filter($departures));
    }

    /**
     * @return array<string, string|int>
     */
    private function query(\DateTimeImmutable $from): array
    {
        return [
            'direction' => $this->directionStopId,
            'when' => $from->format(\DATE_ATOM),
            'duration' => self::LOOKAHEAD_MINUTES,
            'results' => self::MAX_RESULTS,
            'suburban' => 'true',
            'subway' => 'false',
            'tram' => 'false',
            'bus' => 'false',
            'ferry' => 'false',
            'express' => 'false',
            'regional' => 'false',
            'remarks' => 'false',
        ];
    }
}
