<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Service;

use c975L\PilotBundle\Entity\Card;
use c975L\PilotBundle\Entity\Doc;
use c975L\PilotBundle\Repository\DocRepository;
use Doctrine\ORM\EntityManagerInterface;

// A file named <slug>.doc.md sent by a machine writes the file <slug>, created in the card's project when new, and attaches it to the card
class DocIngester
{
    public const string SUFFIX = '.doc.md';

    private const string SLUG = '/^[a-z0-9][a-z0-9-]{0,99}$/';

    public function __construct(
        private readonly DocRepository $docRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    // Reads a kept file of a run into a file of the card's space; false when it is not a file to write
    public function ingest(string $name, string $path, Card $card): bool
    {
        $slug = str_ends_with($name, self::SUFFIX) ? substr($name, 0, -\strlen(self::SUFFIX)) : '';
        $markdown = (string) file_get_contents($path);
        if (1 !== preg_match(self::SLUG, $slug) || '' === trim($markdown)) {
            return false;
        }
        $doc = $this->docRepository->findOneBy(['slug' => $slug]) ?? new Doc($slug, $card->getTitle())->setProject($card->getProject());
        // A file of another space is never written through this card
        if ($doc->getProject() !== $card->getProject()) {
            return true;
        }
        $card->setDoc($doc->fromMarkdown($markdown));
        $this->entityManager->persist($doc);
        $this->entityManager->flush();

        return true;
    }
}
