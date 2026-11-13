<?php

declare(strict_types=1);

namespace App\Infrastructure\Pushover;

use App\Application\Exception\PushFailedException;
use App\Application\Port\PushNotifierInterface;
use App\Domain\Model\Notification;
use App\Infrastructure\Push\SelectablePushNotifier;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsTaggedItem(index: 'pushover')]
#[AutoconfigureTag(SelectablePushNotifier::TAG)]
final readonly class PushoverPushNotifier implements PushNotifierInterface
{
    private const int TIMEOUT_SECONDS = 10;
    private const int PRIORITY_DEFAULT = 0;
    private const int PRIORITY_URGENT = 1;

    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire(env: 'PUSHOVER_API_URL')]
        private string $apiUrl,
        #[Autowire(env: 'PUSHOVER_APP_TOKEN')]
        private string $appToken,
        #[Autowire(env: 'PUSHOVER_USER_KEY')]
        private string $userKey,
    ) {
    }

    public function send(Notification $notification): void
    {
        try {
            $status = $this->httpClient->request('POST', $this->apiUrl, [
                'body' => [
                    'token' => $this->appToken,
                    'user' => $this->userKey,
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
