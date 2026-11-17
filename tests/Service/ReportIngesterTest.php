<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Service;

use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Entity\Report;
use c975L\PilotBundle\Repository\ProjectRepository;
use c975L\PilotBundle\Repository\ReportRepository;
use c975L\PilotBundle\Service\ReportIngester;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

class ReportIngesterTest extends TestCase
{
    // The table keeps valid columns, scalar cells and well-formed actions, the rest is dropped
    public function testTableKeepsWhatThePageShows(): void
    {
        $catalog = new Project('catalog', 'Catalog');
        $table = $this->ingester()->table([
            'label' => 'Items',
            'match' => 'site',
            'home' => true,
            'columns' => [['key' => 'name', 'label' => 'Name'], ['key' => 'bad key'], 'nope'],
            'rows' => [['name' => 'item-1', 'site' => 'catalog.test', 'ahead' => 2, 'nested' => ['x']]],
            'actions' => [
                ['action' => 'push', 'label' => 'Push', 'params' => ['repo' => '{name}', 'until' => '2026-12-01', 'bad name' => 'x'], 'ask' => ['until', 'missing'], 'if' => 'ahead'],
                ['action' => 'Bad Action'],
            ],
        ], 'items', $catalog);

        $this->assertSame([['key' => 'name', 'label' => 'Name']], $table['columns']);
        $this->assertSame([['name' => 'item-1', 'site' => 'catalog.test', 'ahead' => 2]], $table['rows']);
        $this->assertSame([['action' => 'push', 'label' => 'Push', 'params' => ['repo' => '{name}', 'until' => '2026-12-01'], 'ask' => ['until'], 'if' => 'ahead']], $table['actions']);
        $this->assertSame([], $table['globals']);
        $this->assertSame('site', $table['match']);
        $this->assertTrue($table['home']);
    }

    // Rows show on their own project and on the project whose sites match; an action fills its parameters from the row, a typed value winning for an asked one
    public function testRowsActionsAndParams(): void
    {
        $catalog = new Project('catalog', 'Catalog');
        $other = new Project('other', 'Other')->setSites(['other.test']);
        $report = new Report('items', $catalog);
        $report->refresh($this->ingester()->table([
            'match' => 'site',
            'rows' => [['name' => 'item-1', 'site' => 'catalog.test', 'ahead' => 0], ['name' => 'item-2', 'site' => 'other.test', 'ahead' => 1]],
            'actions' => [['action' => 'push', 'params' => ['repo' => '{name}', 'until' => '{name}-x'], 'ask' => ['until'], 'if' => 'ahead']],
        ], 'items', $catalog), new \DateTimeImmutable('2026-11-17'));
        $push = $report->getActions()[0];

        $this->assertSame([0, 1], $report->rowsFor($catalog));
        $this->assertSame([1], $report->rowsFor($other));
        $this->assertFalse($report->applies($push, 0));
        $this->assertTrue($report->applies($push, 1));
        $this->assertSame(['repo' => 'item-2', 'until' => 'item-2-x'], $report->params($push, 1, []));
        $this->assertSame(['repo' => 'item-2', 'until' => '2026-12-24'], $report->params($push, 1, ['until' => '2026-12-24', 'repo' => 'forced']));
    }

    private function ingester(): ReportIngester
    {
        return new ReportIngester($this->createStub(ReportRepository::class), $this->createStub(ProjectRepository::class), $this->createStub(EntityManagerInterface::class), new MockClock('2026-11-17'));
    }
}
