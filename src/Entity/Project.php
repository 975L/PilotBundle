<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Entity;

use c975L\PilotBundle\Repository\ProjectRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

// A project, shown as a tile in its own colour, holding its backlog, its plans and its files
#[ORM\Entity(repositoryClass: ProjectRepository::class)]
#[ORM\Table(name: 'pilot_project')]
class Project implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 7, options: ['default' => '#475569'])]
    #[Assert\Length(max: 7)]
    private string $color = '#475569';

    // Domains of the sites the project lives on
    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $sites = [];

    #[ORM\Column(options: ['default' => 0])]
    private int $position = 0;

    public function __construct(
        #[ORM\Column(length: 50, unique: true)]
        #[Assert\Length(max: 50)]
        private string $slug,
        #[ORM\Column(length: 100)]
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

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getColor(): string
    {
        return $this->color;
    }

    public function setColor(string $color): static
    {
        $this->color = $color;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getSites(): array
    {
        return $this->sites;
    }

    /**
     * @param list<string> $sites
     */
    public function setSites(array $sites): static
    {
        $this->sites = $sites;

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
}
