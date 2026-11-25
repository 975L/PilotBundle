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
use c975L\PilotBundle\Entity\CardSet;
use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Repository\CardRepository;

// Cards added to a space from typed or pasted lines, one per line, each laid with the steps of the space's checklist template, its {variables} filled with the card's title
class CardFiller
{
    // Lines taken from one paste, the rest left aside
    public const int MAX_LINES = 500;

    public function __construct(
        private readonly CardRepository $cardRepository,
        private readonly PlanFactory $planFactory,
    ) {
    }

    // The new cards, not persisted: empty lines and titles the space already holds left aside, so the same list pasted again adds only what is new
    /** @return list<Card> */
    public function fill(Project $project, string $lines, ?CardSet $cardSet = null): array
    {
        $known = array_flip($this->cardRepository->titlesOf($project));
        $cards = [];
        foreach (\array_slice(preg_split('/\R/u', $lines) ?: [], 0, self::MAX_LINES) as $line) {
            $title = mb_substr(trim($line), 0, 500);
            if ('' === $title || isset($known[$title])) {
                continue;
            }
            $known[$title] = true;
            $cards[] = $this->card($project, $title, $cardSet);
        }

        return $cards;
    }

    // One card of the space, in the given collection of it, laid with the collection's template when it has one
    public function card(Project $project, string $title, ?CardSet $cardSet = null): Card
    {
        $cardSet = $cardSet?->getProject() === $project ? $cardSet : null;
        $card = new Card($project, mb_substr($title, 0, 500))->setCardSet($cardSet);
        $template = $cardSet?->getTemplate();
        if (null === $template) {
            return $card;
        }

        return $card->follow($template)->setChecklist($this->planFactory->steps($template, array_fill_keys($this->planFactory->variables($template), $card->getTitle())));
    }
}
