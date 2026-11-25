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
use c975L\PilotBundle\Service\RunOutputs;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class RunOutputsTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/pilot-outputs-' . uniqid();
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->dir);
    }

    // A sent file is kept under a safe name, shown by kind on its step, and a file left on the machine is only named
    public function testStoreAndKinds(): void
    {
        $run = $this->newRun();
        $outputs = new RunOutputs($this->dir);
        $source = $this->dir . '/source.txt';
        new Filesystem()->dumpFile($source, 'catalog');

        $name = $outputs->store($run, new UploadedFile($source, '../../.item 1.txt', null, null, true));
        $run->finish(RunStatus::Done, null, ['/home/x/item 1.txt', '/home/x/film.mp4'], new \DateTimeImmutable());
        $files = $outputs->ofSteps($run->getCard())[0]['files'] ?? [];

        $this->assertSame('item_1.txt', $name);
        $this->assertSame(['name' => 'item_1.txt', 'kind' => 'text'], $files[0] ?? null);
        $this->assertSame(['name' => '/home/x/film.mp4', 'kind' => 'elsewhere'], $files[1] ?? null);
        $this->assertNull($outputs->path($run, '../source.txt'));
    }

    // Each step keeps the files of its last run that produced any, a run without files, a run that only answered in markdown, a failed one or one whose step left aside
    public function testOfSteps(): void
    {
        $card = new Card(new Project('catalog', 'Catalog'), 'item-1')->setChecklist([['t' => 'step-1'], ['t' => 'step-2']]);
        $first = new Run($card, 'action-1', [], 0);
        $first->finish(RunStatus::Done, null, ['/home/x/item-1.md'], new \DateTimeImmutable());
        $last = new Run($card, 'action-1', [], 0);
        $last->finish(RunStatus::Done, null, ['/home/x/item-2.jpg', '/home/x/reponse.md'], new \DateTimeImmutable());
        new Run($card, 'action-1', [], 0);
        new Run($card, 'action-1', [], 1)->finish(RunStatus::Done, null, ['/home/x/item-5.md'], new \DateTimeImmutable());
        new Run($card, 'action-1', [], 1)->finish(RunStatus::Failed, null, ['/home/x/item-3.md'], new \DateTimeImmutable());
        new Run($card, 'action-1', [], Run::LEFT_STEP)->finish(RunStatus::Done, null, ['/home/x/item-6.jpg'], new \DateTimeImmutable());

        $steps = new RunOutputs($this->dir)->ofSteps($card);

        $this->assertSame([0], array_keys($steps));
        $this->assertSame($last, $steps[0]['run']);
        $this->assertSame([['name' => '/home/x/item-2.jpg', 'kind' => 'elsewhere']], $steps[0]['files']);
    }

    // The files listed under a step are named and typed, never read
    public function testOfStepsReadsNoFile(): void
    {
        $card = new Card(new Project('catalog', 'Catalog'), 'item-1')->setChecklist([['t' => 'step-1']]);
        $run = new Run($card, 'action-1', [], 0);
        new \ReflectionProperty(Run::class, 'id')->setValue($run, 8);
        $outputs = new RunOutputs($this->dir);
        $source = $this->dir . '/source.txt';
        new Filesystem()->dumpFile($source, 'catalog');
        $outputs->store($run, new UploadedFile($source, 'item-1.txt', null, null, true));
        $run->finish(RunStatus::Done, null, ['/home/x/item-1.txt'], new \DateTimeImmutable());

        $this->assertSame([['name' => 'item-1.txt', 'kind' => 'text']], $outputs->ofSteps($card)[0]['files']);
    }

    // Only images and videos get a type to be shown under, an html or svg file none
    public function testMediaTypeOnlyForImagesAndVideos(): void
    {
        $outputs = new RunOutputs($this->dir);

        $this->assertSame('image/png', $outputs->mediaType('/x/item-1.PNG'));
        $this->assertSame('video/mp4', $outputs->mediaType('/x/item-1.mp4'));
        $this->assertNull($outputs->mediaType('/x/item-1.svg'));
        $this->assertNull($outputs->mediaType('/x/item-1.html'));
    }

    // A run's folder takes files up to its ceilings of count and bytes, a file sent again under its name replacing its own bytes
    public function testFitsWithinTheRunsCeilings(): void
    {
        $run = $this->newRun();
        $outputs = new RunOutputs($this->dir);
        $source = $this->dir . '/source.txt';
        new Filesystem()->dumpFile($source, 'catalog');
        $file = static fn (string $name): UploadedFile => new UploadedFile($source, $name, null, null, true);
        $this->assertTrue($outputs->fits($run, $file('item-1.txt')));

        // A file as heavy as the whole ceiling, sparse so that it takes no room
        new Filesystem()->mkdir($outputs->folder($run));
        $handle = fopen($outputs->folder($run) . '/item-1.bin', 'w');
        ftruncate($handle, RunOutputs::MAX_RUN_BYTES);
        fclose($handle);
        $this->assertFalse($outputs->fits($run, $file('item-2.txt')));
        $this->assertTrue($outputs->fits($run, $file('item-1.bin')));

        // As many files as the ceiling, light ones
        unlink($outputs->folder($run) . '/item-1.bin');
        for ($i = 0; $i < RunOutputs::MAX_FILES; ++$i) {
            touch($outputs->folder($run) . '/item-' . $i . '.txt');
        }
        $this->assertFalse($outputs->fits($run, $file('item-new.txt')));
        $this->assertTrue($outputs->fits($run, $file('item-0.txt')));
    }

    // A run with an id, as the database gives it
    private function newRun(): Run
    {
        $run = new Run(new Card(new Project('catalog', 'Catalog'), 'item-1')->setChecklist([['t' => 'step-1', 'action' => 'action-1']]), 'action-1', [], 0);
        new \ReflectionProperty(Run::class, 'id')->setValue($run, 7);

        return $run;
    }
}
