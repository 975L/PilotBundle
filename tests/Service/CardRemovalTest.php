<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Service;

use c975L\PilotBundle\Entity\Card;
use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Entity\Run;
use c975L\PilotBundle\Enum\RunStatus;
use c975L\PilotBundle\Exception\FlowException;
use c975L\PilotBundle\Service\CardImages;
use c975L\PilotBundle\Service\CardRemoval;
use c975L\PilotBundle\Service\RunOutputs;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class CardRemovalTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/pilot-erase-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->root);
    }

    // A card's run files, picture and screenshots are deleted once its removal is written, kept when the flush fails
    public function testFilesDeletedAfterTheFlush(): void
    {
        $outputs = new RunOutputs($this->root);
        $shot = str_repeat('a', 32) . '.png';
        $card = new Card(new Project('catalog', 'Catalog'), 'item-1')->setImage('card-7-a.png')->addFeedback('feedback-1');
        $card->setFeedbackImages(0, [$shot]);
        $run = new Run($card, 'action-1', [], 0);
        new \ReflectionProperty(Run::class, 'id')->setValue($run, 7);
        $run->finish(RunStatus::Done, null, [], new \DateTimeImmutable());
        $output = $outputs->folder($run) . '/item-1.txt';
        $image = $this->root . '/' . Card::IMAGE_DIRECTORY . '/card-7-a.png';
        $screenshot = $this->root . '/' . Card::FEEDBACK_IMAGE_DIRECTORY . '/' . $shot;
        foreach ([$output, $image, $screenshot] as $path) {
            new Filesystem()->dumpFile($path, 'catalog');
        }
        $failing = $this->createStub(EntityManagerInterface::class);
        $failing->method('flush')->willThrowException(new \RuntimeException());

        $refused = false;
        try {
            $this->removal($failing)->remove($card);
        } catch (\RuntimeException) {
            $refused = true;
        }
        $this->assertTrue($refused);
        $this->assertFileExists($output);
        $this->assertFileExists($image);
        $this->assertFileExists($screenshot);

        $this->removal($this->createStub(EntityManagerInterface::class))->remove($card);
        $this->assertDirectoryDoesNotExist(\dirname($output));
        $this->assertFileDoesNotExist($image);
        $this->assertFileDoesNotExist($screenshot);
    }

    // A card a machine works on is refused, nothing removed
    public function testRefusedWhileRunning(): void
    {
        $card = new Card(new Project('catalog', 'Catalog'), 'item-1');
        new Run($card, 'action-1', [], 0);

        $this->expectException(FlowException::class);
        $this->removal($this->createStub(EntityManagerInterface::class))->remove($card);
    }

    // The removal on the test's folder
    private function removal(EntityManagerInterface $entityManager): CardRemoval
    {
        return new CardRemoval($entityManager, new CardImages($this->root), new RunOutputs($this->root));
    }
}
