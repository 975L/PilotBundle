<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Entity;

use c975L\ConfigBundle\Contract\UserInterface;
use c975L\PilotBundle\Enum\CardKind;
use c975L\PilotBundle\Enum\CardStatus;
use c975L\PilotBundle\Enum\Executor;
use c975L\PilotBundle\Repository\CardRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

// A card of a project: in the backlog without a date, on the planning with one, a step when it belongs to a plan
#[ORM\Entity(repositoryClass: CardRepository::class)]
#[ORM\Table(name: 'pilot_card')]
#[ORM\Index(columns: ['due'])]
class Card implements \Stringable
{
    // Where the pictures of the cards are kept, from the project's root
    public const string IMAGE_DIRECTORY = 'private/pilot/cards';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'cards')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?Plan $plan = null;

    // The checklist template the card was laid from outside any plan, whose saved steps it keeps following
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?PlanTemplate $template = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $position = 0;

    // Place given by hand inside a backlog or a day, apart from the position that chains a plan
    #[ORM\Column(options: ['default' => 0])]
    private int $rank = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $due = null;

    #[ORM\Column(enumType: CardStatus::class)]
    private CardStatus $status = CardStatus::Ready;

    #[ORM\Column(enumType: Executor::class)]
    private Executor $executor = Executor::Human;

    #[ORM\Column(enumType: CardKind::class)]
    private CardKind $kind = CardKind::Task;

    // Key the runner looks up in its allowlist, and its parameters
    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Length(max: 50)]
    private ?string $action = null;

    /** @var array<string, string> */
    #[ORM\Column(type: Types::JSON)]
    private array $params = [];

    // Last comment of a send-back, passed to the next run
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

    // Sub-steps ticked one by one: text, the group gathering it with its neighbours, done and the day it was, what a step spends of a credit (its key, the amount, whether it is a guess), the machine action a step leaves for once the one before is ticked; the feedback (a fix, an idea, a question still to decide) after the steps
    /** @var list<array{t: string, done?: bool, doneAt?: string, cost?: array{credit: string, amount: int, guess?: bool}, action?: string, params?: array<string, string>, after?: list<string>, feedback?: bool, ask?: bool, g?: string, due?: string}> */
    #[ORM\Column(type: Types::JSON)]
    private array $checklist = [];

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Doc $doc = null;

    // Who an answer is awaited from (a company, a person), the card waiting on them until it is cleared
    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $waitingOn = null;

    // Where the card's work comes from (a reply, a page) and where its result lives once done, plain http(s) links only
    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Length(max: 500)]
    private ?string $sourceUrl = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Length(max: 500)]
    private ?string $resultUrl = null;

    // The collection of its space the card belongs to, none for a card of the space itself
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?CardSet $cardSet = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $doneAt = null;

    // File name of its picture (a sheet's portrait), kept under IMAGE_DIRECTORY
    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $image = null;

    // The secret of the read-only link sharing the card outside the back office, none while it is not shared
    #[ORM\Column(length: 32, unique: true, nullable: true)]
    #[Assert\Length(max: 32)]
    private ?string $shareToken = null;

    // The secret of the link letting whoever holds it read and rewrite the card, none while it is not given
    #[ORM\Column(length: 32, unique: true, nullable: true)]
    #[Assert\Length(max: 32)]
    private ?string $editToken = null;

    // Project and id of the card in the source it was imported from ("catalog:item-1"), so a second import updates instead of duplicating
    #[ORM\Column(length: 100, unique: true, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $sourceId = null;

    // The members the card is given to, the only cards a guest of its space sees
    /** @var Collection<int, UserInterface> */
    #[ORM\ManyToMany(targetEntity: UserInterface::class)]
    #[ORM\JoinTable(name: 'pilot_card_assignee')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'user_id', onDelete: 'CASCADE')]
    private Collection $assignees;

    /** @var Collection<int, Run> */
    #[ORM\OneToMany(targetEntity: Run::class, mappedBy: 'card', cascade: ['persist'])]
    #[ORM\OrderBy(['id' => \SortDirection::Ascending])]
    private Collection $runs;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Project $project,
        #[ORM\Column(length: 500)]
        #[Assert\Length(max: 500)]
        private string $title,
    ) {
        $this->runs = new ArrayCollection();
        $this->assignees = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->title;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    // Moves the card to another space, out of its collection, leaving behind a file of the space it leaves
    public function moveTo(Project $project): static
    {
        $this->project = $project;
        $this->cardSet = null;
        if (null !== $this->doc?->getProject() && $this->doc->getProject() !== $project) {
            $this->doc = null;
        }

        return $this;
    }

    public function getProject(): Project
    {
        return $this->project;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    // The title, cut to the length of its column
    public function setTitle(string $title): static
    {
        $this->title = mb_substr($title, 0, 500);

        return $this;
    }

    public function getPlan(): ?Plan
    {
        return $this->plan;
    }

    /** @return Collection<int, UserInterface> */
    public function getAssignees(): Collection
    {
        return $this->assignees;
    }

    // Gives the card to a member, once
    public function addAssignee(UserInterface $user): static
    {
        if (!$this->assignees->contains($user)) {
            $this->assignees->add($user);
        }

        return $this;
    }

    public function removeAssignee(UserInterface $user): static
    {
        $this->assignees->removeElement($user);

        return $this;
    }

    public function isAssignedTo(UserInterface $user): bool
    {
        return $this->assignees->contains($user);
    }

    // Makes the card follow a checklist template outside any plan, or none
    public function follow(?PlanTemplate $template): static
    {
        $this->template = $template;

        return $this;
    }

    // The template the card comes from: its plan's, otherwise the checklist one it was laid from
    public function getTemplate(): ?PlanTemplate
    {
        return $this->plan?->getTemplate() ?? $this->template;
    }

    // Whether the card is done
    public function isDone(): bool
    {
        return CardStatus::Done === $this->status;
    }

    // Places the card in a plan, at a position from 1
    public function placeIn(Plan $plan, int $position): static
    {
        $this->plan = $plan;
        $this->position = $position;
        $plan->addCard($this);

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getRank(): int
    {
        return $this->rank;
    }

    public function setRank(int $rank): static
    {
        $this->rank = $rank;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

        return $this;
    }

    public function getDue(): ?\DateTimeImmutable
    {
        return $this->due;
    }

    public function setDue(?\DateTimeImmutable $due): static
    {
        $this->due = $due;

        return $this;
    }

    // In the backlog as long as it has no date
    public function isInBacklog(): bool
    {
        return null === $this->due && CardStatus::Done !== $this->status;
    }

    public function getStatus(): CardStatus
    {
        return $this->status;
    }

    public function setStatus(CardStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getExecutor(): Executor
    {
        return $this->executor;
    }

    public function isRunner(): bool
    {
        return Executor::Runner === $this->executor;
    }

    public function getKind(): CardKind
    {
        return $this->kind;
    }

    public function isValidation(): bool
    {
        return CardKind::Validate === $this->kind;
    }

    // What the card does and who does it
    /**
     * @param array<string, string> $params
     */
    public function assign(Executor $executor, CardKind $kind = CardKind::Task, ?string $action = null, array $params = []): static
    {
        $this->executor = $executor;
        $this->kind = $kind;
        $this->action = $action;
        $this->params = $params;

        return $this;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    /**
     * @return array<string, string>
     */
    public function getParams(): array
    {
        return $this->params;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): static
    {
        $this->comment = $comment;

        return $this;
    }

    /**
     * @return list<array{t: string, done?: bool, doneAt?: string, cost?: array{credit: string, amount: int, guess?: bool}, action?: string, params?: array<string, string>, after?: list<string>, feedback?: bool, ask?: bool, g?: string, due?: string}>
     */
    public function getChecklist(): array
    {
        return $this->checklist;
    }

    /**
     * @param list<array{t: string, done?: bool, doneAt?: string, cost?: array{credit: string, amount: int, guess?: bool}, action?: string, params?: array<string, string>, after?: list<string>, feedback?: bool, ask?: bool, g?: string, due?: string}> $checklist
     */
    public function setChecklist(array $checklist): static
    {
        $this->checklist = $checklist;

        return $this;
    }

    // Adds a step after the last one, before the feedback, an empty text adding nothing; a done card opens again
    public function addStep(string $text): static
    {
        $text = mb_substr(trim($text), 0, 500);
        if ('' === $text) {
            return $this;
        }
        $at = array_key_first($this->getFeedback()) ?? \count($this->checklist);
        array_splice($this->checklist, $at, 0, [['t' => $text]]);
        foreach ($this->runs as $run) {
            if (null !== $run->getStep() && $run->getStep() >= $at) {
                $run->moveToStep($run->getStep() + 1);
            }
        }

        return $this->reopen();
    }

    // Adds a feedback at the end (a fix, an idea), still to decide when its text starts with « ? », an empty text adding nothing; a done card opens again
    public function addFeedback(string $text): static
    {
        $text = trim($text);
        $ask = str_starts_with($text, '?');
        $text = mb_substr(ltrim($ask ? substr($text, 1) : $text), 0, 500);
        if ('' === $text) {
            return $this;
        }
        $this->checklist[] = ['t' => $text, 'feedback' => true] + ($ask ? ['ask' => true] : []);

        return $this->reopen();
    }

    // A feedback marked still to decide, or decided; a step changes nothing
    public function toggleAsk(int $index): static
    {
        if (!$this->isFeedback($index)) {
            return $this;
        }
        if ($this->checklist[$index]['ask'] ?? false) {
            unset($this->checklist[$index]['ask']);
        } else {
            $this->checklist[$index]['ask'] = true;
        }

        return $this;
    }

    public function isFeedback(int $index): bool
    {
        return $this->checklist[$index]['feedback'] ?? false;
    }

    // The steps of the checklist, the feedback left out, by their index in it
    /** @return array<int, array{t: string, done?: bool, doneAt?: string, cost?: array{credit: string, amount: int, guess?: bool}, action?: string, params?: array<string, string>, after?: list<string>, feedback?: bool, ask?: bool, g?: string, due?: string}> */
    public function getSteps(): array
    {
        return array_filter($this->checklist, static fn (array $step): bool => !($step['feedback'] ?? false));
    }

    // The steps gathered as they follow each other: each run of steps of one group, a step out of any group in a run without a name
    /** @return list<array{group: ?string, steps: array<int, array{t: string, done?: bool, g?: string}>}> */
    public function getStepGroups(): array
    {
        $groups = [];
        $last = false;
        foreach ($this->getSteps() as $index => $step) {
            $group = $step['g'] ?? null;
            if ($group !== $last) {
                $groups[] = ['group' => $group, 'steps' => []];
                $last = $group;
            }
            $groups[\count($groups) - 1]['steps'][$index] = $step;
        }

        return $groups;
    }

    // The feedback of the checklist, by its index in it
    /** @return array<int, array{t: string, done?: bool, doneAt?: string, cost?: array{credit: string, amount: int, guess?: bool}, action?: string, params?: array<string, string>, after?: list<string>, feedback?: bool, ask?: bool, g?: string, due?: string}> */
    public function getFeedback(): array
    {
        return array_filter($this->checklist, static fn (array $step): bool => $step['feedback'] ?? false);
    }

    // A done card opened again once one of its boxes is to tick
    private function reopen(): static
    {
        if (CardStatus::Done === $this->status && [] !== array_filter($this->checklist, static fn (array $step): bool => !($step['done'] ?? false))) {
            $this->markDone(null);
        }

        return $this;
    }

    // Rewrites the text of a step, keeping what it spends, names and whether it is done; an empty text removes it, its run under way withdrawn
    public function renameStep(int $index, string $text, \DateTimeImmutable $now): static
    {
        if (!isset($this->checklist[$index])) {
            return $this;
        }
        $text = mb_substr(trim($text), 0, 500);
        if ('' === $text) {
            $steps = $this->checklist;
            unset($steps[$index]);

            return $this->restep(array_values($steps), array_keys($steps), $now);
        }
        $before = self::stepKey($this->checklist[$index]);
        $this->checklist[$index]['t'] = $text;
        $this->renameNeed($before, self::stepKey($this->checklist[$index]));

        return $this;
    }

    // A step known by its machine action, or by its text when it names none
    /** @param array{t: string, action?: string} $step */
    public static function stepKey(array $step): string
    {
        return '' !== ($step['action'] ?? '') ? 'action:' . $step['action'] : 'text:' . $step['t'];
    }

    // Indices of the steps a step waits for: those its "after" names by their key, or the step before when it names none; a feedback waits for nothing
    /** @return list<int> */
    public function stepNeeds(int $index): array
    {
        if (!isset($this->checklist[$index]) || $this->isFeedback($index)) {
            return [];
        }
        if (!isset($this->checklist[$index]['after'])) {
            $before = array_filter(array_keys($this->getSteps()), static fn (int $step): bool => $step < $index);

            return [] === $before ? [] : [max($before)];
        }
        $keys = array_map(self::stepKey(...), $this->checklist);

        return array_values(array_filter(array_map(static fn (string $key): int | false => array_search($key, $keys, true), $this->checklist[$index]['after']), static fn (int | false $need): bool => false !== $need && $need !== $index));
    }

    // Texts of the steps a step still waits for, not ticked yet
    /** @return list<string> */
    public function stepAwaits(int $index): array
    {
        return array_values(array_map(fn (int $need): string => $this->checklist[$need]['t'], array_filter($this->stepNeeds($index), fn (int $need): bool => !($this->checklist[$need]['done'] ?? false))));
    }

    // Whether a step still waits for one of the steps it needs to be ticked
    public function isStepWaiting(int $index): bool
    {
        return [] !== $this->stepAwaits($index);
    }

    // A step renamed stays the one the others wait for
    private function renameNeed(string $before, string $after): void
    {
        foreach ($this->checklist as $index => $step) {
            if (isset($step['after'])) {
                $this->checklist[$index]['after'] = array_map(static fn (string $key): string => $before === $key ? $after : $key, $step['after']);
            }
        }
    }

    // Puts the steps in a new order, given as their current indices, the feedback left out of it kept after; the runs follow the step they do; anything but a full reordering is ignored
    /** @param list<int> $order */
    public function reorderSteps(array $order): static
    {
        $order = [...$order, ...array_values(array_diff(array_keys($this->getFeedback()), $order))];
        $indices = array_keys($this->checklist);
        $sorted = $order;
        sort($sorted);
        if ($sorted !== $indices) {
            return $this;
        }
        $this->checklist = array_map(fn (int $index): array => $this->checklist[$index], $order);
        $moved = array_flip($order);
        foreach ($this->runs as $run) {
            if (null !== $run->getStep()) {
                $run->moveToStep($moved[$run->getStep()] ?? $run->getStep());
            }
        }

        return $this;
    }

    // Lays new steps, each given with the index it had or null for a new one; the runs follow their step, one whose step left leaving it (withdrawn while under way), the card back to do once nothing runs or once a done one gets a box to tick
    /**
     * @param list<array{t: string, done?: bool, doneAt?: string, cost?: array{credit: string, amount: int, guess?: bool}, action?: string, params?: array<string, string>, after?: list<string>, feedback?: bool, ask?: bool, g?: string, due?: string}> $steps
     * @param list<?int>                                                                                                                                                                                                                                  $from
     */
    public function restep(array $steps, array $from, \DateTimeImmutable $now): static
    {
        $moved = array_flip(array_filter($from, static fn (?int $index): bool => null !== $index));
        foreach ($this->runs as $run) {
            $step = $run->getStep();
            if (null !== $step && isset($moved[$step])) {
                $run->moveToStep($moved[$step]);
            } elseif (null !== $step) {
                $run->leaveStep($now);
            }
        }
        $this->checklist = $steps;
        if (CardStatus::Running === $this->status && [] === $this->activeRuns()) {
            $this->status = CardStatus::Ready;
        }

        return $this->reopen();
    }

    // Machine action a step hands the card to, none when empty; its parameters go with the action they were given for
    public function setStepAction(int $index, ?string $action): static
    {
        if (!isset($this->checklist[$index])) {
            return $this;
        }
        $action = '' === trim((string) $action) ? null : mb_substr(trim((string) $action), 0, 64);
        $step = $this->checklist[$index];
        if ($action === ($step['action'] ?? null)) {
            return $this;
        }
        unset($step['action'], $step['params']);
        if (null !== $action) {
            $step['action'] = $action;
        }
        $this->checklist[$index] = $step;

        return $this;
    }

    // The day a step is planned for (a release, a publication), none when null; an index out of the list changes nothing
    public function setStepDue(int $index, ?\DateTimeImmutable $due): static
    {
        if (!isset($this->checklist[$index])) {
            return $this;
        }
        unset($this->checklist[$index]['due']);
        if (null !== $due) {
            $this->checklist[$index]['due'] = $due->format('Y-m-d');
        }

        return $this;
    }

    // Ticks a step of the checklist on a day, or unticks it, a done card opening again; an index out of the list changes nothing
    public function toggleStep(int $index, \DateTimeImmutable $day): static
    {
        if (!isset($this->checklist[$index])) {
            return $this;
        }
        $step = $this->checklist[$index];
        if ($step['done'] ?? false) {
            unset($step['done'], $step['doneAt']);
        } else {
            $step['done'] = true;
            $step['doneAt'] = $day->format('Y-m-d');
        }
        $this->checklist[$index] = $step;

        return $this->reopen();
    }

    public function getDoc(): ?Doc
    {
        return $this->doc;
    }

    public function setDoc(?Doc $doc): static
    {
        $this->doc = $doc;

        return $this;
    }

    public function getWaitingOn(): ?string
    {
        return $this->waitingOn;
    }

    // Sets who an answer is awaited from, an empty value ending the wait
    public function setWaitingOn(?string $waitingOn): static
    {
        $waitingOn = null === $waitingOn ? '' : trim($waitingOn);
        $this->waitingOn = '' === $waitingOn ? null : mb_substr($waitingOn, 0, 100);

        return $this;
    }

    public function getSourceUrl(): ?string
    {
        return $this->sourceUrl;
    }

    public function setSourceUrl(?string $url): static
    {
        $this->sourceUrl = self::link($url);

        return $this;
    }

    public function getResultUrl(): ?string
    {
        return $this->resultUrl;
    }

    public function setResultUrl(?string $url): static
    {
        $this->resultUrl = self::link($url);

        return $this;
    }

    // A link kept only when it is a plain http(s) address, anything else (empty, javascript:, a path) dropping it
    private static function link(?string $url): ?string
    {
        $url = trim((string) $url);

        return 1 === preg_match('~^https?://[^\s<>"]+$~i', $url) ? mb_substr($url, 0, 500) : null;
    }

    public function getCardSet(): ?CardSet
    {
        return $this->cardSet;
    }

    // Puts the card in a collection of its own space, or out of any
    public function setCardSet(?CardSet $cardSet): static
    {
        $this->cardSet = $cardSet?->getProject() === $this->project ? $cardSet : null;

        return $this;
    }

    // A new card copied from this one in the same project and collection, following the same template: title marked as a copy, note, date, reference, source, what does it and its steps all unticked; neither its plan, its links, its runs nor its wait
    public function duplicate(string $suffix): self
    {
        $steps = array_map(static function (array $step): array {
            unset($step['done'], $step['doneAt']);

            return $step;
        }, $this->checklist);

        return new self($this->project, mb_substr($this->title . $suffix, 0, 500))
            ->setNote($this->note)
            ->setDue($this->due)
            ->setDoc($this->doc)
            ->setSourceUrl($this->sourceUrl)
            ->setCardSet($this->cardSet)
            ->follow($this->template)
            ->assign($this->executor, $this->kind, $this->action, $this->params)
            ->setChecklist($steps);
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;

        return $this;
    }

    // A card of a collection of sheets: a picture, a title and a pitch, no steps nor state
    public function isSheet(): bool
    {
        return null !== $this->cardSet && $this->cardSet->isSheets();
    }

    public function getShareToken(): ?string
    {
        return $this->shareToken;
    }

    // Shares the card read-only, its secret made once and kept: the link stays the same until sharing stops
    public function share(): static
    {
        $this->shareToken ??= self::newToken();

        return $this;
    }

    // Stops sharing: the old link answers nothing, a later share gets a new one
    public function unshare(): static
    {
        $this->shareToken = null;

        return $this;
    }

    public function getEditToken(): ?string
    {
        return $this->editToken;
    }

    // Gives the card's editing link, its secret made once and kept until it is taken back
    public function lend(): static
    {
        $this->editToken ??= self::newToken();

        return $this;
    }

    // Takes the editing link back: the old one answers nothing
    public function unlend(): static
    {
        $this->editToken = null;

        return $this;
    }

    // A secret of 32 hex characters
    private static function newToken(): string
    {
        return bin2hex(random_bytes(16));
    }

    public function getDoneAt(): ?\DateTimeImmutable
    {
        return $this->doneAt;
    }

    // Marks the card done on a day, or back to do when null
    public function markDone(?\DateTimeImmutable $day): static
    {
        $this->doneAt = $day;
        $this->status = null === $day ? CardStatus::Ready : CardStatus::Done;

        return $this;
    }

    public function getSourceId(): ?string
    {
        return $this->sourceId;
    }

    public function setSourceId(?string $sourceId): static
    {
        $this->sourceId = $sourceId;

        return $this;
    }

    /**
     * @return Collection<int, Run>
     */
    public function getRuns(): Collection
    {
        return $this->runs;
    }

    public function addRun(Run $run): static
    {
        $this->runs->add($run);

        return $this;
    }

    // Most recent execution, null before the first
    public function lastRun(): ?Run
    {
        $last = $this->runs->last();

        return false === $last ? null : $last;
    }

    // Runs waiting in the queue or held by a machine, oldest first
    /** @return list<Run> */
    public function activeRuns(): array
    {
        return array_values(array_filter($this->runs->toArray(), static fn (Run $run): bool => $run->getStatus()->isActive()));
    }

    // Latest run of a checklist step
    public function stepRun(int $index): ?Run
    {
        return $this->stepRuns()[$index] ?? null;
    }

    // Latest run of each checklist step in one pass over the runs, a run of the card's own action counting for the steps naming it when it is the last one
    /** @return array<int, Run> */
    public function stepRuns(): array
    {
        $runs = [];
        foreach ($this->runs as $run) {
            if (isset($this->checklist[$run->getStep() ?? Run::LEFT_STEP])) {
                $runs[(int) $run->getStep()] = $run;
            }
        }
        $last = $this->lastRun();
        if (null === $last || null !== $last->getStep()) {
            return $runs;
        }
        foreach ($this->checklist as $index => $step) {
            if (!isset($runs[$index]) && null !== ($step['action'] ?? null) && $step['action'] === $last->getAction()) {
                $runs[$index] = $last;
            }
        }

        return $runs;
    }
}
