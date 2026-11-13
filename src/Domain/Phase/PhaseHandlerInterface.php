<?php

declare(strict_types=1);

namespace App\Domain\Phase;

use App\Domain\Model\Decision;
use App\Domain\Model\Phase;
use App\Domain\Model\Situation;
use App\Domain\Model\TrackingState;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/** Decides what happens next while the tracker is in one particular phase. */
#[AutoconfigureTag(self::TAG)]
interface PhaseHandlerInterface
{
    public const TAG = 'app.phase_handler';

    public function supports(Phase $phase): bool;

    public function handle(TrackingState $state, Situation $situation): Decision;
}
