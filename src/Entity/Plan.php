<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Entity;

use c975L\PilotBundle\Enum\CardStatus;
use c975L\PilotBundle\Repository\PlanRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

// Instance of a template, its cards copied at creation
#[ORM\Entity(repositoryClass: PlanRepository::class)]
#[ORM\Table(name: 'pilot_plan')]
class Plan
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, Card> */
    #[ORM\OneToMany(targetEntity: Card::class, mappedBy: 'plan', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => \SortDirection::Ascending])]
    private Collection $cards;

    // A plan built from a template, for a title
    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false)]
        private PlanTemplate $template,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Project $project,
        #[ORM\Column(length: 200)]
        #[Assert\Length(max: 200)]
        private string $title,
    ) {
        $this->createdAt = new \DateTimeImmutable();
        $this->cards = new ArrayCollection();
    }

    // Plan id
    public function getId(): ?int
    {
        return $this->id;
    }

    // Project the plan belongs to
    public function getProject(): Project
    {
        return $this->project;
    }

    // Template the plan comes from
    public function getTemplate(): PlanTemplate
    {
        return $this->template;
    }

    // Plan title
    public function getTitle(): string
    {
        return $this->title;
    }

    // Creation date
    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    // Cards in order
    /** @return Collection<int, Card> */
    public function getCards(): Collection
    {
        return $this->cards;
    }

    // Adds a card at the end
    public function addCard(Card $card): static
    {
        $this->cards->add($card);

        return $this;
    }

    // Card following the given one, null for the last
    public function cardAfter(Card $card): ?Card
    {
        foreach ($this->cards as $candidate) {
            if ($candidate->getPosition() === $card->getPosition() + 1) {
                return $candidate;
            }
        }

        return null;
    }

    // Closest runner card before the given one, the one a validation sends back to
    public function runnerCardBefore(Card $card): ?Card
    {
        $found = null;
        foreach ($this->cards as $candidate) {
            if ($candidate->getPosition() < $card->getPosition() && $candidate->isRunner()) {
                $found = $candidate;
            }
        }

        return $found;
    }

    // Number of finished cards
    public function doneCount(): int
    {
        return $this->cards->filter(static fn (Card $card): bool => CardStatus::Done === $card->getStatus())->count();
    }

    // Whether every card is done
    public function isDone(): bool
    {
        return $this->cards->count() === $this->doneCount();
    }
}
