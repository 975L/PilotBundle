<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Entity;

use c975L\PilotBundle\Repository\ActionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

// An action a machine declared it can run, offered on the cards of the projects chosen for it
#[ORM\Entity(repositoryClass: ActionRepository::class)]
#[ORM\Table(name: 'pilot_action')]
class Action implements \Stringable
{
    use OfferedToProjectsTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\Length(max: 100)]
    private string $label;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $params = [];

    // Run on a card's own content (its note, its file), so never offered as a button of the project's page
    #[ORM\Column]
    private bool $cardOnly = false;

    /** @var Collection<int, Project> */
    #[ORM\ManyToMany(targetEntity: Project::class)]
    #[ORM\JoinTable(name: 'pilot_action_project')]
    private Collection $projects;

    public function __construct(
        #[ORM\Column(name: 'action_key', length: 50, unique: true)]
        #[Assert\Length(max: 50)]
        private string $key,
    ) {
        $this->label = $key;
        $this->projects = new ArrayCollection();
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

    public function getLabel(): string
    {
        return $this->label;
    }

    /** @return list<string> */
    public function getParams(): array
    {
        return $this->params;
    }

    public function isCardOnly(): bool
    {
        return $this->cardOnly;
    }

    // What the machine says of the action at each call: its label, the parameters a card fills, whether it only makes sense on a card
    /** @param list<string> $params */
    public function declare(string $label, array $params, bool $cardOnly = false): static
    {
        $this->label = $label;
        $this->params = $params;
        $this->cardOnly = $cardOnly;

        return $this;
    }
}
