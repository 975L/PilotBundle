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
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

// Reusable model of a plan, its steps kept as a list of definitions
#[ORM\Entity(repositoryClass: PlanTemplateRepository::class)]
#[ORM\Table(name: 'pilot_plan_template')]
class PlanTemplate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Each step: title, executor (human|runner), kind (task|validate), action key and params for a runner step
    /** @param list<array{title: string, executor: string, kind?: string, action?: string, params?: array<string, string>}> $steps */
    public function __construct(
        #[ORM\Column(length: 100, unique: true)]
        #[Assert\Length(max: 100)]
        private string $name,
        #[ORM\Column(type: Types::JSON)]
        private array $steps,
    ) {
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

    // Step definitions
    /** @return list<array{title: string, executor: string, kind?: string, action?: string, params?: array<string, string>}> */
    public function getSteps(): array
    {
        return $this->steps;
    }

    // Replaces the step definitions
    /** @param list<array{title: string, executor: string, kind?: string, action?: string, params?: array<string, string>}> $steps */
    public function setSteps(array $steps): static
    {
        $this->steps = $steps;

        return $this;
    }
}
