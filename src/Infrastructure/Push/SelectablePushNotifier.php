<?php

declare(strict_types=1);

namespace App\Infrastructure\Push;

use App\Application\Port\PushNotifierInterface;
use App\Domain\Model\Notification;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;

/**
 * Delegates to the push transport selected by the PUSH_TRANSPORT environment variable.
 *
 * A transport registers itself with #[AutoconfigureTag(self::TAG)] and #[AsTaggedItem(index: '<name>')].
 */
#[AsAlias(PushNotifierInterface::class)]
final readonly class SelectablePushNotifier implements PushNotifierInterface
{
    public const string TAG = 'app.push_transport';

    public function __construct(
        #[AutowireLocator(self::TAG)]
        private ContainerInterface $transports,
        #[Autowire(env: 'PUSH_TRANSPORT')]
        private string $transport,
    ) {
    }

    public function send(Notification $notification): void
    {
        if (!$this->transports->has($this->transport)) {
            throw new \LogicException(\sprintf('Unknown push transport "%s" configured in PUSH_TRANSPORT.', $this->transport));
        }

        $this->transports->get($this->transport)->send($notification);
    }
}
