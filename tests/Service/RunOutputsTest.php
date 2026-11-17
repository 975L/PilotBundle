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

    // A sent file is kept under a safe name, previewed by kind, and a file left on the machine is only named
    public function testStoreAndPreview(): void
    {
        $run = $this->newRun();
        $outputs = new RunOutputs($this->dir);
        $source = $this->dir . '/source.txt';
        new Filesystem()->dumpFile($source, 'catalog');

        $name = $outputs->store($run, new UploadedFile($source, '../../.item 1.txt', null, null, true));
        $run->finish(RunStatus::Done, null, ['/home/x/item 1.txt', '/home/x/film.mp4'], null, new \DateTimeImmutable());
        $previews = $outputs->previews($run);

        $this->assertSame('item_1.txt', $name);
        $this->assertSame(['name' => 'item_1.txt', 'kind' => 'text', 'text' => 'catalog'], $previews[0] ?? null);
        $this->assertSame('elsewhere', $previews[1]['kind'] ?? null);
        $this->assertSame('/home/x/film.mp4', $previews[1]['name']);
        $this->assertNull($outputs->path($run, '../source.txt'));
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

    // A run with an id, as the database gives it
    private function newRun(): Run
    {
        $run = new Run(new Card(new Project('catalog', 'Catalog'), 'item-1'));
        new \ReflectionProperty(Run::class, 'id')->setValue($run, 7);

        return $run;
    }
}
