<?php

declare(strict_types=1);

namespace App\Domain\Model;

enum Phase: string
{
    /** No plan has been announced yet. */
    case Waiting = 'waiting';

    /** A train has been announced, the commuter is still at home. */
    case Planned = 'planned';

    /** The commuter is on the way to the station. */
    case Walking = 'walking';

    /** Nothing left to do today. */
    case Done = 'done';
}
