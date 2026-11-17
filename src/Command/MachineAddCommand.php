<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Command;

use c975L\PilotBundle\Entity\Machine;
use c975L\PilotBundle\Repository\MachineRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

// Registers a runner machine, or gives an existing one a new token, and prints the token once
#[AsCommand(
    name: 'c975l:pilot:machine-add',
    description: 'Registers a runner machine and prints its token once'
)]
class MachineAddCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MachineRepository $machineRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'Machine name');
    }

    // Creates the machine with a fresh token, only its hash being stored; its runs keep their history, their machine left empty
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = (string) $input->getArgument('name');
        $existing = $this->machineRepository->findOneBy(['name' => $name]);
        if (null !== $existing) {
            $this->entityManager->remove($existing);
            $this->entityManager->flush();
        }
        $machine = new Machine($name, '');
        $token = $machine->newToken();
        $this->entityManager->persist($machine);
        $this->entityManager->flush();
        $output->writeln($token);

        return Command::SUCCESS;
    }
}
