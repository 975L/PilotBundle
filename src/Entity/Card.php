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
use c975L\PilotBundle\Enum\CardStatus;
use c975L\PilotBundle\Enum\RunStatus;
use c975L\PilotBundle\Exception\FlowException;
use c975L\PilotBundle\Repository\CardRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

use function Symfony\Component\Translation\t;

// A card of a project: in the backlog without a date, on the planning with one, a step when it belongs to a plan
#[ORM\Entity(repositoryClass: CardRepository::class)]
#[ORM\Table(name: 'pilot_card')]
#[ORM\Index(columns: ['due'])]
class Card implements \Stringable
{
    // Where the pictures of the cards are kept, from the project's root
    public const string IMAGE_DIRECTORY = 'private/pilot/cards';

    // Where the screenshots of the feedback are kept, from the project's root, each one served by its name alone
    public const string FEEDBACK_IMAGE_DIRECTORY = 'private/pilot/feedback';

    // What a runner takes at most as one parameter, the open feedback handed in one pass
    public const int FEEDBACK_PASS_LENGTH = 2000;

    // Most lines a checklist holds, steps and feedback together, so that no link fills a card without end
    public const int MAX_CHECKLIST = 300;

    // Most screenshots one feedback holds
    public const int MAX_FEEDBACK_IMAGES = 3;

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

    // Sub-steps ticked one by one: text, the group gathering it with its neighbours, done and the day it was, what a step spends of a credit (its key, the amount, whether it is a guess), the machine action a step leaves for once the one before is ticked; the feedback (a fix, an idea, the file name of a screenshot, why the machine left it open) after the steps
    /** @var list<array{t: string, done?: bool, doneAt?: string, doneBy?: string, cost?: array{credit: string, amount: int, guess?: bool}, action?: string, params?: array<string, string>, after?: list<string>, feedback?: bool, g?: string, img?: string|list<string>, r?: string, s?: bool}> */
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

    // Whether steps may be added to it by hand, a card following a checklist template taking its steps from it alone
    public function takesStepsByHand(): bool
    {
        return null === $this->getTemplate();
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

    /**
     * @return list<array{t: string, done?: bool, doneAt?: string, doneBy?: string, cost?: array{credit: string, amount: int, guess?: bool}, action?: string, params?: array<string, string>, after?: list<string>, feedback?: bool, g?: string, img?: string|list<string>, r?: string, s?: bool}>
     */
    public function getChecklist(): array
    {
        return $this->checklist;
    }

    /**
     * @param list<array{t: string, done?: bool, doneAt?: string, doneBy?: string, cost?: array{credit: string, amount: int, guess?: bool}, action?: string, params?: array<string, string>, after?: list<string>, feedback?: bool, g?: string, img?: string|list<string>, r?: string, s?: bool}> $checklist
     */
    public function setChecklist(array $checklist): static
    {
        $this->checklist = $checklist;

        return $this;
    }

    // Adds a step after the last one, before the feedback, an empty text adding nothing, a full checklist refusing it; a done card opens again
    public function addStep(string $text): static
    {
        $text = mb_substr(trim($text), 0, 500);
        if ('' === $text) {
            return $this;
        }
        self::expectRoom(\count($this->checklist) + 1);
        $at = array_key_first($this->getFeedback()) ?? \count($this->checklist);
        array_splice($this->checklist, $at, 0, [['t' => $text]]);
        foreach ($this->runs as $run) {
            if (null !== $run->getStep() && $run->getStep() >= $at) {
                $run->moveToStep($run->getStep() + 1);
            }
        }

        return $this->reopen();
    }

    // Adds a feedback at the end (a fix, an idea), an empty text adding nothing, a full checklist refusing it; a done card opens again
    public function addFeedback(string $text): static
    {
        $text = mb_substr(trim($text), 0, 500);
        if ('' === $text) {
            return $this;
        }
        self::expectRoom(\count($this->checklist) + 1);
        $this->checklist[] = ['t' => $text, 'feedback' => true];

        return $this->reopen();
    }

    // Refuses a checklist of more lines than MAX_CHECKLIST, with the reason to show
    public static function expectRoom(int $lines): void
    {
        if ($lines > self::MAX_CHECKLIST) {
            throw new FlowException(t('flow.checklist_full', ['%max%' => self::MAX_CHECKLIST], 'pilot'));
        }
    }

    public function isFeedback(int $index): bool
    {
        return $this->checklist[$index]['feedback'] ?? false;
    }

    // The steps of the checklist, the feedback left out, by their index in it
    /** @return array<int, array{t: string, done?: bool, doneAt?: string, doneBy?: string, cost?: array{credit: string, amount: int, guess?: bool}, action?: string, params?: array<string, string>, after?: list<string>, feedback?: bool, g?: string, img?: string|list<string>, r?: string, s?: bool}> */
    public function getSteps(): array
    {
        return array_filter($this->checklist, static fn (array $step): bool => !($step['feedback'] ?? false));
    }

    // The steps gathered as they follow each other: each run of steps of one group, a step out of any group in a run without a name, cut into its parts, never folded on a card following a checklist template so its ticked steps stay in sight
    /** @return list<array{group: ?string, steps: array<int, array{t: string, done?: bool, g?: string}>, parts: list<array{folded: bool, steps: array<int, array{t: string, done?: bool, g?: string}>}>}> */
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

        $fold = null === $this->getTemplate();

        return array_map(static fn (array $group): array => $group + ['parts' => self::stepParts($group['steps'], $fold)], $groups);
    }

    // Steps in their order, cut where they change between ticked and open: the run of several ticked opening the steps folded when folding is asked, any other ticked one left in its place among the others
    /**
     * @param array<int, array{t: string, done?: bool, g?: string}> $steps
     *
     * @return list<array{folded: bool, steps: array<int, array{t: string, done?: bool, g?: string}>}>
     */
    private static function stepParts(array $steps, bool $fold): array
    {
        $parts = [];
        $last = null;
        foreach ($steps as $index => $step) {
            $done = $step['done'] ?? false;
            if ($done !== $last) {
                $parts[] = ['folded' => $done, 'steps' => []];
                $last = $done;
            }
            $parts[\count($parts) - 1]['steps'][$index] = $step;
        }

        // Only the ticked steps the list starts with are folded, a lone one or one further down staying in its place, joined to its open neighbours
        $merged = [];
        foreach ($parts as $part) {
            $part['folded'] = $fold && $part['folded'] && [] === $merged && \count($part['steps']) > 1;
            $previous = \count($merged) - 1;
            if (!$part['folded'] && $previous >= 0 && !$merged[$previous]['folded']) {
                $merged[$previous]['steps'] += $part['steps'];
                continue;
            }
            $merged[] = $part;
        }

        return $merged;
    }

    // The screenshots of a feedback replaced by those given, kept to MAX_FEEDBACK_IMAGES, none taking the key off; a step changes nothing, the files left being removed by whoever replaced them
    /** @param list<string> $names */
    public function setFeedbackImages(int $index, array $names): static
    {
        if (!$this->isFeedback($index)) {
            return $this;
        }
        unset($this->checklist[$index]['img']);
        $names = \array_slice(array_values(array_unique($names)), 0, self::MAX_FEEDBACK_IMAGES);
        if ([] !== $names) {
            $this->checklist[$index]['img'] = $names;
        }

        return $this;
    }

    // A screenshot added to a feedback, refused once it holds MAX_FEEDBACK_IMAGES
    public function addFeedbackImage(int $index, string $name): static
    {
        $this->expectImageRoom($index);

        return $this->setFeedbackImages($index, [...self::shots($this->checklist[$index] ?? []), $name]);
    }

    // A screenshot taken off a feedback by its name, the others kept in their order
    public function removeFeedbackImage(int $index, string $name): static
    {
        return $this->setFeedbackImages($index, array_values(array_diff(self::shots($this->checklist[$index] ?? []), [$name])));
    }

    // Refuses one more screenshot on a feedback already holding MAX_FEEDBACK_IMAGES, with the reason to show
    public function expectImageRoom(int $index): void
    {
        if (\count(self::shots($this->checklist[$index] ?? [])) >= self::MAX_FEEDBACK_IMAGES) {
            throw new FlowException(t('flow.feedback_images_full', ['%max%' => self::MAX_FEEDBACK_IMAGES], 'pilot'));
        }
    }

    // The screenshots of a line as a list, a single name kept before several were allowed read as one
    /**
     * @param array{img?: string|list<string>} $step
     *
     * @return list<string>
     */
    private static function shots(array $step): array
    {
        return array_values(array_filter((array) ($step['img'] ?? []), static fn (string $name): bool => '' !== $name));
    }

    // Why the machine left a feedback open, shown under it, or none once handled; a step changes nothing
    public function setFeedbackReason(int $index, ?string $reason): static
    {
        if (!$this->isFeedback($index)) {
            return $this;
        }
        unset($this->checklist[$index]['r']);
        if ('' !== trim((string) $reason)) {
            $this->checklist[$index]['r'] = mb_substr(trim((string) $reason), 0, 255);
        }

        return $this;
    }

    // File names of the screenshots its feedback holds
    /** @return list<string> */
    public function getFeedbackImages(): array
    {
        return array_merge(...array_values(array_map(self::shots(...), $this->getFeedback())));
    }

    // The feedback of the checklist, by its index in it, its screenshots always a list
    /** @return array<int, array{t: string, done?: bool, doneAt?: string, doneBy?: string, cost?: array{credit: string, amount: int, guess?: bool}, action?: string, params?: array<string, string>, after?: list<string>, feedback?: bool, g?: string, img?: list<string>, r?: string, s?: bool}> */
    public function getFeedback(): array
    {
        $feedback = array_filter($this->checklist, static fn (array $step): bool => $step['feedback'] ?? false);

        return array_map(static function (array $step): array {
            $shots = self::shots($step);
            unset($step['img']);

            return [] === $shots ? $step : $step + ['img' => $shots];
        }, $feedback);
    }

    // The feedback cut where it changes between ticked and open, the run of ticked lines it starts with folded
    /** @return list<array{folded: bool, steps: array<int, array{t: string, done?: bool, g?: string, img?: list<string>}>}> */
    public function getFeedbackParts(): array
    {
        return self::stepParts($this->getFeedback(), true);
    }

    // The open feedback handed to a machine in one pass, numbered one per line, whole lines only within what a runner takes as one parameter, the rest waiting for the next pass
    /** @return list<string> */
    public function feedbackToHand(): array
    {
        $lines = [];
        $length = 0;
        foreach ($this->getFeedback() as $feedback) {
            if ($feedback['done'] ?? false) {
                continue;
            }
            $line = (\count($lines) + 1) . '. ' . str_replace(["\r", "\n"], ' ', $feedback['t']);
            if ($length + mb_strlen($line) > self::FEEDBACK_PASS_LENGTH) {
                break;
            }
            $lines[] = $line;
            $length += mb_strlen($line) + 1;
        }

        return $lines;
    }

    // How many open feedback lines wait for a pass after the next one, too long to go with it
    public function getFeedbackHeldOver(): int
    {
        return \count(array_filter($this->getFeedback(), static fn (array $feedback): bool => !($feedback['done'] ?? false))) - \count($this->feedbackToHand());
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
        // The mark of a line once written through a shared link, which no longer exists
        unset($this->checklist[$index]['s']);
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

    // Indices of the steps not ticked yet a step waits for, those they wait for in turn included, in the checklist's order
    /** @return list<int> */
    public function stepAwaitsAll(int $index): array
    {
        $found = [];
        $todo = [$index];
        while ([] !== $todo) {
            foreach ($this->stepNeeds((int) array_pop($todo)) as $need) {
                if ($need !== $index && !isset($found[$need]) && !($this->checklist[$need]['done'] ?? false)) {
                    $found[$need] = $need;
                    $todo[] = $need;
                }
            }
        }
        sort($found);

        return $found;
    }

    // What a step not ticked yet spends of a credit, none for a ticked step or a cost missing its credit or amount, as an import may write it
    /**
     * @param array<string, mixed> $step
     *
     * @return ?array{credit: string, amount: int}
     */
    public static function stepCost(array $step): ?array
    {
        $credit = $step['cost']['credit'] ?? null;
        $amount = $step['cost']['amount'] ?? null;

        return \is_string($credit) && \is_int($amount) && !($step['done'] ?? false) ? ['credit' => $credit, 'amount' => $amount] : null;
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
     * @param list<array{t: string, done?: bool, doneAt?: string, doneBy?: string, cost?: array{credit: string, amount: int, guess?: bool}, action?: string, params?: array<string, string>, after?: list<string>, feedback?: bool, g?: string, img?: string|list<string>, r?: string, s?: bool}> $steps
     * @param list<?int>                                                                                                                                                                                                                                                                          $from
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

    // Parameters of the action a step names, replaced as a whole, each value cut to what a runner takes, none kept on a step naming no action
    /** @param array<string, string> $params */
    public function setStepParams(int $index, array $params): static
    {
        if (!isset($this->checklist[$index]['action'])) {
            return $this;
        }
        $step = $this->checklist[$index];
        unset($step['params']);
        if ([] !== $params) {
            $step['params'] = array_map(static fn (string $value): string => mb_substr($value, 0, self::FEEDBACK_PASS_LENGTH), $params);
        }
        $this->checklist[$index] = $step;

        return $this;
    }

    // Ticks a step of the checklist at a moment, kept to the minute, with who ticked it, or unticks it, a done card opening again; an index out of the list changes nothing
    public function toggleStep(int $index, \DateTimeImmutable $day, ?string $by = null): static
    {
        if (!isset($this->checklist[$index])) {
            return $this;
        }
        $step = $this->checklist[$index];
        if ($step['done'] ?? false) {
            unset($step['done'], $step['doneAt'], $step['doneBy']);
        } else {
            $step['done'] = true;
            $step['doneAt'] = $day->format('Y-m-d H:i');
            if (null !== $by && '' !== $by) {
                $step['doneBy'] = $by;
            }
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

    // Another card merged into this one: its note under this one's, its steps after these, its feedback after this one's with their screenshots, it left without any so its removal deletes none; a done card opens again - refused past the checklist's ceiling
    public function absorb(self $other): static
    {
        if ($other === $this) {
            return $this;
        }
        self::expectRoom(\count($this->checklist) + \count($other->checklist));
        $note = implode("\n\n", array_filter([trim((string) $this->note), trim((string) $other->getNote())], static fn (string $text): bool => '' !== $text));
        $this->note = '' === $note ? null : $note;
        $this->checklist = [...array_values($this->getSteps()), ...array_values($other->getSteps()), ...array_values($this->getFeedback()), ...array_values($other->getFeedback())];
        $other->setChecklist([]);

        return $this->reopen();
    }

    // A new card copied from this one in the same project and collection, following the same template: title marked as a copy, note, date, reference, source, what does it and its steps all unticked; neither its plan, its links, its runs nor its wait
    public function duplicate(string $suffix): self
    {
        $steps = array_map(static function (array $step): array {
            // A screenshot belongs to the card it was sent on, the copy never sharing its file, nor who ticked a step or why a machine left a feedback open
            unset($step['done'], $step['doneAt'], $step['doneBy'], $step['img'], $step['r'], $step['s']);

            return $step;
        }, $this->checklist);

        return new self($this->project, mb_substr($this->title . $suffix, 0, 500))
            ->setNote($this->note)
            ->setDue($this->due)
            ->setDoc($this->doc)
            ->setSourceUrl($this->sourceUrl)
            ->setCardSet($this->cardSet)
            ->follow($this->template)
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

    // Steps whose files are out of date, an earlier step's run having written its own files after theirs, by index
    /** @return list<int> */
    public function staleSteps(): array
    {
        $runs = $this->stepRuns();
        ksort($runs);
        $stale = [];
        $latest = null;
        foreach ($runs as $index => $run) {
            $at = RunStatus::Done === $run->getStatus() && [] !== $run->getFiles() ? $run->getFinishedAt() : null;
            if (null === $at) {
                continue;
            }
            if (null !== $latest && $at < $latest) {
                $stale[] = $index;
            }
            $latest = null === $latest || $at > $latest ? $at : $latest;
        }

        return $stale;
    }

    // Latest run of each checklist step in one pass over the runs
    /** @return array<int, Run> */
    public function stepRuns(): array
    {
        $runs = [];
        foreach ($this->runs as $run) {
            if (isset($this->checklist[$run->getStep() ?? Run::LEFT_STEP])) {
                $runs[(int) $run->getStep()] = $run;
            }
        }

        return $runs;
    }
}
