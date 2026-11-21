<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Command;

use c975L\PilotBundle\Repository\CreditRepository;
use c975L\PilotBundle\Service\CreditRefresher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

// Reads again the balances of the credits naming a service, scheduled every day by PilotMaintenanceTaskProvider
#[AsCommand(
    name: 'c975l:pilot:credits-refresh',
    description: 'Reads the balances of the paid services from their API'
)]
class CreditsRefreshCommand extends Command
{
    public function __construct(
        private readonly CreditRepository $creditRepository,
        private readonly CreditRefresher $creditRefresher,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    // Each credit read in turn, one that fails keeping its balance; a credit typed in by hand is left alone
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $unread = $this->creditRefresher->refreshAll();
        $this->entityManager->flush();
        foreach ($this->creditRepository->findOrdered() as $credit) {
            if (!$credit->isManual()) {
                $output->writeln(\sprintf('%s: %s', $credit->getKey(), match (true) {
                    !\in_array($credit->getKey(), $unread, true) => (string) $credit->getRemaining(),
                    null === $credit->getApiKey() => 'no API key',
                    default => 'not read, see the log',
                }));
            }
        }

        return Command::SUCCESS;
    }
}
