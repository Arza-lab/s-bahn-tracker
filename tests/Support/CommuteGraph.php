<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Domain\CommutePlanner;
use App\Domain\CommuteSettings;
use App\Domain\NotificationFactory;
use App\Domain\Phase\PlannedPhaseHandler;
use App\Domain\Phase\WaitingPhaseHandler;
use App\Domain\Phase\WalkingPhaseHandler;
use App\Domain\TrainSelector;

/**
 * The real domain object graph, wired by hand.
 *
 * Settings: Europe/Berlin, earliest departure 09:50, 25 min walk, 2 min buffer,
 * plan announced 20 min before leaving, changes below 2 min are ignored.
 */
final readonly class CommuteGraph
{
    public CommuteSettings $settings;
    public TrainSelector $selector;
    public NotificationFactory $notifications;
    public CommutePlanner $planner;

    public function __construct()
    {
        $this->settings = new CommuteSettings('Europe/Berlin', '09:50', 25, 2, 20, 2);
        $this->selector = new TrainSelector($this->settings);
        $this->notifications = new NotificationFactory($this->settings);
        $this->planner = new CommutePlanner([
            new WaitingPhaseHandler($this->selector, $this->notifications, $this->settings),
            new PlannedPhaseHandler($this->selector, $this->notifications, $this->settings),
            new WalkingPhaseHandler($this->notifications, $this->settings),
        ]);
    }
}
