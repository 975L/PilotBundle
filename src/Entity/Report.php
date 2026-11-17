<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Entity;

use c975L\PilotBundle\Repository\ReportRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

// A table a machine sent as a file of a run (repositories, sites, publication series…), each row with the actions it offers
#[ORM\Entity(repositoryClass: ReportRepository::class)]
#[ORM\Table(name: 'pilot_report')]
class Report
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\Length(max: 100)]
    private string $label = '';

    // Column whose value, matched against a project's sites, also shows the row on that project's page
    #[ORM\Column(name: 'match_column', length: 50, nullable: true)]
    #[Assert\Length(max: 50)]
    private ?string $match = null;

    // Shown on the Planning too
    #[ORM\Column]
    private bool $home = false;

    /** @var list<array{key: string, label: string}> */
    #[ORM\Column(name: 'report_columns', type: Types::JSON)]
    private array $columns = [];

    /** @var list<array<string, scalar|null>> */
    #[ORM\Column(name: 'report_rows', type: Types::JSON)]
    private array $rows = [];

    /** @var list<array{action: string, label: string, params: array<string, string>, ask: list<string>, if: ?string}> */
    #[ORM\Column(name: 'row_actions', type: Types::JSON)]
    private array $actions = [];

    /** @var list<array{action: string, label: string, params: array<string, string>, ask: list<string>, if: ?string}> */
    #[ORM\Column(name: 'global_actions', type: Types::JSON)]
    private array $globals = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $scannedAt;

    public function __construct(
        #[ORM\Column(name: 'report_key', length: 50, unique: true)]
        #[Assert\Length(max: 50)]
        private string $key,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Project $project,
    ) {
        $this->scannedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getProject(): Project
    {
        return $this->project;
    }

    public function getMatch(): ?string
    {
        return $this->match;
    }

    public function isHome(): bool
    {
        return $this->home;
    }

    /** @return list<array{key: string, label: string}> */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /** @return list<array<string, scalar|null>> */
    public function getRows(): array
    {
        return $this->rows;
    }

    /** @return list<array{action: string, label: string, params: array<string, string>, ask: list<string>, if: ?string}> */
    public function getActions(): array
    {
        return $this->actions;
    }

    /** @return list<array{action: string, label: string, params: array<string, string>, ask: list<string>, if: ?string}> */
    public function getGlobals(): array
    {
        return $this->globals;
    }

    public function getScannedAt(): \DateTimeImmutable
    {
        return $this->scannedAt;
    }

    // Replaces the whole table with what the machine sent
    /** @param array{label: string, project: Project, match: ?string, home: bool, columns: list<array{key: string, label: string}>, rows: list<array<string, scalar|null>>, actions: list<array{action: string, label: string, params: array<string, string>, ask: list<string>, if: ?string}>, globals: list<array{action: string, label: string, params: array<string, string>, ask: list<string>, if: ?string}>} $table */
    public function refresh(array $table, \DateTimeImmutable $now): static
    {
        $this->label = $table['label'];
        $this->project = $table['project'];
        $this->match = $table['match'];
        $this->home = $table['home'];
        $this->columns = $table['columns'];
        $this->rows = $table['rows'];
        $this->actions = $table['actions'];
        $this->globals = $table['globals'];
        $this->scannedAt = $now;

        return $this;
    }

    // Indexes of the rows shown on a project's page: all of them on its own project, those matching its sites elsewhere, none on the Planning (null)
    /** @return list<int> */
    public function rowsFor(?Project $project): array
    {
        if (null === $project || $project === $this->project) {
            return array_keys($this->rows);
        }
        if (null === $this->match) {
            return [];
        }

        return array_keys(array_filter($this->rows, fn (array $row): bool => \in_array((string) ($row[$this->match] ?? ''), $project->getSites(), true)));
    }

    // Whether an action applies to a row: always, or when its condition column holds a true value
    /** @param array{if: ?string} $action */
    public function applies(array $action, ?int $row): bool
    {
        if (null === $action['if'] || null === $row) {
            return true;
        }

        return (bool) ($this->rows[$row][$action['if']] ?? false);
    }

    // Values of an action's parameters, {column} replaced by the row's value, those typed in winning for the asked ones
    /**
     * @param array{params: array<string, string>, ask: list<string>} $action
     * @param array<string, string>                                   $typed
     *
     * @return array<string, string>
     */
    public function params(array $action, ?int $row, array $typed): array
    {
        $values = null === $row ? [] : ($this->rows[$row] ?? []);
        $params = [];
        foreach ($action['params'] as $name => $template) {
            $params[$name] = (string) preg_replace_callback('/\{(\w+)\}/', static fn (array $m): string => (string) ($values[$m[1]] ?? ''), $template);
            if (\in_array($name, $action['ask'], true) && isset($typed[$name])) {
                $params[$name] = $typed[$name];
            }
        }

        return $params;
    }
}
