<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Command;

use c975L\PilotBundle\Service\ArtifactImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

// Brings the projects, cards and files of the Pilotage artifact into the bundle, from an export folder; run again, it updates instead of duplicating
#[AsCommand(
    name: 'c975l:pilot:import',
    description: 'Imports an export of the Pilotage artifact (projects, cards, files)'
)]
class ImportCommand extends Command
{
    public function __construct(
        private readonly ArtifactImporter $artifactImporter,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('dir', InputArgument::REQUIRED, 'Export folder, one sub-folder per collection (settings, tasks, docs)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $counts = $this->artifactImporter->import((string) $input->getArgument('dir'));
        } catch (\InvalidArgumentException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }
        $io->success(sprintf('%d projects, %d files and %d cards imported.', $counts['projects'], $counts['docs'], $counts['cards']));

        return Command::SUCCESS;
    }
}
