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
use c975L\PilotBundle\Repository\DocRepository;

// A card as one markdown text, read and rewritten through its editing link: title, date and awaited answer, note, steps, then its reference in full
class CardMarkdown
{
    public const string STEPS_HEADING = '## Steps';

    public const string REFERENCE_HEADING = '## Reference';

    private const string DUE = 'Due';

    private const string WAITING_ON = 'Waiting on';

    public function __construct(
        private readonly DocRepository $docRepository,
    ) {
    }

    // The card, project and status in its header for reading only
    public function write(Card $card): string
    {
        $lines = ['# ' . $card->getTitle(), '', '- Project: ' . $card->getProject()->getName(), '- Status: ' . $card->getStatus()->value, '- ' . self::DUE . ': ' . $card->getDue()?->format('Y-m-d'), '- ' . self::WAITING_ON . ': ' . $card->getWaitingOn()];
        if (null !== $card->getNote()) {
            array_push($lines, '', $card->getNote());
        }

        return implode("\n", [...$lines, ...$this->steps($card), ...$this->reference($card)]) . "\n";
    }

    // A text shaped as write() gives it laid back on the card: a part left out keeps what the card had, a part written replaces it
    public function read(Card $card, string $markdown, \DateTimeImmutable $day): Card
    {
        $parts = preg_split('/^(?=' . self::STEPS_HEADING . '$|' . self::REFERENCE_HEADING . '$)/m', trim(str_replace("\r\n", "\n", $markdown))) ?: [];
        $head = trim((string) array_shift($parts));
        if ('' !== $head) {
            $this->readHead($card, $head);
        }
        foreach ($parts as $part) {
            if (str_starts_with($part, self::STEPS_HEADING)) {
                $this->readSteps($card, substr($part, \strlen(self::STEPS_HEADING)), $day);
            } else {
                $this->readReference($card, substr($part, \strlen(self::REFERENCE_HEADING)));
            }
        }

        return $card;
    }

    // The title on the first line, then the date and the awaited answer (a line left out keeping its value, an empty one clearing it), the rest from the first other line as the note, headings and fields included
    private function readHead(Card $card, string $head): void
    {
        $lines = explode("\n", $head);
        if (1 === preg_match('/^# (.+)$/', $lines[0], $title)) {
            $card->setTitle(trim($title[1]));
            array_shift($lines);
        }
        while ([] !== $lines && $this->readHeadLine($card, $lines[0])) {
            array_shift($lines);
        }
        $text = trim(implode("\n", $lines));
        $card->setNote('' !== $text ? $text : null);
    }

    // A field or a blank line of the header read, false for the first line of the note
    private function readHeadLine(Card $card, string $line): bool
    {
        if (1 === preg_match('/^- (' . self::DUE . '|' . self::WAITING_ON . '):\s*(.*)$/', $line, $field)) {
            $this->readField($card, $field[1], trim($field[2]));

            return true;
        }

        return '' === trim($line) || 1 === preg_match('/^- (Project|Status):/', $line);
    }

    // A date that is not one leaves the card's as it was
    private function readField(Card $card, string $name, string $value): void
    {
        if (self::WAITING_ON === $name) {
            $card->setWaitingOn($value);

            return;
        }
        $due = '' === $value ? null : \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (false !== $due) {
            $card->setDue($due);
        }
    }

    // The task list replacing the checklist, a step kept by its text (each one once, in order) keeping what it spends, its run and the action it names (shown after it, read back as its own)
    private function readSteps(Card $card, string $text, \DateTimeImmutable $day): void
    {
        $own = $card->getChecklist();
        $free = array_column($own, 't');
        $steps = [];
        $from = [];
        preg_match_all('/^- \[([ xX])\] (.+)$/m', $text, $items, \PREG_SET_ORDER);
        foreach ($items as [, $mark, $title]) {
            // The machine action write() shows after a step is its own, never part of the text
            $title = trim((string) preg_replace('/ `[\w-]+`$/', '', trim($title)));
            $index = StepOrder::take($free, $title);
            $from[] = $index;
            $step = null === $index ? ['t' => mb_substr($title, 0, 500)] : $own[$index];
            if (' ' === $mark) {
                unset($step['done'], $step['doneAt']);
            } elseif (!($step['done'] ?? false)) {
                $step['done'] = true;
                $step['doneAt'] = $day->format('Y-m-d');
            }
            $steps[] = $step;
        }
        $card->restep($steps, $from, $day);
    }

    // The reference rewritten, created in the card's project when it had none, an empty part leaving it as it was
    private function readReference(Card $card, string $text): void
    {
        if ('' === trim($text)) {
            return;
        }
        $doc = $card->getDoc() ?? $this->docRepository->newFile($card->getProject(), $card->getTitle());
        $card->setDoc($doc->fromMarkdown($text));
    }

    // The checklist as task list items
    /** @return list<string> */
    private function steps(Card $card): array
    {
        if ([] === $card->getChecklist()) {
            return [];
        }
        $lines = ['', self::STEPS_HEADING, ''];
        foreach ($card->getChecklist() as $step) {
            $lines[] = '- [' . (($step['done'] ?? false) ? 'x' : ' ') . '] ' . $step['t'] . (isset($step['action']) ? ' `' . $step['action'] . '`' : '');
        }

        return $lines;
    }

    // The reference the card points to, whole as its own markdown file writes it: the summary for a human, the detail for whoever works on it
    /** @return list<string> */
    private function reference(Card $card): array
    {
        $doc = $card->getDoc();

        return null === $doc ? [] : ['', self::REFERENCE_HEADING, '', rtrim($doc->toMarkdown())];
    }
}
