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
use c975L\PilotBundle\Entity\Plan;
use c975L\PilotBundle\Entity\Run;
use c975L\PilotBundle\Enum\CardKind;
use c975L\PilotBundle\Enum\CardStatus;
use c975L\PilotBundle\Enum\Executor;
use c975L\PilotBundle\Enum\RunStatus;
use c975L\PilotBundle\Exception\FlowException;
use Psr\Clock\ClockInterface;

use function Symfony\Component\Translation\t;

// Moves the cards: a card handed to a machine is queued, run, then validated or sent back with a comment; the steps' actions queue one after the other, each done one ticking its step and queuing the next; a card closed in a plan opens the next one, a machine card leaving on its own
class CardFlow
{
    public function __construct(
        private readonly ClockInterface $clock,
    ) {
    }

    // Opens the first card of a new plan
    public function start(Plan $plan): ?Run
    {
        $first = $plan->getCards()->first();

        return false === $first ? null : $this->open($first);
    }

    // Closes a card by hand
    public function complete(Card $card): ?Run
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
        $card->setStatus(CardStatus::Running);

        return new Run($card);
    }

    // Accepts what the machine produced: a run started by a checklist step ticks that step and goes on to the next, any other closes the card
    public function validate(Card $card): ?Run
    {
        $this->expectReview($card);
        $card->setComment(null);
        $index = $this->stepOfAction($card);
        if (null === $index) {
            return $this->close($card);
        }
        $card->assign(Executor::Human)->setStatus(CardStatus::Ready);

        return $this->tick($card, $index);
    }

    // Sends the output back to the machine with a comment, given to the action as its "comment" parameter: the card's own run waiting for validation, or the last step's run done, that step unticked and queued again
    public function sendBack(Card $card, string $comment): Run
    {
        $last = $card->lastRun();
        $step = $last?->getStep();
        if (null !== $step && !isset($card->getChecklist()[$step])) {
            $step = null;
        }
        if (null === $step || RunStatus::Done !== $last->getStatus() || CardStatus::Done === $card->getStatus()) {
            $this->expectReview($card);
        }
        $comment = trim($comment);
        if ('' === $comment) {
            throw new FlowException(t('flow.comment_needed', [], 'pilot'));
        }
        if (str_starts_with($comment, '-')) {
            throw new FlowException(t('flow.comment_dash', [], 'pilot'));
        }
        if (null !== $last && null !== $step && CardStatus::Review !== $card->getStatus()) {
            if ($card->getChecklist()[$step]['done'] ?? false) {
                $card->toggleStep($step, $this->clock->now());
            }

            return $this->queue($card, (string) $last->getAction(), ['comment' => $comment] + $last->getParams(), $step);
        }
        $card->setComment($comment)->setStatus(CardStatus::Ready);

        return $this->launch($card);
    }

    // Ticks or unticks a step; ticking one whose next step names a machine action queues that action at once
    public function tick(Card $card, int $index): ?Run
    {
        $card->toggleStep($index, $this->clock->now());
        $steps = $card->getChecklist();
        $next = $steps[$index + 1] ?? null;
        if (!($steps[$index]['done'] ?? false) || !$this->stepQueueable($card, $index + 1)) {
            return null;
        }

        return $this->queue($card, $next['action'] ?? '', $next['params'] ?? [], $index + 1);
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

    // Applies the result of a finished run to its card, unless the card was closed meanwhile: a step's run done ticks the step like a hand would, the next step's action queued; any failure stops what is still queued
    public function runFinished(Run $run): ?Run
    {
        $card = $run->getCard();
        if (CardStatus::Running !== $card->getStatus()) {
            return null;
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

            return null;
        }
        $named = $card->getChecklist()[$step] ?? [];
        $next = $done && ($named['action'] ?? null) === $run->getAction() && !($named['done'] ?? false) ? $this->tick($card, $step) : null;
        $card->setStatus(match (true) {
            [] !== $card->activeRuns() => CardStatus::Running,
            $done => CardStatus::Ready,
            default => CardStatus::Failed,
        });

        return $next;
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
        $card->setStatus(CardStatus::Running);

        return new Run($card, $action, $params, $step);
    }

    // Marks a card done on the day and opens the next one of its plan
    private function close(Card $card): ?Run
    {
        $card->markDone($this->clock->now()->setTime(0, 0));
        $next = $card->getPlan()?->cardAfter($card);

        return null === $next ? null : $this->open($next);
    }

    // Makes a card to do, a machine card with its action leaving at once
    private function open(Card $card): ?Run
    {
        $card->setStatus(CardStatus::Ready);

        return $card->isRunner() && null !== $card->getAction() ? $this->launch($card) : null;
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
