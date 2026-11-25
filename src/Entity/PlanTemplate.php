<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Entity;

use c975L\PilotBundle\Repository\PlanTemplateRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

// Reusable model of a plan, the steps of its single card kept as a list of definitions, offered on the projects chosen for it
#[ORM\Entity(repositoryClass: PlanTemplateRepository::class)]
#[ORM\Table(name: 'pilot_plan_template')]
class PlanTemplate implements \Stringable
{
    use OfferedToProjectsTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** @var Collection<int, Project> */
    #[ORM\ManyToMany(targetEntity: Project::class)]
    #[ORM\JoinTable(name: 'pilot_plan_template_project')]
    private Collection $projects;

    // Each step: title, the machine action it names and that action's params, the steps it waits for and its group
    /** @param list<array{title: string, action?: string, params?: array<string, string>, after?: list<int>, group?: string}> $steps */
    public function __construct(
        #[ORM\Column(length: 100, unique: true)]
        #[Assert\NotBlank]
        #[Assert\Length(max: 100)]
        private string $name,
        #[ORM\Column(type: Types::JSON)]
        private array $steps,
    ) {
        $this->projects = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->name;
    }

    // Template id
    public function getId(): ?int
    {
        return $this->id;
    }

    // Template name
    public function getName(): string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name ?? '';

        return $this;
    }

    // Step definitions
    /** @return list<array{title: string, action?: string, params?: array<string, string>, after?: list<int>, group?: string}> */
    public function getSteps(): array
    {
        return $this->steps;
    }

    // Replaces the step definitions
    /** @param list<array{title: string, action?: string, params?: array<string, string>, after?: list<int>, group?: string}> $steps */
    public function setSteps(array $steps): static
    {
        $this->steps = $steps;

        return $this;
    }
}
