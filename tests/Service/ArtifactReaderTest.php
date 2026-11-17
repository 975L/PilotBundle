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

class ArtifactReaderTest extends TestCase
{
    private string $dir;

    // An export folder with one project, two cards, a file with a summary, a plain file and a character
    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/pilot-reader-' . bin2hex(random_bytes(4));
        $this->write('settings/projects', ['items' => [['id' => 'catalog', 'name' => 'Catalog', 'color' => '#123456', 'sites' => ['example.com']]]]);
        $this->write('settings/layout', ['order' => ['other', 'catalog']]);
        $this->write('tasks/catalog', [
            'items' => [['id' => 'item-1', 'title' => 'Item 1', 'due' => '2026-12-01', 'doc' => 'catalog-file', 'steps' => [['t' => 'Step 1', 'done' => true]]]],
            'done' => [['id' => 'item-2', 'title' => 'Item 2', 'doneAt' => '2026-11-01']],
        ]);
        $this->write('docs/catalog-file', ['title' => 'File', 'project' => 'catalog', 'markdown' => "# File\n\n## L'essentiel\n\n- Decided\n\n<details>\n<summary>Detail</summary>\n\n## Study\n\nLong text\n\n</details>\n"]);
        $this->write('docs/other-charter', ['title' => 'Charter', 'project' => 'other', 'kind' => 'charte', 'markdown' => '| a | b |']);
        $this->write('characters/hero', ['name' => 'Hero', 'project' => 'catalog', 'role' => 'Lead', 'desc' => 'Brave', 'info' => [['Age', '7']]]);
    }

    protected function tearDown(): void
    {
        exec('rm -r ' . escapeshellarg($this->dir));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function write(string $path, array $data): void
    {
        @mkdir(\dirname($this->dir . '/' . $path), 0o777, true);
        file_put_contents($this->dir . '/' . $path . '.json', json_encode($data));
    }

    // Projects keep their settings, follow the layout order, and a project only named by a file is created
    public function testProjects(): void
    {
        $projects = array_column(new ArtifactReader()->read($this->dir)['projects'], null, 'slug');

        $this->assertSame(['slug' => 'catalog', 'name' => 'Catalog', 'color' => '#123456', 'sites' => ['example.com'], 'position' => 1], $projects['catalog']);
        $this->assertSame('other', $projects['other']['name']);
        $this->assertSame(0, $projects['other']['position']);
    }

    // Open and done cards are both read, with their date, file and checklist
    public function testCards(): void
    {
        $cards = array_column(new ArtifactReader()->read($this->dir)['cards'], null, 'id');

        $this->assertSame('2026-12-01', $cards['item-1']['due']);
        $this->assertSame('catalog-file', $cards['item-1']['doc']);
        $this->assertSame([['t' => 'Step 1', 'done' => true]], $cards['item-1']['checklist']);
        $this->assertNull($cards['item-1']['doneAt']);
        $this->assertSame('2026-11-01', $cards['item-2']['doneAt']);
    }

    // The summary is the "L'essentiel" section, the detail the rest without its folding tags
    public function testDocSummaryAndDetail(): void
    {
        $docs = array_column(new ArtifactReader()->read($this->dir)['docs'], null, 'slug');

        $this->assertSame('- Decided', $docs['catalog-file']['summary']);
        $this->assertStringContainsString('## Study', (string) $docs['catalog-file']['detail']);
        $this->assertStringNotContainsString('<details>', (string) $docs['catalog-file']['detail']);
        $this->assertStringNotContainsString('Decided', (string) $docs['catalog-file']['detail']);
        $this->assertNull($docs['other-charter']['summary']);
        $this->assertSame('charter', $docs['other-charter']['kind']);
    }

    // A character becomes a document, its role as summary and its fields as detail
    public function testCharacter(): void
    {
        $docs = array_column(new ArtifactReader()->read($this->dir)['docs'], null, 'slug');

        $this->assertSame('character', $docs['character-hero']['kind']);
        $this->assertSame('Lead', $docs['character-hero']['summary']);
        $this->assertSame("**Description** : Brave\n\n**Age** : 7", $docs['character-hero']['detail']);
    }

    public function testMissingFolderIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ArtifactReader()->read($this->dir . '/missing');
    }
}
