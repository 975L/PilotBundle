<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Entity;

use c975L\PilotBundle\Repository\CreditRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

// What is left on a paid service account: reported by a machine, or typed in by hand for a service without an API
#[ORM\Entity(repositoryClass: CreditRepository::class)]
#[ORM\Table(name: 'pilot_credit')]
class Credit implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $label = '';

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $remaining = null;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $total = null;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $monthly = null;

    #[ORM\Column(length: 30, nullable: true)]
    #[Assert\Length(max: 30)]
    private ?string $unit = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $renewsOn = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $note = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $manual = true;

    #[ORM\Column(options: ['default' => 0])]
    private int $position = 0;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct(
        #[ORM\Column(name: 'credit_key', length: 50, unique: true)]
        #[Assert\NotBlank]
        #[Assert\Regex('/^[a-z0-9-]+$/')]
        #[Assert\Length(max: 50)]
        private string $key,
    ) {
    }

    public function __toString(): string
    {
        return $this->label;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function setKey(?string $key): static
    {
        $this->key = $key ?? '';

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(?string $label): static
    {
        $this->label = $label ?? '';

        return $this;
    }

    public function getRemaining(): ?int
    {
        return $this->remaining;
    }

    // A balance typed in or reported, dated
    public function setRemaining(?int $remaining, ?\DateTimeImmutable $now = null): static
    {
        $this->remaining = $remaining;
        $this->updatedAt = $now ?? new \DateTimeImmutable();

        return $this;
    }

    // Amount the planned steps may spend in a month, compared on the Planning with what they plan to
    public function getMonthly(): ?int
    {
        return $this->monthly;
    }

    public function setMonthly(?int $monthly): static
    {
        $this->monthly = $monthly;

        return $this;
    }

    public function getTotal(): ?int
    {
        return $this->total;
    }

    public function setTotal(?int $total): static
    {
        $this->total = $total;

        return $this;
    }

    public function getUnit(): ?string
    {
        return $this->unit;
    }

    public function setUnit(?string $unit): static
    {
        $this->unit = $unit;

        return $this;
    }

    public function getRenewsOn(): ?\DateTimeImmutable
    {
        return $this->renewsOn;
    }

    public function setRenewsOn(?\DateTimeImmutable $renewsOn): static
    {
        $this->renewsOn = $renewsOn;

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

    public function isManual(): bool
    {
        return $this->manual;
    }

    public function setManual(bool $manual): static
    {
        $this->manual = $manual;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(?int $position): static
    {
        $this->position = $position ?? 0;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    // Whether nothing is left
    public function isEmpty(): bool
    {
        return 0 === $this->remaining;
    }
}
