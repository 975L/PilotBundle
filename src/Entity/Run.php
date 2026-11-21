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

    // Most file paths a run keeps
    public const int MAX_FILES = 50;

    // Extensions of the machine's own data written beside its work (a list of posts, settings, a log), kept on the run but not shown under its step
    public const array HIDDEN_FILES = ['json', 'yaml', 'yml', 'xml', 'log', 'md'];

    // Links a run may leave under its step, a post to approve for instance
    public const int MAX_LINKS = 10;

    // Step of a run whose step left the checklist, never an index of it
    public const int LEFT_STEP = -1;

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

    // Paths of the files the action wrote where they live on the machine, only named, never sent
    /** @var list<string> */
    #[ORM\Column(type: Types::JSON, options: ['default' => '[]'])]
    private array $files = [];

    // Addresses the action points to, a draft to approve for instance, each with the words shown for it
    /** @var list<array{url: string, label: string}> */
    #[ORM\Column(type: Types::JSON, options: ['default' => '[]'])]
    private array $links = [];

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    // When a machine took it, the time it spent in the queue left out of how long its action lasts
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(length: 64, nullable: true)]
    #[Assert\Length(max: 64)]
    private ?string $action;

    /** @var array<string, string>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $params;

    // A queued run of a card, doing the given action of one of its steps or, without one, the card's own action with its comment
    /** @param array<string, string> $params */
    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'runs')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Card $card,
        ?string $action = null,
        array $params = [],
        #[ORM\Column(nullable: true)]
        private ?int $step = null,
    ) {
        $this->createdAt = new \DateTimeImmutable();
        $this->action = $action ?? $card->getAction();
        $this->params = null !== $action ? $params : null;
        $card->addRun($this);
    }

    // Action the machine runs, the card's for a run queued before runs held their own
    public function getAction(): ?string
    {
        return $this->action ?? $this->card->getAction();
    }

    // Values of the action's parameters, the card's with its comment for a run of the card's own action, which cannot change while it runs
    /** @return array<string, string> */
    public function getParams(): array
    {
        return $this->params ?? $this->card->getParams() + (null !== $this->card->getComment() ? ['comment' => $this->card->getComment()] : []);
    }

    // Index of the checklist step the run does, null for the card's own action
    public function getStep(): ?int
    {
        return $this->step;
    }

    // Follows its step moved elsewhere in the checklist, null when the step left it
    public function moveToStep(?int $step): static
    {
        $this->step = $step;

        return $this;
    }

    // Leaves the step that left the checklist, withdrawn when still waiting or held, its result kept when finished
    public function leaveStep(\DateTimeImmutable $now): static
    {
        if ($this->status->isActive()) {
            $this->cancel($now);
        }
        $this->step = self::LEFT_STEP;

        return $this;
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

    /** @return list<string> */
    public function getFiles(): array
    {
        return $this->files;
    }

    // Absolute paths only, each once, at most MAX_FILES, anything else left aside
    /** @param list<string> $files */
    public function setFiles(array $files): static
    {
        $this->files = \array_slice(array_values(array_unique(array_filter(array_map(trim(...), $files), static fn (string $path): bool => str_starts_with($path, '/') && mb_strlen($path) <= 500 && !str_contains($path, "\0")))), 0, self::MAX_FILES);

        return $this;
    }

    // Links left under the step
    /** @return list<array{url: string, label: string}> */
    public function getLinks(): array
    {
        return $this->links;
    }

    // Http(s) addresses only, each once, at most MAX_LINKS, a link without words named by its host
    /** @param list<array{url?: mixed, label?: mixed}> $links */
    public function setLinks(array $links): static
    {
        $kept = [];
        foreach ($links as $link) {
            $url = \is_string($link['url'] ?? null) ? trim($link['url']) : '';
            if (1 !== preg_match('~^https?://[^\s/]+\S*$~i', $url) || mb_strlen($url) > 500 || isset($kept[$url])) {
                continue;
            }
            $label = \is_string($link['label'] ?? null) ? mb_substr(trim($link['label']), 0, 100) : '';
            $kept[$url] = ['url' => $url, 'label' => '' !== $label ? $label : (string) parse_url($url, \PHP_URL_HOST)];
        }
        $this->links = \array_slice(array_values($kept), 0, self::MAX_LINKS);

        return $this;
    }

    // The files by folder, each folder named once with the names it holds, for the step that shows them, the machine's own data left out with a folder holding nothing else
    /** @return array<string, list<string>> */
    public function getFilesByFolder(): array
    {
        $folders = [];
        foreach ($this->files as $path) {
            if (!\in_array(strtolower(pathinfo($path, \PATHINFO_EXTENSION)), self::HIDDEN_FILES, true)) {
                $folders[\dirname($path)][] = basename($path);
            }
        }

        return $folders;
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
        $this->startedAt = $now;

        return $this;
    }

    // When a machine took it, its time in the queue for a run from before that was kept
    public function getStartedAt(): \DateTimeImmutable
    {
        return $this->startedAt ?? $this->createdAt;
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
        $this->message = null;

        return $this;
    }

    // Closes the run with its result
    /** @param list<string> $outputs */
    public function finish(RunStatus $status, ?string $message, array $outputs, \DateTimeImmutable $now): static
    {
        $this->status = $status;
        $this->message = null === $message ? null : mb_substr($message, 0, 255);
        $this->outputs = $outputs;
        $this->leaseUntil = null;
        $this->finishedAt = $now;
        if (RunStatus::Done === $status) {
            $this->progress = 100;
        }

        return $this;
    }

    // Withdraws a run, still waiting or stopped while a machine holds it
    public function cancel(\DateTimeImmutable $now): static
    {
        return $this->finish(RunStatus::Cancelled, null, [], $now);
    }
}
