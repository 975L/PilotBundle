<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Service;

use c975L\PilotBundle\Service\ArtifactReader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class ArtifactReaderTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/pilot-export-' . uniqid();
        new Filesystem()->dumpFile($this->dir . '/planning/catalog-1.json', (string) json_encode(['project' => 'catalog', 'date' => '2026-12-01', 'title' => 'Item 1', 'todo' => 'Left', 'note' => 'Note']));
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->dir);
    }

    // A deadline of the planning collection becomes a dated card of its project, what is left to do opening its note
    public function testPlanningDeadlinesBecomeDatedCards(): void
    {
        $rows = new ArtifactReader()->read($this->dir);

        $this->assertSame([[
            'id' => 'planning-catalog-1',
            'project' => 'catalog',
            'title' => 'Item 1',
            'note' => "Left\n\nNote",
            'due' => '2026-12-01',
            'doc' => null,
            'doneAt' => null,
            'checklist' => [],
        ]], $rows['cards']);
        $this->assertSame('catalog', $rows['projects'][0]['slug'] ?? null);
    }
}
