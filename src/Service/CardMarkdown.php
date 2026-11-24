<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Service;

use c975L\PilotBundle\Controller\CardImageController;
use c975L\PilotBundle\Entity\Card;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

// A card as one markdown text, read by an agent or downloaded: title, date and awaited answer, note, steps, feedback, then its reference in full
class CardMarkdown
{
    public const string STEPS_HEADING = '## Steps';
    public const string FEEDBACK_HEADING = '## Feedback';

    public const string REFERENCE_HEADING = '## Reference';

    private const string DUE = 'Due';

    private const string WAITING_ON = 'Waiting on';

    private const string SOURCE = 'Source';

    private const string RESULT = 'Result';

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    // The card, project and status in its header for reading only, its source and result links when it has them
    public function write(Card $card): string
    {
        $lines = ['# ' . $card->getTitle(), '', '- Project: ' . $card->getProject()->getName(), '- Status: ' . $card->getStatus()->value, '- ' . self::DUE . ': ' . $card->getDue()?->format('Y-m-d'), '- ' . self::WAITING_ON . ': ' . $card->getWaitingOn()];
        foreach ([self::SOURCE => $card->getSourceUrl(), self::RESULT => $card->getResultUrl()] as $name => $url) {
            if (null !== $url) {
                $lines[] = '- ' . $name . ': ' . $url;
            }
        }
        if (null !== $card->getNote()) {
            array_push($lines, '', $card->getNote());
        }

        return implode("\n", [...$lines, ...$this->steps($card), ...$this->reference($card)]) . "\n";
    }

    // The steps as task list items under the « ### » heading of their group, then the feedback, its screenshots as images on the indented lines under it
    /** @return list<string> */
    private function steps(Card $card): array
    {
        $lines = [];
        foreach ([self::STEPS_HEADING => $card->getSteps(), self::FEEDBACK_HEADING => $card->getFeedback()] as $heading => $steps) {
            if ([] === $steps) {
                continue;
            }
            array_push($lines, '', $heading, '');
            $group = null;
            foreach ($steps as $step) {
                if (($step['g'] ?? null) !== $group) {
                    $group = $step['g'] ?? null;
                    array_push($lines, '', '### ' . ($group ?? '-'), '');
                }
                $lines[] = '- [' . (($step['done'] ?? false) ? 'x' : ' ') . '] ' . $step['t'] . (isset($step['action']) ? ' `' . $step['action'] . '`' : '');
                foreach ((array) ($step['img'] ?? []) as $name) {
                    $lines[] = '  ![screenshot](' . $this->imageUrl($name) . ')';
                }
            }
        }

        return $lines;
    }

    // Absolute address of a feedback's screenshot, readable by whoever holds it, an AI fetching it included
    private function imageUrl(string $name): string
    {
        return $this->urlGenerator->generate(CardImageController::FEEDBACK_ROUTE, ['name' => $name], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    // The reference the card points to, whole as its own markdown file writes it: the summary for a human, the detail for whoever works on it
    /** @return list<string> */
    private function reference(Card $card): array
    {
        $doc = $card->getDoc();

        return null === $doc ? [] : ['', self::REFERENCE_HEADING, '', rtrim($doc->toMarkdown())];
    }
}
