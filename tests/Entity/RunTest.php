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
use c975L\PilotBundle\Enum\RunStatus;
use PHPUnit\Framework\TestCase;

class RunTest extends TestCase
{
    private Card $card;

    protected function setUp(): void
    {
        $this->card = new Card(new Project('catalog', 'Catalog'), 'item-1');
    }

    // A run of a step holds its own action and parameters; none given, none read
    public function testRunHoldsItsOwnActionAndParameters(): void
    {
        $run = new Run($this->card, 'copy', ['file' => 'item-1.txt'], 2);

        $this->assertSame(['copy', ['file' => 'item-1.txt'], 2], [$run->getAction(), $run->getParams(), $run->getStep()]);
        $this->assertSame([null, []], [new Run($this->card, '')->getAction(), new Run($this->card, 'copy')->getParams()]);
    }

    // The files a run wrote keep their absolute paths only, once each, grouped by folder for the step
    public function testFilesKeptByFolder(): void
    {
        $run = new Run($this->card, 'copy', [], 0)->setFiles(['/catalog/item-1/01.jpg', ' /catalog/item-1/item-1.pdf ', '/catalog/item-1/01.jpg', 'item-2.jpg', '/catalog/item-3.mp4']);

        $this->assertSame(['/catalog/item-1/01.jpg', '/catalog/item-1/item-1.pdf', '/catalog/item-3.mp4'], $run->getFiles());
        $this->assertSame(['/catalog/item-1' => ['01.jpg', 'item-1.pdf'], '/catalog' => ['item-3.mp4']], $run->getFilesByFolder());
        $this->assertCount(Run::MAX_FILES, $run->setFiles(array_map(static fn (int $index): string => '/catalog/item-' . $index, range(1, 60)))->getFiles());
    }

    // The machine's own data stays on the run but not under the step, a folder holding nothing else left out
    public function testFilesByFolderLeaveMachineDataOut(): void
    {
        $run = new Run($this->card, 'copy', [], 0)->setFiles(['/catalog/posts/list.json', '/catalog/item-1/01.JPG', '/catalog/item-1/notes.MD', '/catalog/item-1/item-1.sla']);

        $this->assertCount(4, $run->getFiles());
        $this->assertSame(['/catalog/item-1' => ['01.JPG', 'item-1.sla']], $run->getFilesByFolder());
        $this->assertSame([], $run->setFiles(['/catalog/posts/list.json'])->getFilesByFolder());
    }

    // The links a run leaves keep http(s) addresses only, once each, named by their host without words, at most MAX_LINKS
    public function testLinksKept(): void
    {
        $run = new Run($this->card, 'copy', [], 0)->setLinks([
            ['url' => ' https://example.com/post/1 ', 'label' => ' Post to approve '],
            ['url' => 'https://example.com/post/1', 'label' => 'Again'],
            ['url' => 'http://example.org/item-1'],
            ['url' => 'javascript:alert(1)', 'label' => 'Bad'],
            ['url' => 'file:///catalog/item-1.pdf'],
            ['label' => 'No address'],
        ]);

        $this->assertSame([['url' => 'https://example.com/post/1', 'label' => 'Post to approve'], ['url' => 'http://example.org/item-1', 'label' => 'example.org']], $run->getLinks());
        $this->assertCount(Run::MAX_LINKS, $run->setLinks(array_map(static fn (int $index): array => ['url' => 'https://example.com/item-' . $index], range(1, 15)))->getLinks());
    }

    // A run follows its step moved
    public function testMoveToStep(): void
    {
        $run = new Run($this->card, 'copy', [], 0);

        $this->assertSame(3, $run->moveToStep(3)->getStep());
        $this->assertNull($run->moveToStep(null)->getStep());
    }

    // A run withdrawn is cancelled, not failed, and over
    public function testCancelIsNotAFailure(): void
    {
        $run = new Run($this->card, 'copy', [], 0)->cancel(new \DateTimeImmutable('2026-11-18 10:00'));

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
