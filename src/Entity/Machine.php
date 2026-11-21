<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Entity;

use c975L\PilotBundle\Repository\MachineRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

// A runner machine, known by the hash of its token, serving the spaces chosen for it, every space when none is
#[ORM\Entity(repositoryClass: MachineRepository::class)]
#[ORM\Table(name: 'pilot_machine')]
class Machine implements UserInterface
{
    use OfferedToProjectsTrait;

    // Seconds without a call after which a machine shows offline
    public const ONLINE_SECONDS = 45;

    // Most runs a machine may be given at once
    public const int MAX_PARALLEL = 8;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** @var array<string, array{label: string, params: list<string>, card?: bool, help?: ?string}> */
    #[ORM\Column(type: Types::JSON)]
    private array $capabilities = [];

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastSeenAt = null;

    // Runs it takes at once, set by hand to what the machine bears; an action its runner marks exclusive still runs alone
    #[ORM\Column(options: ['default' => 1])]
    #[Assert\Range(min: 1, max: self::MAX_PARALLEL)]
    private int $parallel = 1;

    // The spaces whose cards it takes, reports it fills and files it writes
    /** @var Collection<int, Project> */
    #[ORM\ManyToMany(targetEntity: Project::class)]
    #[ORM\JoinTable(name: 'pilot_machine_project')]
    private Collection $projects;

    // A machine with its name and the sha256 of its token
    public function __construct(
        #[ORM\Column(length: 100, unique: true)]
        #[Assert\NotBlank]
        #[Assert\Length(max: 100)]
        private string $name,
        #[ORM\Column(length: 64, unique: true)]
        #[Assert\Length(max: 64)]
        private string $tokenHash,
    ) {
        $this->projects = new ArrayCollection();
    }

    // Whether it works for a space: every one when no space was chosen for it
    public function serves(Project $project): bool
    {
        return $this->projects->isEmpty() || $this->projects->contains($project);
    }

    // Whether it can take the given action for the space now: online, serving the space and declaring the action
    public function canRun(string $action, Project $project, \DateTimeImmutable $now): bool
    {
        return $this->isOnline($now) && $this->serves($project) && isset($this->getCapabilities()[$action]);
    }

    // Ids of the spaces chosen for it, empty for every space
    /** @return list<int> */
    public function projectIds(): array
    {
        return array_values(array_map(static fn (Project $project): int => (int) $project->getId(), $this->projects->toArray()));
    }

    // Machine id
    public function getId(): ?int
    {
        return $this->id;
    }

    // Machine name
    public function getName(): string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name ?? '';

        return $this;
    }

    // Draws a new token, keeps its hash and returns it, the only time it is readable
    public function newToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->tokenHash = hash('sha256', $token);

        return $token;
    }

    // Hash of the token, the token itself is never stored
    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    // Actions the machine declared at its last call: key, label and parameter names
    /** @return array<string, array{label: string, params: list<string>, card?: bool, help?: ?string}> */
    public function getCapabilities(): array
    {
        return $this->capabilities;
    }

    public function getParallel(): int
    {
        return $this->parallel;
    }

    // Kept between one and the most a machine may take, an empty field meaning one
    public function setParallel(?int $parallel): static
    {
        $this->parallel = max(1, min(self::MAX_PARALLEL, $parallel ?? 1));

        return $this;
    }

    // Labels of the actions declared, for the list of machines
    public function getDeclaredActions(): string
    {
        return implode(', ', array_column($this->capabilities, 'label'));
    }

    // Records a call: capabilities and time
    /** @param array<string, array{label: string, params: list<string>, card?: bool, help?: ?string}> $capabilities */
    public function seen(array $capabilities, \DateTimeImmutable $now): static
    {
        $this->capabilities = $capabilities;
        $this->lastSeenAt = $now;

        return $this;
    }

    // Last call
    public function getLastSeenAt(): ?\DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    // Whether the machine called recently
    public function isOnline(\DateTimeImmutable $now): bool
    {
        return null !== $this->lastSeenAt && $now->getTimestamp() - $this->lastSeenAt->getTimestamp() <= self::ONLINE_SECONDS;
    }

    // Security identifier
    public function getUserIdentifier(): string
    {
        return $this->name;
    }

    // Single role of a machine
    public function getRoles(): array
    {
        return ['ROLE_MACHINE'];
    }
}
