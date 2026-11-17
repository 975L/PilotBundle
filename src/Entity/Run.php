<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Entity;

use c975L\PilotBundle\Enum\RunStatus;
use c975L\PilotBundle\Repository\RunRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

// One execution of a runner card, held by a machine under a renewable lease
#[ORM\Entity(repositoryClass: RunRepository::class)]
#[ORM\Table(name: 'pilot_run')]
#[ORM\Index(columns: ['status'])]
class Run
{
    // Seconds a claim lasts without news from the machine
    public const LEASE_SECONDS = 60;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(enumType: RunStatus::class)]
    private RunStatus $status = RunStatus::Queued;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Machine $machine = null;

    #[ORM\Column]
    private int $progress = 0;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $message = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $leaseUntil = null;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $outputs = [];

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $log = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    // A queued run of a card
    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'runs')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Card $card,
    ) {
        $this->createdAt = new \DateTimeImmutable();
        $card->addRun($this);
    }

    // Run id
    public function getId(): ?int
    {
        return $this->id;
    }

    // Card executed
    public function getCard(): Card
    {
        return $this->card;
    }

    // Current status
    public function getStatus(): RunStatus
    {
        return $this->status;
    }

    // Machine holding or having held the run
    public function getMachine(): ?Machine
    {
        return $this->machine;
    }

    // Progress in percent
    public function getProgress(): int
    {
        return $this->progress;
    }

    // Last message from the machine
    public function getMessage(): ?string
    {
        return $this->message;
    }

    // End of the current lease
    public function getLeaseUntil(): ?\DateTimeImmutable
    {
        return $this->leaseUntil;
    }

    // Files produced, absolute paths on the machine
    /** @return list<string> */
    public function getOutputs(): array
    {
        return $this->outputs;
    }

    // Tail of the command output
    public function getLog(): ?string
    {
        return $this->log;
    }

    // Queue date
    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    // End date
    public function getFinishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }

    // Whether the given machine holds the run right now
    public function isHeldBy(Machine $machine): bool
    {
        return RunStatus::Claimed === $this->status && $this->machine?->getId() === $machine->getId();
    }

    // Hands the run to a machine under a fresh lease
    public function claim(Machine $machine, \DateTimeImmutable $now): static
    {
        $this->status = RunStatus::Claimed;
        $this->machine = $machine;
        $this->progress = 0;
        $this->message = null;
        $this->leaseUntil = $now->modify('+' . self::LEASE_SECONDS . ' seconds');

        return $this;
    }

    // Records progress and renews the lease
    public function report(?int $progress, ?string $message, \DateTimeImmutable $now): static
    {
        if (null !== $progress) {
            $this->progress = max(0, min(100, $progress));
        }
        if (null !== $message) {
            $this->message = mb_substr($message, 0, 255);
        }
        $this->leaseUntil = $now->modify('+' . self::LEASE_SECONDS . ' seconds');

        return $this;
    }

    // Puts the run back in the queue after a lost lease
    public function release(): static
    {
        $this->status = RunStatus::Queued;
        $this->machine = null;
        $this->leaseUntil = null;
        $this->progress = 0;
        $this->message = 'Machine perdue, action remise en attente';

        return $this;
    }

    // Closes the run with its result
    /** @param list<string> $outputs */
    public function finish(RunStatus $status, ?string $message, array $outputs, ?string $log, \DateTimeImmutable $now): static
    {
        $this->status = $status;
        $this->message = null === $message ? null : mb_substr($message, 0, 255);
        $this->outputs = $outputs;
        $this->log = $log;
        $this->leaseUntil = null;
        $this->finishedAt = $now;
        if (RunStatus::Done === $status) {
            $this->progress = 100;
        }

        return $this;
    }
}
