<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Mcp;

use c975L\PilotBundle\Entity\Card;
use c975L\PilotBundle\Entity\CardSet;
use c975L\PilotBundle\Entity\Doc;
use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Entity\Run;
use c975L\PilotBundle\Exception\FlowException;
use c975L\PilotBundle\Repository\CardRepository;
use c975L\PilotBundle\Repository\CardSetRepository;
use c975L\PilotBundle\Repository\DocRepository;
use c975L\PilotBundle\Repository\ProjectRepository;
use c975L\PilotBundle\Security\PilotAccess;
use c975L\PilotBundle\Service\CardFiller;
use c975L\PilotBundle\Service\CardFlow;
use c975L\PilotBundle\Service\CardMarkdown;
use c975L\PilotBundle\Service\CardRemoval;
use c975L\PilotBundle\Service\PilotUrls;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Server\Exception\ToolCallException;
use Psr\Clock\ClockInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// The piloting as MCP tools, acting with the rights of the agent token's account
class PilotTools
{
    use AccountTools;

    // Cards a search or a list gives at most
    public const int LIMIT = 50;

    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly CardRepository $cardRepository,
        private readonly CardFiller $cardFiller,
        private readonly CardFlow $cardFlow,
        private readonly CardMarkdown $cardMarkdown,
        private readonly PilotUrls $pilotUrls,
        private readonly EntityManagerInterface $entityManager,
        private readonly ClockInterface $clock,
        private readonly CardSetRepository $cardSetRepository,
        private readonly CardRemoval $cardRemoval,
        private readonly TranslatorInterface $translator,
        private readonly DocRepository $docRepository,
        private readonly PilotAccess $pilotAccess,
    ) {
    }

    // The spaces served, with their open cards counted and the names of their collections
    /** @return list<array{slug: string, name: string, open: int, collections: list<string>}> */
    public function spaces(): array
    {
        $scope = $this->scope();
        $open = $this->cardRepository->countOpenByProject($scope);

        return array_map(fn (Project $project): array => [
            'slug' => $project->getSlug(),
            'name' => $project->getName(),
            'open' => $open[(int) $project->getId()] ?? 0,
            'collections' => array_map(static fn (CardSet $cardSet): string => $cardSet->getName(), $this->cardSetRepository->findOfProject($project)),
        ], $this->projectRepository->findOrdered($scope));
    }

    // Cards whose title or note holds the words, else the open cards of a space, else the open cards due today or late
    /**
     * @param ?string $space  slug of a space, to list its open cards
     * @param ?string $search words looked for in the titles and notes, done cards included
     *
     * @return list<array{id: ?int, title: string, space: string, collection: ?string, status: string, due: ?string, steps: string}>
     */
    public function cards(?string $space = null, ?string $search = null): array
    {
        $scope = $this->scope();
        $words = trim((string) $search);
        $cards = match (true) {
            '' !== $words => $this->cardRepository->search($words, $scope, self::LIMIT),
            null !== $space && '' !== trim($space) => $this->cardRepository->findOpen($this->space($space), $scope),
            default => $this->cardRepository->findDatedUntil($this->clock->now(), $scope),
        };

        return array_map($this->summary(...), \array_slice($cards, 0, self::LIMIT));
    }

    // A card as markdown, with the index of each step and comment to tick one or answer it, the machine action a step names and where its last run stands
    /**
     * @param int $card id of the card
     *
     * @return array{url: string, markdown: string, steps: list<array{index: int, text: string, done: bool, action?: string, run?: string, message?: string}>, comments: list<array{index: int, text: string, done: bool}>}
     */
    public function card(int $card): array
    {
        $found = $this->served($card);

        return [
            'url' => $this->url($found),
            'markdown' => $this->cardMarkdown->write($found),
            'steps' => $this->withRuns($found, $this->indexed($found->getSteps())),
            'comments' => $this->indexed($found->getFeedback()),
        ];
    }

    // A card added to a space, in its first collection, with its note and its date when given
    /**
     * @param string  $space slug of the space
     * @param string  $title title of the card
     * @param ?string $note  free text under the title
     * @param ?string $due   day it is planned for, as YYYY-MM-DD, left in the backlog without one
     *
     * @return array{id: ?int, url: string}
     */
    public function addCard(string $space, string $title, ?string $note = null, ?string $due = null): array
    {
        $project = $this->space($space, PilotAccess::EDIT);
        $title = trim($title);
        if ('' === $title) {
            throw new ToolCallException('The title is empty.');
        }
        $card = $this->cardFiller->card($project, $title)->setNote($this->text($note))->setDue($this->day($due));
        $this->entityManager->persist($card);
        $this->entityManager->flush();

        return ['id' => $card->getId(), 'url' => $this->url($card)];
    }

    // A step or a comment of a card ticked, or unticked, as its page ticks them, one already in that state left as it is so a call repeated changes nothing
    /**
     * @param int  $card id of the card
     * @param int  $step index of the step or comment, as the card tool gives it
     * @param bool $done false to untick it
     *
     * @return array{done: bool}
     */
    public function tickStep(int $card, int $step, bool $done = true): array
    {
        $found = $this->served($card, PilotAccess::WORK);
        if (!isset($found->getChecklist()[$step])) {
            throw new ToolCallException(sprintf('The card has no step or comment %d.', $step));
        }
        if ($done !== ($found->getChecklist()[$step]['done'] ?? false)) {
            $this->cardFlow->tick($found, $step);
            $this->entityManager->flush();
        }

        return ['done' => $found->getChecklist()[$step]['done'] ?? false];
    }

    // A comment left at the end of a card, a done card opening again
    /**
     * @param int    $card id of the card
     * @param string $text the comment, 500 characters at most
     *
     * @return array{index: int}
     */
    public function comment(int $card, string $text): array
    {
        $found = $this->served($card, PilotAccess::WORK);
        if ('' === trim($text)) {
            throw new ToolCallException('The comment is empty.');
        }
        $this->flow(static fn (): Card => $found->addFeedback($text));
        $this->entityManager->flush();

        return ['index' => (int) array_key_last($found->getChecklist())];
    }

    // A card changed: only the fields given, an empty note clearing it, an empty day sending it to the backlog, moved to another space served, then to one of the collections of its space by name
    /**
     * @param int     $card       id of the card
     * @param ?string $title      new title
     * @param ?string $note       new note, empty to clear it
     * @param ?string $due        day it is planned for, as YYYY-MM-DD, empty for the backlog
     * @param ?string $space      slug of the space to move it to
     * @param ?string $collection name of a collection of its space, as pilot_spaces lists them
     * @param ?string $doc        slug of a file of its space to attach, empty to detach it
     *
     * @return array{url: string}
     */
    public function editCard(int $card, ?string $title = null, ?string $note = null, ?string $due = null, ?string $space = null, ?string $collection = null, ?string $doc = null): array
    {
        $found = $this->served($card, PilotAccess::EDIT);
        if (null !== $this->text($title)) {
            $found->setTitle(mb_substr((string) $this->text($title), 0, 500));
        }
        if (null !== $note) {
            $found->setNote($this->text($note));
        }
        if (null !== $due) {
            $found->setDue($this->day($due));
        }
        if (null !== $this->text($space)) {
            $this->move($found, $this->space((string) $space, PilotAccess::EDIT));
        }
        if (null !== $this->text($collection)) {
            $found->setCardSet($this->collection($found, (string) $collection));
        }
        if (null !== $doc) {
            $found->setDoc($this->file($found, $doc));
        }
        $this->entityManager->flush();

        return ['url' => $this->url($found)];
    }

    // Steps added after the card's last one, before its feedback, empty ones left aside; a done card opens again, a card following a checklist template refused
    /**
     * @param int          $card  id of the card
     * @param list<string> $steps texts of the steps, in their order
     *
     * @return array{steps: list<array{index: int, text: string, done: bool}>}
     */
    public function addSteps(int $card, array $steps): array
    {
        $found = $this->served($card, PilotAccess::EDIT);
        if (!$found->takesStepsByHand()) {
            throw new ToolCallException('The card follows a checklist template: add the steps to its template.');
        }
        $this->flow(static function () use ($found, $steps): void {
            foreach ($steps as $step) {
                $found->addStep((string) $step);
            }
        });
        $this->entityManager->flush();

        return ['steps' => $this->indexed($found->getSteps())];
    }

    // A card marked done, or opened again with done false; refused while a machine works on it
    /**
     * @param int  $card id of the card
     * @param bool $done false to open it again
     *
     * @return array{status: string}
     */
    public function closeCard(int $card, bool $done = true): array
    {
        $found = $this->served($card, PilotAccess::EDIT);
        $this->flow(fn (): Card => $done ? $this->cardFlow->complete($found) : $found->markDone(null));
        $this->entityManager->flush();

        return ['status' => $found->getStatus()->value];
    }

    // The machine action a step names queued for a machine of its space, after any run already queued; refused while a step it waits for is still to tick
    /**
     * @param int $card id of the card
     * @param int $step index of the step, as the card tool gives it, the step naming an action
     *
     * @return array{run: ?int, status: string}
     */
    public function runStep(int $card, int $step): array
    {
        $found = $this->served($card, PilotAccess::RUN);
        if (null === ($found->getSteps()[$step]['action'] ?? null)) {
            throw new ToolCallException(sprintf('The card has no step %d naming a machine action.', $step));
        }
        $run = $this->flow(fn (): Run => $this->cardFlow->runStep($found, $step));
        $this->entityManager->persist($run);
        $this->entityManager->flush();

        return ['run' => $run->getId(), 'status' => $run->getStatus()->value];
    }

    // A run of a card withdrawn as its page does, the given one or else the last: one still queued never leaves, one a machine holds stopped at its next sign of life; refused when none is queued or under way
    /**
     * @param int  $card id of the card
     * @param ?int $run  id of the run, as pilot_run_step gives it, the card's last run without one
     *
     * @return array{url: string, run: ?int, status: string, cardStatus: string}
     */
    public function cancelRun(int $card, ?int $run = null): array
    {
        $found = $this->served($card, PilotAccess::RUN);
        $cancelled = $this->flow(fn (): Run => $this->cardFlow->cancel($found, $run));
        $this->entityManager->flush();

        return ['url' => $this->url($found), 'run' => $cancelled->getId(), 'status' => $cancelled->getStatus()->value, 'cardStatus' => $found->getStatus()->value];
    }

    // What a machine produced accepted as its page does: a run started by a checklist step ticks that step, any other closes the card; refused when nothing waits for validation
    /**
     * @param int $card id of the card, its status review
     *
     * @return array{url: string, status: string}
     */
    public function validateRun(int $card): array
    {
        $found = $this->served($card, PilotAccess::RUN);
        $this->flow(fn (): Card => $this->cardFlow->validate($found));
        $this->entityManager->flush();

        return ['url' => $this->url($found), 'status' => $found->getStatus()->value];
    }

    // A card merged into another one and deleted: its note, steps (ticked ones staying so) and feedback carried over; refused while a machine works on either or past the checklist's ceiling
    /**
     * @param int $card id of the card merged and deleted
     * @param int $into id of the card it is merged into
     *
     * @return array{url: string, steps: list<array{index: int, text: string, done: bool}>}
     */
    public function mergeCards(int $card, int $into): array
    {
        $from = $this->served($card, PilotAccess::DELETE);
        $kept = $this->served($into, PilotAccess::EDIT);
        if ($from === $kept) {
            throw new ToolCallException('A card is not merged into itself.');
        }
        if ([] !== $kept->activeRuns() || [] !== $from->activeRuns()) {
            throw new ToolCallException($this->translator->trans('flow.not_now', [], 'pilot'));
        }

        // A card following a checklist template takes its steps from the template alone
        if (!$kept->takesStepsByHand() && [] !== $from->getSteps()) {
            throw new ToolCallException('The card follows a checklist template: add the steps to its template.');
        }
        $this->flow(static fn (): Card => $kept->absorb($from));
        $this->cardRemoval->remove($from);

        return ['url' => $this->url($kept), 'steps' => $this->indexed($kept->getSteps())];
    }

    // A card deleted for good with its runs and their files; refused while a machine works on it
    /**
     * @param int $card id of the card
     *
     * @return array{deleted: int}
     */
    public function deleteCard(int $card): array
    {
        $found = $this->served($card, PilotAccess::DELETE);
        $this->flow(fn () => $this->cardRemoval->remove($found));

        return ['deleted' => $card];
    }

    // The repository the spaces are found in
    private function projects(): ProjectRepository
    {
        return $this->projectRepository;
    }

    // The account's rights on the piloting
    private function access(): PilotAccess
    {
        return $this->pilotAccess;
    }

    // A card by its id at the level asked: one the account does not see refused as unknown, one it may not change at that level said so
    private function served(int $id, string $level = PilotAccess::VIEW): Card
    {
        $card = $this->cardRepository->find($id);
        if (!$card instanceof Card || !$this->pilotAccess->can($card, PilotAccess::VIEW)) {
            throw new ToolCallException(sprintf('No card %d.', $id));
        }
        if (!$this->pilotAccess->can($card, $level)) {
            throw new ToolCallException(sprintf('This account may not do that on card %d.', $id));
        }

        return $card;
    }

    // A gesture of the piloting run, its refusal given back to the agent in the site's words
    private function flow(callable $gesture): mixed
    {
        try {
            return $gesture();
        } catch (FlowException $exception) {
            throw new ToolCallException($exception->reason->trans($this->translator));
        }
    }

    // The card moved to a space served, a card of a plan staying with its plan
    private function move(Card $card, Project $project): void
    {
        if ($project !== $card->getProject() && null !== $card->getPlan()) {
            throw new ToolCallException($this->translator->trans('label.card_move_plan', [], 'pilot'));
        }
        $card->moveTo($project);
    }

    // A file of the card's space by its slug, none for an empty one
    private function file(Card $card, string $slug): ?Doc
    {
        if (null === $this->text($slug)) {
            return null;
        }
        $doc = $this->docRepository->findOneBy(['slug' => trim($slug)]);
        if (!$doc instanceof Doc || $doc->getProject() !== $card->getProject()) {
            throw new ToolCallException(sprintf('No file "%s" in this space.', $slug));
        }

        return $doc;
    }

    // A collection of the card's space by its name, the names there given back when none matches
    private function collection(Card $card, string $name): CardSet
    {
        $collections = $this->cardSetRepository->findOfProject($card->getProject());
        foreach ($collections as $cardSet) {
            if (0 === strcasecmp($cardSet->getName(), trim($name))) {
                return $cardSet;
            }
        }

        throw new ToolCallException(sprintf('No collection "%s" in this space, only: %s.', $name, implode(', ', array_map(static fn (CardSet $cardSet): string => $cardSet->getName(), $collections))));
    }

    // A card in a list: its state and how many of its steps are ticked
    /** @return array{id: ?int, title: string, space: string, collection: ?string, status: string, due: ?string, steps: string} */
    private function summary(Card $card): array
    {
        $steps = $card->getSteps();

        return [
            'id' => $card->getId(),
            'title' => $card->getTitle(),
            'space' => $card->getProject()->getSlug(),
            'collection' => $card->getCardSet()?->getName(),
            'status' => $card->getStatus()->value,
            'due' => $card->getDue()?->format('Y-m-d'),
            'steps' => \count(array_filter($steps, static fn (array $step): bool => $step['done'] ?? false)) . '/' . \count($steps),
        ];
    }

    // Steps with the action they name and the state and message of their last run
    /**
     * @param list<array{index: int, text: string, done: bool}> $steps
     *
     * @return list<array{index: int, text: string, done: bool, action?: string, run?: string, message?: string}>
     */
    private function withRuns(Card $card, array $steps): array
    {
        $checklist = $card->getChecklist();
        $runs = $card->stepRuns();

        return array_map(static fn (array $step): array => array_filter([
            ...$step,
            'action' => $checklist[$step['index']]['action'] ?? null,
            'run' => ($runs[$step['index']] ?? null)?->getStatus()->value,
            'message' => ($runs[$step['index']] ?? null)?->getMessage(),
        ], static fn (mixed $value): bool => null !== $value), $steps);
    }

    // Lines of a checklist with their index in it
    /**
     * @param array<int, array{t: string, done?: bool}> $lines
     *
     * @return list<array{index: int, text: string, done: bool}>
     */
    private function indexed(array $lines): array
    {
        $indexed = [];
        foreach ($lines as $index => $line) {
            $indexed[] = ['index' => $index, 'text' => $line['t'], 'done' => $line['done'] ?? false];
        }

        return $indexed;
    }

    // A card as an absolute address opening it over its space's page, for the agent to hand it on
    private function url(Card $card): string
    {
        return $this->pilotUrls->shared($card);
    }

    // A day given as YYYY-MM-DD, null without one, anything else refused
    private function day(?string $due): ?\DateTimeImmutable
    {
        if (null === $this->text($due)) {
            return null;
        }
        $day = \DateTimeImmutable::createFromFormat('!Y-m-d', trim((string) $due));
        if (false === $day || $day->format('Y-m-d') !== trim((string) $due)) {
            throw new ToolCallException(sprintf('The day "%s" is not YYYY-MM-DD.', $due));
        }

        return $day;
    }
}
