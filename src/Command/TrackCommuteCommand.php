<?php

declare(strict_types=1);

namespace App\Command;

use App\Application\CommuteTracker;
use App\Application\Exception\PushFailedException;
use App\Domain\Model\Decision;
use App\Domain\Model\Phase;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:commute:track', description: 'Prüft die S-Bahn-Abfahrten und benachrichtigt, wann du losgehen musst.')]
final readonly class TrackCommuteCommand
{
    public function __construct(private CommuteTracker $tracker)
    {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Nur anzeigen, was passieren würde: nichts senden, nichts speichern.')]
        bool $dryRun = false,
    ): int {
        try {
            $decision = $dryRun ? $this->tracker->preview() : $this->tracker->run();
        } catch (PushFailedException $e) {
            $io->error(\sprintf('Die Benachrichtigung konnte nicht gesendet werden: %s', $e->getMessage()));

            return Command::FAILURE;
        }

        if (null === $decision) {
            $io->note('Ein anderer Durchlauf läuft noch, dieser wird übersprungen.');

            return Command::SUCCESS;
        }

        $this->render($io, $decision, $dryRun);

        return Command::SUCCESS;
    }

    private function render(SymfonyStyle $io, Decision $decision, bool $dryRun): void
    {
        $io->writeln(\sprintf('Phase: <info>%s</info>', $this->phaseLabel($decision->state->phase)));

        if ([] === $decision->notifications) {
            $io->writeln($dryRun ? 'Es würde nichts gesendet.' : 'Es gab nichts zu senden.');

            return;
        }

        $io->writeln($dryRun ? 'Trockenlauf, es wird nichts gesendet. Folgende Meldungen würden gesendet:' : 'Gesendete Meldungen:');
        foreach ($decision->notifications as $notification) {
            $io->writeln(\sprintf(
                '%s<comment>%s</comment>: %s',
                $notification->urgent ? '[DRINGEND] ' : '',
                $notification->title,
                $notification->body,
            ));
        }
    }

    private function phaseLabel(Phase $phase): string
    {
        return match ($phase) {
            Phase::Waiting => 'wartet',
            Phase::Planned => 'geplant',
            Phase::Walking => 'unterwegs',
            Phase::Done => 'erledigt',
        };
    }
}
