<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Entity;

use c975L\PilotBundle\Enum\DocKind;
use c975L\PilotBundle\Repository\DocRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

// A document on two floors: the summary a human reads in five lines, the detail kept folded for Claude
#[ORM\Entity(repositoryClass: DocRepository::class)]
#[ORM\Table(name: 'pilot_doc')]
class Doc implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Project $project = null;

    #[ORM\Column(enumType: DocKind::class)]
    private DocKind $kind = DocKind::File;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $summary = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $detail = null;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        #[ORM\Column(length: 100, unique: true)]
        #[Assert\Length(max: 100)]
        private string $slug,
        #[ORM\Column(length: 200)]
        #[Assert\Length(max: 200)]
        private string $title,
    ) {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return $this->title;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): static
    {
        $this->project = $project;

        return $this;
    }

    public function getKind(): DocKind
    {
        return $this->kind;
    }

    public function setKind(DocKind $kind): static
    {
        $this->kind = $kind;

        return $this;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function getDetail(): ?string
    {
        return $this->detail;
    }

    // Both floors at once, the date following
    public function write(?string $summary, ?string $detail): static
    {
        $this->summary = $summary;
        $this->detail = $detail;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
