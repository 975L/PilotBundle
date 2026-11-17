<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Command;

use c975L\PilotBundle\Command\ImportCommand;
use c975L\PilotBundle\Service\ArtifactImporter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class ImportCommandTest extends TestCase
{
    // The counts of the import said back on success
    public function testImportSaysWhatItBrought(): void
    {
        $importer = $this->createStub(ArtifactImporter::class);
        $importer->method('import')->willReturn(['projects' => 2, 'docs' => 3, 'cards' => 4]);
        $tester = new CommandTester(new ImportCommand($importer));

        $this->assertSame(Command::SUCCESS, $tester->execute(['dir' => '/catalog']));
        $this->assertStringContainsString('2 projects, 3 files and 4 cards imported.', $tester->getDisplay());
    }

    // A missing folder is a failure with the reader's message, nothing imported
    public function testMissingFolderFails(): void
    {
        $importer = $this->createStub(ArtifactImporter::class);
        $importer->method('import')->willThrowException(new \InvalidArgumentException('No export folder at "/catalog".'));
        $tester = new CommandTester(new ImportCommand($importer));

        $this->assertSame(Command::FAILURE, $tester->execute(['dir' => '/catalog']));
        $this->assertStringContainsString('No export folder', $tester->getDisplay());
    }
}
