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
    // Heading of the summary section, the one the Pilotage artifact writes and the import splits on
    public const string SUMMARY_HEADING = "## L'essentiel";

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

    // The whole document as one markdown file: title, the summary under its heading, then the detail
    public function toMarkdown(): string
    {
        $parts = ['# ' . $this->title];
        if (null !== $this->summary && '' !== trim($this->summary)) {
            $parts[] = self::SUMMARY_HEADING . "\n\n" . trim($this->summary);
        }
        if (null !== $this->detail && '' !== trim($this->detail)) {
            $parts[] = trim($this->detail);
        }

        return implode("\n\n", $parts) . "\n";
    }

    // Reads back a markdown file shaped as toMarkdown() writes it: the title, the summary under its heading, the rest as the detail
    public function fromMarkdown(string $markdown): static
    {
        $text = trim(str_replace("\r\n", "\n", $markdown));
        if (1 === preg_match('/^# (.+)$/m', $text, $title, PREG_OFFSET_CAPTURE) && 0 === $title[0][1]) {
            $this->title = mb_substr(trim($title[1][0]), 0, 200);
            $text = trim(substr($text, \strlen($title[0][0])));
        }
        $summary = null;
        if (str_starts_with($text, self::SUMMARY_HEADING)) {
            $rest = trim(substr($text, \strlen(self::SUMMARY_HEADING)));
            $parts = preg_split('/^(?=#{1,2} )/m', $rest, 2) ?: [$rest];
            $summary = trim($parts[0]);
            $text = trim($parts[1] ?? '');
        }

        return $this->write('' === $summary ? null : $summary, '' === $text ? null : $text);
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
