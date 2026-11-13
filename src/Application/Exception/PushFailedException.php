<?php

declare(strict_types=1);

namespace App\Application\Exception;

/** A notification could not be delivered to the push service. */
final class PushFailedException extends \RuntimeException
{
}
