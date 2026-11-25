<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Entity;

use c975L\PilotBundle\Repository\CardSetRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

// A collection of a space, called so on screen: the cards of one kind gathered on its page (the stories, the replies), with the word for one of them and the checklist template a card added to it gets
#[ORM\Entity(repositoryClass: CardSetRepository::class)]
#[ORM\Table(name: 'pilot_card_set')]
class CardSet implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // The word for one of its cards (« story », « reply »), the button and the paste naming it
    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Length(max: 50)]
    private ?string $item = null;

    // The checklist template a card added to it gets, its {variables} filled with the card's title
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?PlanTemplate $template = null;

    // Its place among the space's collections
    #[ORM\Column]
    private int $position = 0;

    // Its cards shown as sheets (a character, a place, an app): a picture, a title and a pitch, no steps nor state
    #[ORM\Column(options: ['default' => false])]
    private bool $sheets = false;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Project $project,
        #[ORM\Column(length: 100)]
        #[Assert\NotBlank]
        #[Assert\Length(max: 100)]
        private string $name,
    ) {
    }

    public function __toString(): string
    {
        return $this->name;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProject(): Project
    {
        return $this->project;
    }

    public function getName(): string
    {
        return $this->name;
    }

    // Renamed, an empty name keeping the one it had
    public function setName(string $name): static
    {
        $name = trim($name);
        if ('' !== $name) {
            $this->name = mb_substr($name, 0, 100);
        }

        return $this;
    }

    public function getItem(): ?string
    {
        return $this->item;
    }

    // The word for one card, an empty value dropping it
    public function setItem(?string $item): static
    {
        $item = null === $item ? '' : trim($item);
        $this->item = '' === $item ? null : mb_substr($item, 0, 50);

        return $this;
    }

    public function getTemplate(): ?PlanTemplate
    {
        return $this->template;
    }

    public function setTemplate(?PlanTemplate $template): static
    {
        $this->template = $template;

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

    public function isSheets(): bool
    {
        return $this->sheets;
    }

    public function setSheets(bool $sheets): static
    {
        $this->sheets = $sheets;

        return $this;
    }
}
