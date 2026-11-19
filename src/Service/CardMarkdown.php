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

// A card as one markdown text, read and rewritten through its editing link: title, date and awaited answer, note, steps, feedback, then its reference in full
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
        private readonly DocRepository $docRepository,
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

    // A text shaped as write() gives it laid back on the card: a part left out keeps what the card had, a part written replaces it
    public function read(Card $card, string $markdown, \DateTimeImmutable $day): Card
    {
        $parts = preg_split('/^(?=' . self::STEPS_HEADING . '$|' . self::FEEDBACK_HEADING . '$|' . self::REFERENCE_HEADING . '$)/m', trim(str_replace("\r\n", "\n", $markdown))) ?: [];
        $head = trim((string) array_shift($parts));
        if ('' !== $head) {
            $this->readHead($card, $head);
        }
        $lists = [];
        foreach ($parts as $part) {
            if (str_starts_with($part, self::REFERENCE_HEADING)) {
                $this->readReference($card, substr($part, \strlen(self::REFERENCE_HEADING)));
                continue;
            }
            $heading = str_starts_with($part, self::FEEDBACK_HEADING) ? self::FEEDBACK_HEADING : self::STEPS_HEADING;
            $lists[$heading] = substr($part, \strlen($heading));
        }
        if ([] !== $lists) {
            $this->readChecklist($card, $lists[self::STEPS_HEADING] ?? null, $lists[self::FEEDBACK_HEADING] ?? null, $day);
        }

        return $card;
    }

    // The title on the first line, then the date, the awaited answer and the links (a line left out keeping its value, an empty one clearing it), the rest from the first other line as the note, headings and fields included
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
        if (1 === preg_match('/^- (' . self::DUE . '|' . self::WAITING_ON . '|' . self::SOURCE . '|' . self::RESULT . '):\s*(.*)$/', $line, $field)) {
            $this->readField($card, $field[1], trim($field[2]));

            return true;
        }

        return '' === trim($line) || 1 === preg_match('/^- (Project|Status):/', $line);
    }

    // A date that is not one leaves the card's as it was; a link that is not one is dropped
    private function readField(Card $card, string $name, string $value): void
    {
        match ($name) {
            self::WAITING_ON => $card->setWaitingOn($value),
            self::SOURCE => $card->setSourceUrl($value),
            self::RESULT => $card->setResultUrl($value),
            default => $this->readDue($card, $value),
        };
    }

    // The date read, one that is not a date leaving the card's as it was
    private function readDue(Card $card, string $value): void
    {
        $due = '' === $value ? null : \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (false !== $due) {
            $card->setDue($due);
        }
    }

    // The task lists replacing the checklist, the steps then the feedback, a list left out keeping what the card had
    private function readChecklist(Card $card, ?string $steps, ?string $feedback, \DateTimeImmutable $day): void
    {
        $laid = [];
        $from = [];
        foreach ([[false, $steps, $card->getSteps()], [true, $feedback, $card->getFeedback()]] as [$isFeedback, $text, $own]) {
            [$items, $indices] = null === $text ? [array_values($own), array_keys($own)] : $this->readList($text, $own, $isFeedback, $day);
            $laid = [...$laid, ...$items];
            $from = [...$from, ...$indices];
        }
        $card->restep($laid, $from, $day);
    }

    // One task list read against the items of its kind the card has: an item kept by its text (each one once, in order) keeping what it spends, its run and the action it names (shown after it, read back as its own); a step in the group of the « ### » heading above it; a feedback starting with « ? » still to decide
    /**
     * @param array<int, array{t: string, done?: bool, doneAt?: string, feedback?: bool, ask?: bool}> $own
     *
     * @return array{list<array{t: string, done?: bool, doneAt?: string, feedback?: bool, ask?: bool}>, list<?int>}
     */
    private function readList(string $text, array $own, bool $feedback, \DateTimeImmutable $day): array
    {
        $free = array_map(static fn (array $step): string => $step['t'], $own);
        $steps = [];
        $from = [];
        $group = null;
        preg_match_all('/^(?:### (.+)|- \[([ xX])\] (.+))$/m', $text, $items, \PREG_SET_ORDER);
        foreach ($items as $item) {
            if ('' !== $item[1]) {
                // « ### - » closes a group, the steps under it in none
                $name = trim($item[1]);
                $group = $feedback || '-' === $name ? null : mb_substr($name, 0, 50);

                continue;
            }
            [, , $mark, $title] = $item;
            // The machine action write() shows after a step is its own, never part of the text
            $title = trim((string) preg_replace('/ `[\w-]+`$/', '', trim($title)));
            // So is the day the step is planned for, written « @2027-02-03 » after its text
            $due = !$feedback && 1 === preg_match('/ @(\d{4}-\d{2}-\d{2})$/', $title, $planned) ? $planned[1] : null;
            $title = null !== $due ? mb_substr($title, 0, -12) : $title;
            $ask = $feedback && str_starts_with($title, '?');
            $title = $ask ? ltrim(substr($title, 1)) : $title;
            $index = StepOrder::take($free, $title);
            $from[] = $index;
            $step = null === $index ? ['t' => mb_substr($title, 0, 500)] : $own[$index];
            $steps[] = $this->mark($feedback ? $step : self::planned(StepOrder::grouped($step, ['group' => $group ?? '']), $due), ' ' !== $mark, $feedback ? $ask : null, $day);
        }

        return [$steps, $from];
    }

    // A step planned for the day read, a valid one only, or for none
    /**
     * @param array{t: string, due?: string} $step
     *
     * @return array{t: string, due?: string}
     */
    private static function planned(array $step, ?string $due): array
    {
        unset($step['due']);
        $parsed = null !== $due ? \DateTimeImmutable::createFromFormat('!Y-m-d', $due) : false;
        if (false !== $parsed && $due === $parsed->format('Y-m-d')) {
            $step['due'] = $due;
        }

        return $step;
    }

    // An item ticked or not, dated the day it is ticked, and for a feedback still to decide or not
    /**
     * @param array{t: string, done?: bool, doneAt?: string, feedback?: bool, ask?: bool} $step
     *
     * @return array{t: string, done?: bool, doneAt?: string, feedback?: bool, ask?: bool}
     */
    private function mark(array $step, bool $done, ?bool $ask, \DateTimeImmutable $day): array
    {
        if (!$done) {
            unset($step['done'], $step['doneAt']);
        } elseif (!($step['done'] ?? false)) {
            $step['done'] = true;
            $step['doneAt'] = $day->format('Y-m-d');
        }
        if (null !== $ask) {
            $step['feedback'] = true;
            unset($step['ask']);
            if ($ask) {
                $step['ask'] = true;
            }
        }

        return $step;
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

    // The steps as task list items under the « ### » heading of their group, the day one is planned for after its text, then the feedback, one still to decide starting with « ? »
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
                $lines[] = '- [' . (($step['done'] ?? false) ? 'x' : ' ') . '] ' . (($step['ask'] ?? false) ? '? ' : '') . $step['t'] . (isset($step['due']) ? ' @' . $step['due'] : '') . (isset($step['action']) ? ' `' . $step['action'] . '`' : '');
            }
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
