<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\EventListener;

use c975L\PilotBundle\Entity\Card;
use c975L\PilotBundle\Entity\CardSet;
use c975L\PilotBundle\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Symfony\Contracts\Translation\TranslatorInterface;

// Every card lives in a collection of its space: one saved without any, or with one of another space (moved, added from the dashboard, laid by a plan), goes to the space's first collection, made when the space has none
class CardHomeListener
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $entityManager = $args->getObjectManager();
        $unitOfWork = $entityManager->getUnitOfWork();
        $homes = [];
        foreach ([...$unitOfWork->getScheduledEntityInsertions(), ...$unitOfWork->getScheduledEntityUpdates()] as $card) {
            if (!$card instanceof Card || $card->getCardSet()?->getProject() === $card->getProject()) {
                continue;
            }
            $project = $card->getProject();
            $homes[spl_object_id($project)] ??= $this->home($entityManager, $project);
            $card->setCardSet($homes[spl_object_id($project)]);
            $unitOfWork->recomputeSingleEntityChangeSet($entityManager->getClassMetadata(Card::class), $card);
        }
    }

    // The space's first collection still kept, among those saved (none for a space being added) and those being added, else a new one named after the cards
    private function home(EntityManagerInterface $entityManager, Project $project): CardSet
    {
        $unitOfWork = $entityManager->getUnitOfWork();
        $sets = array_filter([
            ...(null === $project->getId() ? [] : $entityManager->createQueryBuilder()->select('s')->from(CardSet::class, 's')->where('s.project = :project')->setParameter('project', $project)->orderBy('s.position')->addOrderBy('s.id')->getQuery()->getResult()),
            ...array_filter($unitOfWork->getScheduledEntityInsertions(), static fn (object $entity): bool => $entity instanceof CardSet && $entity->getProject() === $project),
        ], static fn (CardSet $set): bool => !$unitOfWork->isScheduledForDelete($set));
        $home = reset($sets);
        if (false !== $home) {
            return $home;
        }
        $home = new CardSet($project, $this->translator->trans('label.cards', [], 'pilot'));
        $entityManager->persist($home);
        $unitOfWork->computeChangeSet($entityManager->getClassMetadata(CardSet::class), $home);

        return $home;
    }
}
