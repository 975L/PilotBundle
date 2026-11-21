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
use c975L\PilotBundle\Exception\FlowException;
use Doctrine\ORM\EntityManagerInterface;

use function Symfony\Component\Translation\t;

// A card deleted for good with its picture, its screenshots, its runs and the files they kept, refused while a machine works on it
class CardRemoval
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CardImages $cardImages,
        private readonly RunOutputs $runOutputs,
    ) {
    }

    // The card removed and flushed, its files deleted only once the removal is written, so a failed flush loses nothing
    public function remove(Card $card): void
    {
        if ([] !== $card->activeRuns()) {
            throw new FlowException(t('flow.not_now', [], 'pilot'));
        }

        // Read before the flush, which sets the removed runs' ids back to null
        $folders = array_map($this->runOutputs->folder(...), $card->getRuns()->toArray());
        $image = $this->cardImages->path($card);
        $screenshots = $card->getFeedbackImages();
        $this->entityManager->remove($card);
        $this->entityManager->flush();
        $this->cardImages->forget($screenshots);
        foreach ($folders as $folder) {
            $this->runOutputs->forget($folder);
        }
        if (null !== $image) {
            unlink($image);
        }
    }
}
