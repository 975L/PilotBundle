<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Entity;

use c975L\PilotBundle\Entity\Card;
use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Entity\Run;
use c975L\PilotBundle\Enum\CardKind;
use c975L\PilotBundle\Enum\Executor;
use c975L\PilotBundle\Enum\RunStatus;
use PHPUnit\Framework\TestCase;

class RunTest extends TestCase
{
    private Card $card;

    protected function setUp(): void
    {
        $this->card = new Card(new Project('catalog', 'Catalog'), 'item-1')
            ->assign(Executor::Runner, CardKind::Task, 'hello', ['text' => 'card'])
            ->setComment('again');
    }

    // A run of a step holds its own action and parameters, whatever the card says afterwards
    public function testStepRunHoldsItsOwnActionAndParameters(): void
    {
        $run = new Run($this->card, 'copy', ['file' => 'item-1.txt'], 2);
        $this->card->assign(Executor::Runner, CardKind::Task, 'other', []);

        $this->assertSame('copy', $run->getAction());
        $this->assertSame(['file' => 'item-1.txt'], $run->getParams());
        $this->assertSame(2, $run->getStep());
    }

    // A run of the card's own action takes the card's parameters with its comment
    public function testCardRunTakesTheCardsParametersAndComment(): void
    {
        $run = new Run($this->card);

        $this->assertSame('hello', $run->getAction());
        $this->assertSame(['text' => 'card', 'comment' => 'again'], $run->getParams());
        $this->assertNull($run->getStep());
    }

    // A run follows its step moved
    // The files a run wrote keep their absolute paths only, once each, grouped by folder for the step
    public function testFilesKeptByFolder(): void
    {
        $run = new Run($this->card, 'copy', [], 0)->setFiles(['/catalog/item-1/01.jpg', ' /catalog/item-1/item-1.pdf ', '/catalog/item-1/01.jpg', 'item-2.jpg', '/catalog/item-3.mp4']);

        $this->assertSame(['/catalog/item-1/01.jpg', '/catalog/item-1/item-1.pdf', '/catalog/item-3.mp4'], $run->getFiles());
        $this->assertSame(['/catalog/item-1' => ['01.jpg', 'item-1.pdf'], '/catalog' => ['item-3.mp4']], $run->getFilesByFolder());
        $this->assertCount(Run::MAX_FILES, $run->setFiles(array_map(static fn (int $index): string => '/catalog/item-' . $index, range(1, 60)))->getFiles());
    }

    public function testMoveToStep(): void
    {
        $run = new Run($this->card, 'copy', [], 0);

        $this->assertSame(3, $run->moveToStep(3)->getStep());
        $this->assertNull($run->moveToStep(null)->getStep());
    }

    // A run withdrawn is cancelled, not failed, and over
    public function testCancelIsNotAFailure(): void
    {
        $run = new Run($this->card)->cancel(new \DateTimeImmutable('2026-11-18 10:00'));

        $this->assertSame(RunStatus::Cancelled, $run->getStatus());
        $this->assertTrue($run->getStatus()->isFinished());
    }

    // A run whose step left the checklist leaves it too: withdrawn while waiting, its result kept once finished
    public function testLeaveStep(): void
    {
        $now = new \DateTimeImmutable('2026-11-18 10:00');
        $waiting = new Run($this->card, 'copy', [], 0)->leaveStep($now);
        $finished = new Run($this->card, 'copy', [], 1)->finish(RunStatus::Done, 'ok', [], $now)->leaveStep($now->modify('+1 hour'));

        $this->assertSame([RunStatus::Cancelled, Run::LEFT_STEP], [$waiting->getStatus(), $waiting->getStep()]);
        $this->assertSame([RunStatus::Done, 'ok', Run::LEFT_STEP], [$finished->getStatus(), $finished->getMessage(), $finished->getStep()]);
        $this->assertEquals($now, $finished->getFinishedAt());
    }
}
