<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Mcp;

use c975L\PilotBundle\Entity\Doc;
use c975L\PilotBundle\Repository\DocRepository;
use c975L\PilotBundle\Repository\ProjectRepository;
use c975L\PilotBundle\Security\PilotAccess;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Server\Exception\ToolCallException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

// The files of the piloting as MCP tools, written on two floors (a short summary for a human, the detail for an agent), with the rights of the agent token's account
class PilotDocTools
{
    use AccountTools;

    // Files a list gives at most
    public const int LIMIT = 50;

    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly DocRepository $docRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly PilotAccess $pilotAccess,
    ) {
    }

    // Files whose title or text holds the words, else those of a space, else every file served
    /**
     * @param ?string $space  slug of a space, to list its files
     * @param ?string $search words looked for in the titles and texts
     *
     * @return list<array{slug: string, title: string, space: ?string, updated: string}>
     */
    public function docs(?string $space = null, ?string $search = null): array
    {
        $docs = null === $this->text($space) || null !== $this->text($search)
            ? $this->docRepository->search((string) $this->text($search), $this->scope(), self::LIMIT)
            : $this->docRepository->findFiles($this->space((string) $space));

        return array_map(static fn (Doc $doc): array => [
            'slug' => $doc->getSlug(),
            'title' => $doc->getTitle(),
            'space' => $doc->getProject()?->getSlug(),
            'updated' => $doc->getUpdatedAt()->format('Y-m-d'),
        ], \array_slice($docs, 0, self::LIMIT));
    }

    // A file as markdown: its title, its summary under its heading, then its detail
    /**
     * @param string $doc slug of the file
     *
     * @return array{url: string, markdown: string}
     */
    public function doc(string $doc): array
    {
        $found = $this->served($doc);

        return ['url' => $this->url($found), 'markdown' => $found->toMarkdown()];
    }

    // A file written: a new one in a space when no slug is given, else the one named, only the floors given changing
    /**
     * @param ?string $space   slug of the space of a new file
     * @param ?string $title   title of the file, needed for a new one
     * @param ?string $summary short summary for a human, markdown
     * @param ?string $detail  detail for an agent, markdown
     * @param ?string $doc     slug of the file to rewrite, none to add one
     *
     * @return array{slug: string, url: string}
     */
    public function writeDoc(?string $space = null, ?string $title = null, ?string $summary = null, ?string $detail = null, ?string $doc = null): array
    {
        $found = null === $this->text($doc) ? $this->newDoc((string) $space, $title) : $this->served((string) $doc, PilotAccess::EDIT);
        if (null !== $this->text($title)) {
            $found->setTitle(mb_substr((string) $this->text($title), 0, 200));
        }
        $found->write(null === $summary ? $found->getSummary() : $this->text($summary), null === $detail ? $found->getDetail() : $this->text($detail));
        $this->entityManager->flush();

        return ['slug' => $found->getSlug(), 'url' => $this->url($found)];
    }

    // The repository the spaces are found in
    private function projects(): ProjectRepository
    {
        return $this->projectRepository;
    }

    // The account's rights on the piloting
    private function access(): PilotAccess
    {
        return $this->pilotAccess;
    }

    // A new file of a space served, refused without a title
    private function newDoc(string $space, ?string $title): Doc
    {
        $project = $this->space($space, PilotAccess::EDIT);
        if (null === $this->text($title)) {
            throw new ToolCallException('A new file needs a title.');
        }

        return $this->docRepository->newFile($project, (string) $this->text($title));
    }

    // A file by its slug at the level asked, as its page guards it: one of a space by the account's role there, one of no space by the admin only; one it may not see refused as unknown
    private function served(string $slug, string $level = PilotAccess::VIEW): Doc
    {
        $doc = $this->docRepository->findOneBy(['slug' => trim($slug)]);
        $project = $doc?->getProject();
        if (!$doc instanceof Doc || !(null === $project ? $this->pilotAccess->isAdmin() : $this->pilotAccess->can($project, PilotAccess::VIEW))) {
            throw new ToolCallException(sprintf('No file "%s".', $slug));
        }
        if (null !== $project && !$this->pilotAccess->can($project, $level)) {
            throw new ToolCallException(sprintf('This account may not change the file "%s".', $slug));
        }

        return $doc;
    }

    // A file's page as an absolute address, for the agent to hand it on
    private function url(Doc $doc): string
    {
        return $this->urlGenerator->generate('pilot_doc', ['slug' => $doc->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
