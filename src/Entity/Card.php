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

    // Sub-steps ticked one by one: text, done and the day it was
    /** @var list<array{t: string, done?: bool, doneAt?: string}> */
    #[ORM\Column(type: Types::JSON)]
    private array $checklist = [];

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Doc $doc = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $doneAt = null;

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

    public function setTitle(string $title): static
    {
        $this->title = $title;

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
     * @return list<array{t: string, done?: bool, doneAt?: string}>
     */
    public function getChecklist(): array
    {
        return $this->checklist;
    }

    /**
     * @param list<array{t: string, done?: bool, doneAt?: string}> $checklist
     */
    public function setChecklist(array $checklist): static
    {
        $this->checklist = $checklist;

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
}
