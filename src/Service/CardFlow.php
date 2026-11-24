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
use c975L\PilotBundle\Enum\CardKind;
use c975L\PilotBundle\Enum\CardStatus;
use c975L\PilotBundle\Enum\Executor;
use c975L\PilotBundle\Enum\RunStatus;
use c975L\PilotBundle\Exception\FlowException;
use c975L\PilotBundle\Repository\ActionRepository;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\SecurityBundle\Security;

use function Symfony\Component\Translation\t;

// Moves the cards: a card handed to a machine is queued, run, then validated or handed the open feedback; the steps' actions queue one after the other, each done one ticking its step and queuing the next; a card closed in a plan opens the next one, a machine card leaving on its own
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

    // Hands the card to a machine with an action of its library and the values of its parameters
    /** @param array<string, string> $params */
    public function entrust(Card $card, string $action, array $params): Run
    {
        if (\in_array($card->getStatus(), [CardStatus::Running, CardStatus::Done], true)) {
            throw new FlowException(t('flow.not_now', [], 'pilot'));
        }
        $card->assign(Executor::Runner, CardKind::Task, $action, $params)->setComment(null)->setStatus(CardStatus::Ready);

        return $this->launch($card);
    }

    // Queues a runner card that is to do or failed
    public function launch(Card $card): Run
    {
        if (!$card->isRunner() || null === $card->getAction() || !\in_array($card->getStatus(), [CardStatus::Ready, CardStatus::Failed], true)) {
            throw new FlowException(t('flow.not_now', [], 'pilot'));
        }
        $this->expectOffered($card, (string) $card->getAction());
        $card->setStatus(CardStatus::Running);

        return new Run($card)->setRequester($this->requester());
    }

    // Accepts what the machine produced: a run started by a checklist step ticks that step, any other closes the card
    public function validate(Card $card): Card
    {
        $this->expectReview($card);
        $card->setComment(null);
        $index = $this->stepOfAction($card);
        if (null === $index) {
            return $this->close($card);
        }

        return $this->tick($card->assign(Executor::Human)->setStatus(CardStatus::Ready), $index);
    }

    // Hands the open feedback to the machine as one numbered comment: the card's own run waiting for validation goes back to it, else the action of the last run is queued again on its step, the machine doing only what the feedback asks
    public function treatFeedback(Card $card): Run
    {
        $comment = $this->feedbackComment($card);
        if (CardStatus::Review === $card->getStatus()) {
            $card->setComment($comment)->setStatus(CardStatus::Ready);

            return $this->launch($card);
        }
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
        if (null === $step) {
            $card->setStatus($done ? CardStatus::Review : CardStatus::Failed);

            return;
        }
        $named = $card->getChecklist()[$step] ?? [];
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
            && !\in_array($card->getStatus(), [CardStatus::Review, CardStatus::Done], true)
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

    // Marks a card done on the day and opens the next one of its plan, a machine card waiting for its button like any action
    private function close(Card $card): Card
    {
        $card->markDone($this->clock->now()->setTime(0, 0));
        $card->getPlan()?->cardAfter($card)?->setStatus(CardStatus::Ready);

        return $card;
    }

    // Index of the first step still to do that names the card's action, null when the action came from elsewhere
    private function stepOfAction(Card $card): ?int
    {
        foreach ($card->getChecklist() as $index => $step) {
            if (!($step['done'] ?? false) && null !== $card->getAction() && ($step['action'] ?? null) === $card->getAction()) {
                return $index;
            }
        }

        return null;
    }

    // Refuses a validation gesture on a card with nothing to validate
    private function expectReview(Card $card): void
    {
        if (CardStatus::Review !== $card->getStatus()) {
            throw new FlowException(t('flow.not_now', [], 'pilot'));
        }
    }
}
