<?php

declare(strict_types=1);

namespace App\Infrastructure\Ntfy;

use App\Application\Exception\PushFailedException;
use App\Application\Port\PushNotifierInterface;
use App\Domain\Model\Notification;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class NtfyPushNotifier implements PushNotifierInterface
{
    private const int TIMEOUT_SECONDS = 10;
    private const int PRIORITY_DEFAULT = 3;
    private const int PRIORITY_URGENT = 5;

    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire(env: 'NTFY_SERVER_URL')]
        private string $serverUrl,
        #[Autowire(env: 'NTFY_TOPIC')]
        private string $topic,
    ) {
    }

    public function send(Notification $notification): void
    {
        try {
            $status = $this->httpClient->request('POST', $this->serverUrl, [
                'json' => [
                    'topic' => $this->topic,
                    'title' => $notification->title,
                    'message' => $notification->body,
                    'priority' => $notification->urgent ? self::PRIORITY_URGENT : self::PRIORITY_DEFAULT,
                ],
                'timeout' => self::TIMEOUT_SECONDS,
            ])->getStatusCode();
        } catch (ExceptionInterface $e) {
            throw new PushFailedException('The push service could not be reached.', 0, $e);
        }

        if ($status < 200 || $status >= 300) {
            throw new PushFailedException(\sprintf('The push service answered with HTTP %d.', $status));
        }
    }
}
