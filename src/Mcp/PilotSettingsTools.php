<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Mcp;

use c975L\PilotBundle\Entity\Action;
use c975L\PilotBundle\Entity\CardSet;
use c975L\PilotBundle\Entity\Machine;
use c975L\PilotBundle\Entity\PlanTemplate;
use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Form\PlanStep;
use c975L\PilotBundle\Repository\ActionRepository;
use c975L\PilotBundle\Repository\CardRepository;
use c975L\PilotBundle\Repository\CardSetRepository;
use c975L\PilotBundle\Repository\MachineRepository;
use c975L\PilotBundle\Repository\PlanTemplateRepository;
use c975L\PilotBundle\Repository\ProjectRepository;
use c975L\PilotBundle\Security\PilotAccess;
use c975L\PilotBundle\Service\StepOrder;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Server\Exception\ToolCallException;
use Psr\Clock\ClockInterface;

// The piloting settings as MCP tools, as their pages set them: the checklist templates, the spaces offering each machine action, the machines for the admin, and the collections of a space for whoever manages it
class PilotSettingsTools
{
    use AccountTools;

    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly PlanTemplateRepository $planTemplateRepository,
        private readonly ActionRepository $actionRepository,
        private readonly MachineRepository $machineRepository,
        private readonly CardSetRepository $cardSetRepository,
        private readonly CardRepository $cardRepository,
        private readonly StepOrder $stepOrder,
        private readonly PilotAccess $pilotAccess,
        private readonly EntityManagerInterface $entityManager,
        private readonly ClockInterface $clock,
    ) {
    }

    // The templates, with the spaces offering them, their steps counted and the open cards following them
    /** @return list<array{id: ?int, name: string, spaces: list<string>, steps: int, open: int}> */
    public function templates(): array
    {
        $this->admin();

        return array_map(fn (PlanTemplate $template): array => [
            'id' => $template->getId(),
            'name' => $template->getName(),
            'spaces' => self::slugs($template->getProjects()),
            'steps' => \count($template->getSteps()),
            'open' => \count($this->cardRepository->findOpenOfTemplate($template)),
        ], $this->planTemplateRepository->findBy([], ['name' => 'ASC']));
    }

    // A template with its steps numbered from 1, as « after » names them
    /**
     * @param string $template id or name of the template
     *
     * @return array{id: ?int, name: string, spaces: list<string>, open: int, steps: list<array{number: int, title: string, action?: string, params?: array<string, string>, after?: list<int>, group?: string}>}
     */
    public function template(string $template): array
    {
        $this->admin();

        return $this->described($this->found($template));
    }

    // A template made, or the one named changed in what is given only; its steps saved are laid on its open cards as the settings page does, each card keeping its ticks and its own steps
    /**
     * @param ?string                     $template id or name of the template to change, none to make one
     * @param ?string                     $name     its name, unique
     * @param ?list<string>               $spaces   slugs of the spaces offering it, replacing those it had
     * @param ?list<array<string, mixed>> $steps    every step in its order, replacing those it had: title, and optionally action (key of a machine action), params (name to value), after (numbers from 1 of the steps to tick before it, [] for none, absent for the step before) and group
     *
     * @return array{id: ?int, name: string, spaces: list<string>, open: int, steps: list<array{number: int, title: string, action?: string, params?: array<string, string>, after?: list<int>, group?: string}>}
     */
    public function saveTemplate(?string $template = null, ?string $name = null, ?array $spaces = null, ?array $steps = null): array
    {
        $this->admin();
        $found = null === $this->text($template) ? null : $this->found((string) $template);
        if (null === $found && (null === $this->text($name) || null === $steps)) {
            throw new ToolCallException('A new template takes a name and its steps.');
        }
        $found ??= new PlanTemplate('', []);
        $previous = $found->getSteps();
        if (null !== $this->text($name)) {
            $this->rename($found, (string) $this->text($name));
        }
        if (null !== $spaces) {
            $this->offer($found->getProjects(), $spaces);
        }
        if (null !== $steps) {
            $found->setSteps($this->steps($steps));
        }
        $this->entityManager->persist($found);
        if (null !== $found->getId()) {
            $this->stepOrder->fromTemplate($found, $previous);
        }
        $this->entityManager->flush();

        return $this->described($found);
    }

    // The machine actions declared, in the order of their buttons, with the spaces offering them and the machines running them
    /** @return list<array{key: string, label: string, params: list<string>, help: ?string, card: bool, spaces: list<string>, machines: list<string>}> */
    public function actions(): array
    {
        $this->admin();
        $machines = $this->machineRepository->findBy([], ['name' => 'ASC']);

        return array_map(static fn (Action $action): array => [
            'key' => $action->getKey(),
            'label' => $action->getLabel(),
            'params' => $action->getParams(),
            'help' => $action->getHelp(),
            'card' => $action->isCardOnly(),
            'spaces' => self::slugs($action->getProjects()),
            'machines' => array_values(array_map(static fn (Machine $machine): string => $machine->getName(), array_filter($machines, static fn (Machine $machine): bool => isset($machine->getCapabilities()[$action->getKey()])))),
        ], $this->actionRepository->findBy([], ['position' => 'ASC', 'label' => 'ASC']));
    }

    // The spaces offering a machine action, replacing those it had: a step of another space naming it is not run
    /**
     * @param string       $action key of the action
     * @param list<string> $spaces slugs of the spaces offering it
     *
     * @return array{key: string, spaces: list<string>}
     */
    public function offerAction(string $action, array $spaces): array
    {
        $this->admin();
        $found = $this->actionRepository->findOneBy(['key' => trim($action)]);
        if (!$found instanceof Action) {
            throw new ToolCallException(sprintf('No machine declares the action "%s".', $action));
        }
        $this->offer($found->getProjects(), $spaces);
        $this->entityManager->flush();

        return ['key' => $found->getKey(), 'spaces' => self::slugs($found->getProjects())];
    }

    // The machines, with the spaces each serves (every one when none), the runs it takes at once, whether it is online and the actions it declared
    /** @return list<array{name: string, parallel: int, spaces: list<string>, online: bool, seen: ?string, actions: list<string>}> */
    public function machines(): array
    {
        $this->admin();
        $now = $this->clock->now();

        return array_map(static fn (Machine $machine): array => self::machine($machine, $now), $this->machineRepository->findBy([], ['name' => 'ASC']));
    }

    // The runs a machine takes at once and the spaces it serves, only what is given changing; a machine is made, and its token given, on the settings page only
    /**
     * @param string        $machine  name of the machine
     * @param ?int          $parallel runs it takes at once, from 1 to Machine::MAX_PARALLEL
     * @param ?list<string> $spaces   slugs of the spaces it serves, replacing those it had, [] for every space
     *
     * @return array{name: string, parallel: int, spaces: list<string>, online: bool, seen: ?string, actions: list<string>}
     */
    public function editMachine(string $machine, ?int $parallel = null, ?array $spaces = null): array
    {
        $this->admin();
        $found = $this->machineRepository->findOneBy(['name' => trim($machine)]);
        if (!$found instanceof Machine) {
            throw new ToolCallException(sprintf('No machine "%s".', $machine));
        }
        if (null !== $parallel) {
            $found->setParallel($parallel);
        }
        if (null !== $spaces) {
            $this->offer($found->getProjects(), $spaces);
        }
        $this->entityManager->flush();

        return self::machine($found, $this->clock->now());
    }

    // A collection of a space made, or the one named changed in what is given only, as its tab's gear sets it: its name, its word for a card, its template among those the space offers, whether its cards show as sheets
    /**
     * @param string  $space      slug of the space
     * @param ?string $collection name of the collection to change, none to add one after the others
     * @param ?string $name       its name
     * @param ?string $item       its word for one of its cards
     * @param ?string $template   id or name of the template a card added to it gets, empty for none
     * @param ?bool   $sheets     whether its cards show as sheets (a picture, a title and a pitch)
     *
     * @return array{space: string, collection: string, item: ?string, template: ?string, sheets: bool}
     */
    public function saveCollection(string $space, ?string $collection = null, ?string $name = null, ?string $item = null, ?string $template = null, ?bool $sheets = null): array
    {
        $project = $this->space($space, PilotAccess::MANAGE);
        $found = $this->cardSet($project, $collection, $name);
        if (null !== $this->text($name)) {
            $found->setName(mb_substr((string) $this->text($name), 0, 100));
        }
        if (null !== $item) {
            $found->setItem($this->text($item));
        }
        if (null !== $template) {
            $found->setTemplate(null === $this->text($template) ? null : $this->offeredTemplate($project, $template));
        }
        if (null !== $sheets) {
            $found->setSheets($sheets);
        }
        $this->entityManager->persist($found);
        $this->entityManager->flush();

        return ['space' => $project->getSlug(), 'collection' => $found->getName(), 'item' => $found->getItem(), 'template' => $found->getTemplate()?->getName(), 'sheets' => $found->isSheets()];
    }

    private function projects(): ProjectRepository
    {
        return $this->projectRepository;
    }

    private function access(): PilotAccess
    {
        return $this->pilotAccess;
    }

    // The settings are the admin's, as their pages are
    private function admin(): void
    {
        if (!$this->pilotAccess->isAdmin()) {
            throw new ToolCallException('Only an administrator changes the piloting settings.');
        }
    }

    // A template by its id or its name
    private function found(string $template): PlanTemplate
    {
        $key = trim($template);
        $found = ctype_digit($key) ? $this->planTemplateRepository->find((int) $key) : $this->planTemplateRepository->findOneBy(['name' => $key]);

        return $found instanceof PlanTemplate ? $found : throw new ToolCallException(sprintf('No template "%s".', $template));
    }

    // A name another template does not hold already
    private function rename(PlanTemplate $template, string $name): void
    {
        $name = mb_substr($name, 0, 100);
        $other = $this->planTemplateRepository->findOneBy(['name' => $name]);
        if (null !== $other && $other !== $template) {
            throw new ToolCallException(sprintf('A template is already named "%s".', $name));
        }
        $template->setName($name);
    }

    // The steps given checked as the settings form checks them, then kept as the template keeps them
    /**
     * @param list<array<string, mixed>> $steps
     *
     * @return list<array{title: string, action?: string, params?: array<string, string>, after?: list<int>, group?: string}>
     */
    private function steps(array $steps): array
    {
        $kept = [];
        foreach ($steps as $index => $step) {
            $kept[] = $this->step($step, $index + 1, \count($steps));
        }

        return $kept;
    }

    // One step checked: a title, a declared action, named parameters, steps waited for among the others, a short group
    /**
     * @param array<string, mixed> $step
     *
     * @return array{title: string, action?: string, params?: array<string, string>, after?: list<int>, group?: string}
     */
    private function step(array $step, int $number, int $count): array
    {
        $title = trim(\is_string($step['title'] ?? null) ? $step['title'] : '');
        $group = trim(\is_string($step['group'] ?? null) ? $step['group'] : '');
        if ('' === $title || mb_strlen($title) > 500 || mb_strlen($group) > 50) {
            throw new ToolCallException(sprintf('Step %d needs a title of 500 characters at most and a group of 50 at most.', $number));
        }
        $action = trim(\is_string($step['action'] ?? null) ? $step['action'] : '');
        if ('' !== $action && !$this->actionRepository->findOneBy(['key' => $action]) instanceof Action) {
            throw new ToolCallException(sprintf('Step %d names the action "%s", which no machine declares.', $number, $action));
        }

        return new PlanStep($title, '' === $action ? null : $action, self::params($step['params'] ?? null), $this->after($step, $number, $count), $group)->toArray();
    }

    // The parameters of a step trimmed as the form reads them, a value that is no text or a bad name dropped
    /** @return array<string, string> */
    private static function params(mixed $given): array
    {
        $params = [];
        foreach (\is_array($given) ? $given : [] as $name => $value) {
            if (\is_scalar($value) && 1 === preg_match(PlanStep::PARAM_NAME, trim((string) $name))) {
                $params[trim((string) $name)] = trim((string) $value);
            }
        }

        return $params;
    }

    // The steps a step waits for, written as the form takes them: empty for the step before, « - » for none
    /** @param array<string, mixed> $step */
    private function after(array $step, int $number, int $count): string
    {
        if (!\array_key_exists('after', $step) || null === $step['after']) {
            return '';
        }
        $after = \is_array($step['after']) ? array_map(intval(...), $step['after']) : [0];
        if ([] !== array_filter($after, static fn (int $need): bool => $need < 1 || $need > $count || $need === $number)) {
            throw new ToolCallException(sprintf('Step %d waits for a step it cannot: the numbers go from 1 to %d, itself left out.', $number, $count));
        }

        return [] === $after ? '-' : implode(', ', $after);
    }

    // The spaces given replacing those of a collection of spaces, an unknown one refused
    /**
     * @param Collection<int, Project> $projects
     * @param list<string>             $slugs
     */
    private function offer(Collection $projects, array $slugs): void
    {
        $wanted = [];
        foreach ($slugs as $slug) {
            $wanted[] = $this->projectRepository->findOneBy(['slug' => trim((string) $slug)]) ?? throw new ToolCallException(sprintf('No space "%s".', $slug));
        }
        $projects->clear();
        foreach (array_unique($wanted, \SORT_REGULAR) as $project) {
            $projects->add($project);
        }
    }

    // The collection named in the space, or a new one after the others when none is named
    private function cardSet(Project $project, ?string $collection, ?string $name): CardSet
    {
        if (null === $this->text($collection)) {
            if (null === $this->text($name)) {
                throw new ToolCallException('A new collection takes a name.');
            }

            return new CardSet($project, mb_substr((string) $this->text($name), 0, 100))->setPosition(\count($this->cardSetRepository->findOfProject($project)));
        }

        return $this->cardSetRepository->findOneBy(['project' => $project, 'name' => trim((string) $collection)]) ?? throw new ToolCallException(sprintf('No collection "%s" in the space "%s".', $collection, $project->getSlug()));
    }

    // A template the space offers, as the collection's gear lists them, one it does not offer answered as one that does not exist
    private function offeredTemplate(Project $project, string $template): PlanTemplate
    {
        $key = trim($template);
        foreach ($this->planTemplateRepository->findOfferedTo($project) as $offered) {
            if ($offered->getName() === $key || (string) $offered->getId() === $key) {
                return $offered;
            }
        }

        throw new ToolCallException(sprintf('The space "%s" does not offer the template "%s".', $project->getSlug(), $template));
    }

    // A template as the tools give it, its steps numbered from 1
    /** @return array{id: ?int, name: string, spaces: list<string>, open: int, steps: list<array{number: int, title: string, action?: string, params?: array<string, string>, after?: list<int>, group?: string}>} */
    private function described(PlanTemplate $template): array
    {
        $steps = [];
        foreach ($template->getSteps() as $index => $step) {
            $steps[] = ['number' => $index + 1] + $step;
        }

        return ['id' => $template->getId(), 'name' => $template->getName(), 'spaces' => self::slugs($template->getProjects()), 'open' => \count($this->cardRepository->findOpenOfTemplate($template)), 'steps' => $steps];
    }

    // A machine as the tools give it
    /** @return array{name: string, parallel: int, spaces: list<string>, online: bool, seen: ?string, actions: list<string>} */
    private static function machine(Machine $machine, \DateTimeImmutable $now): array
    {
        return ['name' => $machine->getName(), 'parallel' => $machine->getParallel(), 'spaces' => self::slugs($machine->getProjects()), 'online' => $machine->isOnline($now), 'seen' => $machine->getLastSeenAt()?->format('Y-m-d H:i'), 'actions' => array_map(strval(...), array_keys($machine->getCapabilities()))];
    }

    // The slugs of some spaces
    /**
     * @param Collection<int, Project> $projects
     *
     * @return list<string>
     */
    private static function slugs(Collection $projects): array
    {
        return array_values(array_map(static fn (Project $project): string => $project->getSlug(), $projects->toArray()));
    }
}
