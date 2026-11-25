<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Service;

use c975L\PilotBundle\Entity\Action;
use c975L\PilotBundle\Entity\Card;
use c975L\PilotBundle\Entity\Plan;
use c975L\PilotBundle\Entity\Run;
use c975L\PilotBundle\Enum\CardStatus;
use c975L\PilotBundle\Enum\RunStatus;
use c975L\PilotBundle\Exception\FlowException;
use c975L\PilotBundle\Repository\ActionRepository;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\SecurityBundle\Security;

use function Symfony\Component\Translation\t;

// Moves the cards: a machine action leaves only from the step naming it, the steps' actions queue one after the other, each done one ticking its step, the open feedback handed back to the last run's action
class CardFlow
{
    public function __construct(
        private readonly ClockInterface $clock,
        private readonly ?Security $security = null,
        private readonly ?ActionRepository $actionRepository = null,
    ) {
    }

    // Opens the first card of a new plan
    public function start(Plan $plan): void
    {
        $first = $plan->getCards()->first();
        if (false !== $first) {
            $first->setStatus(CardStatus::Ready);
        }
    }

    // Closes a card by hand
    public function complete(Card $card): Card
    {
        if (CardStatus::Running === $card->getStatus()) {
            throw new FlowException(t('flow.not_now', [], 'pilot'));
        }

        return $this->close($card);
    }

    // Hands the card to a machine through a new step naming the action and the values of its parameters, its text the action's label, queued at once
    /** @param array<string, string> $params */
    public function entrust(Card $card, string $action, array $params, string $label): Run
    {
        if (\in_array($card->getStatus(), [CardStatus::Running, CardStatus::Done], true)) {
            throw new FlowException(t('flow.not_now', [], 'pilot'));
        }
        $card->addStep('' !== trim($label) ? $label : $action);
        $index = (int) array_key_last($card->getSteps());
        $card->setStepAction($index, $action)->setStepParams($index, $params);

        return $this->runStep($card, $index);
    }

    // Hands the open feedback to the machine as one numbered comment: the action of the last run is queued again on its step, the machine doing only what the feedback asks
    public function treatFeedback(Card $card): Run
    {
        $comment = $this->feedbackComment($card);
        $last = $card->lastRun();
        $step = $last?->getStep();
        if (null === $last || null === $step || !isset($card->getChecklist()[$step]) || $last->getStatus()->isActive() || \in_array($card->getStatus(), [CardStatus::Running, CardStatus::Done], true)) {
            throw new FlowException(t('flow.not_now', [], 'pilot'));
        }
        $params = $last->getParams();
        unset($params['comment']);

        return $this->queue($card, (string) $last->getAction(), ['comment' => $comment] + $params, $step);
    }

    // The feedback still open, numbered one per line, whole lines within what a runner takes as a parameter; none open refused
    private function feedbackComment(Card $card): string
    {
        $lines = $card->feedbackToHand();
        if ([] === $lines) {
            throw new FlowException(t('flow.feedback_none', [], 'pilot'));
        }

        return implode("\n", $lines);
    }

    // Settles the feedback a finished run was handed: each line of its comment still open on the card is ticked when the run is done, or keeps the reason the machine gave for leaving it; matched by its text, the lines having possibly moved meanwhile
    /** @param array<int, string> $left */
    public function feedbackSettled(Run $run, array $left): void
    {
        $handed = [];
        foreach (explode("\n", $run->getParams()['comment'] ?? '') as $line) {
            if (1 === preg_match('/^(\d+)\. (.+)$/', $line, $match)) {
                $handed[$match[2]] = (int) $match[1];
            }
        }
        $card = $run->getCard();
        foreach ($card->getFeedback() as $index => $feedback) {
            $number = $handed[str_replace(["\r", "\n"], ' ', $feedback['t'])] ?? null;
            if (null === $number || ($feedback['done'] ?? false)) {
                continue;
            }
            if (isset($left[$number])) {
                $card->setFeedbackReason($index, $left[$number]);
            } elseif (RunStatus::Done === $run->getStatus()) {
                $card->setFeedbackReason($index, null)->toggleStep($index, $this->clock->now(), $run->getMachine()?->getName());
            }
        }
    }

    // Ticks or unticks a step, a machine action only ever leaving by its own button
    public function tick(Card $card, int $index): Card
    {
        return $card->toggleStep($index, $this->clock->now(), self::initials($this->security?->getUser()?->getUserIdentifier()));
    }

    // Initials of whoever ticks, from the name part of their identifier: the first letter of its first two words, else its first two letters
    public static function initials(?string $identifier): ?string
    {
        $words = self::nameWords($identifier);
        if ([] === $words) {
            return null;
        }

        return mb_strtoupper(isset($words[1]) ? mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1) : mb_substr($words[0], 0, 2));
    }

    // Name of whoever asks, from the name part of their identifier, each word capitalised ("item.one@example.com" gives "Item One")
    public static function displayName(?string $identifier): ?string
    {
        $words = self::nameWords($identifier);

        return [] !== $words ? implode(' ', array_map(static fn (string $word): string => mb_convert_case($word, \MB_CASE_TITLE), $words)) : null;
    }

    // Words of the name part of an identifier, the domain of an email left out
    /** @return list<string> */
    private static function nameWords(?string $identifier): array
    {
        $name = explode('@', (string) $identifier)[0];

        return preg_split('/[^\p{L}\p{N}]+/u', $name, -1, \PREG_SPLIT_NO_EMPTY) ?: [];
    }

    // Queues the action a step names, after the ones already queued on the card, when that step is still to do and not already under way
    public function runStep(Card $card, int $index): Run
    {
        if (!$this->stepQueueable($card, $index)) {
            throw new FlowException(t('flow.not_now', [], 'pilot'));
        }
        $step = $card->getChecklist()[$index];

        return $this->queue($card, $step['action'] ?? '', $step['params'] ?? [], $index);
    }

    // Withdraws a run of the card, the given one or else the last: one still queued never leaves, one a machine holds is stopped at its next sign of life (refused, the machine stops the command); the card back to do once nothing else waits
    public function cancel(Card $card, ?int $runId = null): Run
    {
        $run = null === $runId ? $card->lastRun() : $card->getRuns()->findFirst(static fn (int $key, Run $run): bool => $runId === $run->getId());
        if (CardStatus::Running !== $card->getStatus() || !($run?->getStatus()->isActive() ?? false)) {
            throw new FlowException(t('flow.not_now', [], 'pilot'));
        }
        $run->cancel($this->clock->now());
        if ([] === $card->activeRuns()) {
            $card->setStatus(CardStatus::Ready);
        }

        return $run;
    }

    // Applies the result of a finished run to its card, unless the card was closed meanwhile: a step's run done ticks the step, the next one left to a hand so nothing runs on unchecked; any failure stops what is still queued
    public function runFinished(Run $run): void
    {
        $card = $run->getCard();
        if (CardStatus::Running !== $card->getStatus()) {
            return;
        }
        $done = RunStatus::Done === $run->getStatus();
        if (!$done) {
            foreach ($card->activeRuns() as $queued) {
                if (RunStatus::Queued === $queued->getStatus()) {
                    $queued->cancel($this->clock->now());
                }
            }
        }
        $step = $run->getStep();
        $named = null !== $step ? $card->getChecklist()[$step] ?? [] : [];
        if ($done && ($named['action'] ?? null) === $run->getAction() && !($named['done'] ?? false)) {
            $card->toggleStep($step, $this->clock->now(), $run->getMachine()?->getName());
        }
        $card->setStatus(match (true) {
            [] !== $card->activeRuns() => CardStatus::Running,
            $done => CardStatus::Ready,
            default => CardStatus::Failed,
        });
    }

    // Whether a step names an action, is still to do, no longer waits for the steps it needs, is not already under way, and the card is open with no output waiting for validation
    private function stepQueueable(Card $card, int $index): bool
    {
        $step = $card->getChecklist()[$index] ?? null;

        return null !== $step && !($step['done'] ?? false) && isset($step['action']) && !$card->isStepWaiting($index)
            && CardStatus::Done !== $card->getStatus()
            && !($card->stepRun($index)?->getStatus()->isActive() ?? false);
    }

    // A run of a step's action queued on the card, which runs while anything waits
    /** @param array<string, string> $params */
    private function queue(Card $card, string $action, array $params, int $step): Run
    {
        $this->expectOffered($card, $action);
        $card->setStatus(CardStatus::Running);

        return new Run($card, $action, $params, $step)->setRequester($this->requester());
    }

    // Name of whoever signed in asks for a run, null when nobody did
    private function requester(): ?string
    {
        return self::displayName($this->security?->getUser()?->getUserIdentifier());
    }

    // Refuses an action the card's space is not offered, whoever names it (a step written by hand, a link, an agent, a report), so no machine runs what the admin kept for other spaces
    private function expectOffered(Card $card, string $action): void
    {
        if (null === $this->actionRepository) {
            return;
        }
        $offered = $this->actionRepository->findOneBy(['key' => $action]);
        if (!$offered instanceof Action || !$offered->isOfferedTo($card->getProject())) {
            throw new FlowException(t('flow.not_now', [], 'pilot'));
        }
    }

    // Marks a card done on the day
    private function close(Card $card): Card
    {
        return $card->markDone($this->clock->now()->setTime(0, 0));
    }
}
