<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Service;

use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Entity\Report;
use c975L\PilotBundle\Repository\ProjectRepository;
use c975L\PilotBundle\Repository\ReportRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

// A file named <key>.report.json sent by a machine becomes the report <key>; anything malformed is left aside
class ReportIngester
{
    public const string SUFFIX = '.report.json';

    private const string KEY = '/^[a-z0-9][a-z0-9-]{0,49}$/';

    private const string NAME = '/^\w{1,50}$/';

    public function __construct(
        private readonly ReportRepository $reportRepository,
        private readonly ProjectRepository $projectRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ClockInterface $clock,
    ) {
    }

    // Reads a kept file of a run, the run's project standing in when the file names none; false when it is not a report
    public function ingest(string $name, string $path, Project $fallback): bool
    {
        $key = str_ends_with($name, self::SUFFIX) ? substr($name, 0, -\strlen(self::SUFFIX)) : '';
        $data = json_decode((string) file_get_contents($path), true);
        if (1 !== preg_match(self::KEY, $key) || !\is_array($data)) {
            return false;
        }
        $slug = \is_string($data['project'] ?? null) ? $data['project'] : null;
        $project = (null === $slug ? null : $this->projectRepository->findOneBy(['slug' => $slug])) ?? $fallback;
        $report = $this->reportRepository->findOneBy(['key' => $key]) ?? new Report($key, $project);
        $report->refresh($this->table($data, $key, $project), $this->clock->now());
        $this->entityManager->persist($report);
        $this->entityManager->flush();

        return true;
    }

    // The table kept from the file, every part checked and trimmed to what the page shows
    /**
     * @param array<mixed> $data
     *
     * @return array{label: string, project: Project, match: ?string, home: bool, columns: list<array{key: string, label: string}>, rows: list<array<string, scalar|null>>, actions: list<array{action: string, label: string, params: array<string, string>, ask: list<string>, if: ?string}>, globals: list<array{action: string, label: string, params: array<string, string>, ask: list<string>, if: ?string}>}
     */
    public function table(array $data, string $key, Project $project): array
    {
        $columns = [];
        foreach ($this->list($data['columns'] ?? null) as $column) {
            if ($this->name($column['key'] ?? null)) {
                $columns[] = ['key' => $column['key'], 'label' => mb_substr($this->text($column['label'] ?? $column['key']), 0, 50)];
            }
        }
        $rows = [];
        foreach ($this->list($data['rows'] ?? null) as $row) {
            $rows[] = array_filter($row, static fn (mixed $value, int | string $name): bool => \is_string($name) && (null === $value || \is_scalar($value)), ARRAY_FILTER_USE_BOTH);
        }
        $match = $data['match'] ?? null;

        return [
            'label' => mb_substr($this->text($data['label'] ?? $key), 0, 100),
            'project' => $project,
            'match' => $this->name($match) ? $match : null,
            'home' => true === ($data['home'] ?? false),
            'columns' => $columns,
            'rows' => $rows,
            'actions' => $this->actions($data['actions'] ?? null),
            'globals' => $this->actions($data['globals'] ?? null),
        ];
    }

    // Actions of a row or of the whole table: an action key, a label, parameters as templates, those asked, an optional condition column
    /** @return list<array{action: string, label: string, params: array<string, string>, ask: list<string>, if: ?string}> */
    private function actions(mixed $list): array
    {
        $actions = [];
        foreach ($this->list($list) as $action) {
            if (!$this->name($action['action'] ?? null, '/^[a-z0-9][a-z0-9-]{0,49}$/')) {
                continue;
            }
            $params = array_map($this->text(...), array_filter(\is_array($action['params'] ?? null) ? $action['params'] : [], fn (mixed $value, int | string $name): bool => \is_string($name) && 1 === preg_match(self::NAME, $name) && \is_string($value), ARRAY_FILTER_USE_BOTH));
            $ask = array_values(array_filter(\is_array($action['ask'] ?? null) ? $action['ask'] : [], static fn (mixed $name): bool => \is_string($name) && isset($params[$name])));
            $condition = $action['if'] ?? null;
            $actions[] = ['action' => $action['action'], 'label' => mb_substr($this->text($action['label'] ?? $action['action']), 0, 50), 'params' => $params, 'ask' => $ask, 'if' => $this->name($condition) ? $condition : null];
        }

        return $actions;
    }

    // The arrays of a list, anything else dropped
    /** @return list<array<mixed>> */
    private function list(mixed $list): array
    {
        return \is_array($list) ? array_values(array_filter($list, is_array(...))) : [];
    }

    // Whether a value is a column or parameter name
    /** @phpstan-assert-if-true string $value */
    private function name(mixed $value, string $pattern = self::NAME): bool
    {
        return \is_string($value) && 1 === preg_match($pattern, $value);
    }

    private function text(mixed $value): string
    {
        return \is_scalar($value) ? trim((string) $value) : '';
    }
}
