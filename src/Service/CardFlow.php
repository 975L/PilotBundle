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

// Moves the cards: a card handed to a machine is queued, run, then validated or sent back with a comment; a card closed in a plan opens the next one, a machine card leaving on its own
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

    // Accepts what the machine produced, which closes the card
    public function validate(Card $card): ?Run
    {
        $this->expectReview($card);
        $card->setComment(null);

        return $this->close($card);
    }

    // Sends the output back to the machine with a comment, given to the action as its "comment" parameter
    public function sendBack(Card $card, string $comment): Run
    {
        $this->expectReview($card);
        $comment = trim($comment);
        if ('' === $comment) {
            throw new FlowException(t('flow.comment_needed', [], 'pilot'));
        }
        if (str_starts_with($comment, '-')) {
            throw new FlowException(t('flow.comment_dash', [], 'pilot'));
        }
        $card->setComment($comment)->setStatus(CardStatus::Ready);

        return $this->launch($card);
    }

    // Withdraws the run a card waits on while no machine has taken it, the card back to do
    public function cancel(Card $card): Run
    {
        $run = $card->lastRun();
        if (CardStatus::Running !== $card->getStatus() || RunStatus::Queued !== $run?->getStatus()) {
            throw new FlowException(t('flow.not_now', [], 'pilot'));
        }
        $card->setStatus(CardStatus::Ready);

        return $run->cancel($this->clock->now());
    }

    // Applies the result of a finished run to its card, unless the card was closed meanwhile
    public function runFinished(Run $run): void
    {
        $card = $run->getCard();
        if (CardStatus::Running !== $card->getStatus()) {
            return;
        }
        $card->setStatus(RunStatus::Done === $run->getStatus() ? CardStatus::Review : CardStatus::Failed);
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

    // Refuses a validation gesture on a card with nothing to validate
    private function expectReview(Card $card): void
    {
        if (CardStatus::Review !== $card->getStatus()) {
            throw new FlowException(t('flow.not_now', [], 'pilot'));
        }
    }
}
