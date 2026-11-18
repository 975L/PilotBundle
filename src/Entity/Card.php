<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Entity;

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
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'cards')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?Plan $plan = null;

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

    // Sub-steps ticked one by one: text, done and the day it was, what a step spends of a credit (its key, the amount, whether it is a guess), and the machine action a step leaves for once the one before is ticked
    /** @var list<array{t: string, done?: bool, doneAt?: string, cost?: array{credit: string, amount: int, guess?: bool}, action?: string, params?: array<string, string>, after?: list<string>}> */
    #[ORM\Column(type: Types::JSON)]
    private array $checklist = [];

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Doc $doc = null;

    // Who an answer is awaited from (a company, a person), the card waiting on them until it is cleared
    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $waitingOn = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $doneAt = null;

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
    }

    public function __toString(): string
    {
        return $this->title;
    }

    public function getId(): ?int
    {
        return $this->id;
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
     * @return list<array{t: string, done?: bool, doneAt?: string, cost?: array{credit: string, amount: int, guess?: bool}, action?: string, params?: array<string, string>, after?: list<string>}>
     */
    public function getChecklist(): array
    {
        return $this->checklist;
    }

    /**
     * @param list<array{t: string, done?: bool, doneAt?: string, cost?: array{credit: string, amount: int, guess?: bool}, action?: string, params?: array<string, string>, after?: list<string>}> $checklist
     */
    public function setChecklist(array $checklist): static
    {
        $this->checklist = $checklist;

        return $this;
    }

    // Adds a step at the end of the checklist, an empty text adding nothing
    public function addStep(string $text): static
    {
        $text = mb_substr(trim($text), 0, 500);
        if ('' !== $text) {
            $this->checklist[] = ['t' => $text];
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

    // Indices of the steps a step waits for: those its "after" names by their key, or the step before when it names none
    /** @return list<int> */
    public function stepNeeds(int $index): array
    {
        if (!isset($this->checklist[$index])) {
            return [];
        }
        if (!isset($this->checklist[$index]['after'])) {
            return $index > 0 ? [$index - 1] : [];
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

    // Puts the steps in a new order, given as their current indices; the runs follow the step they do; anything but a full reordering is ignored
    /** @param list<int> $order */
    public function reorderSteps(array $order): static
    {
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

    // Lays new steps, each given with the index it had or null for a new one; the runs follow their step, one whose step left leaving it (withdrawn while under way), the card back to do once nothing runs
    /**
     * @param list<array{t: string, done?: bool, doneAt?: string, cost?: array{credit: string, amount: int, guess?: bool}, action?: string, params?: array<string, string>, after?: list<string>}> $steps
     * @param list<?int>                                                                                                                                                                           $from
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

        return $this;
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

    // Ticks a step of the checklist on a day, or unticks it; an index out of the list changes nothing
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

        return $this;
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

    // A new card copied from this one in the same project: title marked as a copy, note, date, reference, what does it and its steps all unticked; neither its plan, its links, its runs nor its wait
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
            ->assign($this->executor, $this->kind, $this->action, $this->params)
            ->setChecklist($steps);
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
