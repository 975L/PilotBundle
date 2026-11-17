<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Service;

use c975L\PilotBundle\Entity\Card;
use c975L\PilotBundle\Entity\Machine;
use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Entity\Run;
use c975L\PilotBundle\Enum\CardStatus;
use c975L\PilotBundle\Enum\RunStatus;
use c975L\PilotBundle\Exception\FlowException;
use c975L\PilotBundle\Service\CardFlow;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

class CardFlowTest extends TestCase
{
    private CardFlow $flow;
    private Card $card;

    protected function setUp(): void
    {
        $this->flow = new CardFlow(new MockClock('2026-11-17 10:00'));
        $this->card = new Card(new Project('catalog', 'Catalog'), 'item-1');
    }

    // Handing a card to a machine makes it a runner card with its action and a queued run
    public function testEntrustQueuesARun(): void
    {
        $run = $this->flow->entrust($this->card, 'hello', ['text' => 'catalog']);

        $this->assertTrue($this->card->isRunner());
        $this->assertSame('hello', $this->card->getAction());
        $this->assertSame(['text' => 'catalog'], $this->card->getParams());
        $this->assertSame(CardStatus::Running, $this->card->getStatus());
        $this->assertSame(RunStatus::Queued, $run->getStatus());
        $this->assertSame($run, $this->card->lastRun());
    }

    // A card already running cannot be handed over a second time
    public function testEntrustRefusedWhileRunning(): void
    {
        $this->flow->entrust($this->card, 'hello', []);

        $this->expectException(FlowException::class);
        $this->flow->entrust($this->card, 'hello', []);
    }

    // A run still waiting is withdrawn and the card is to do again; one a machine holds cannot be
    public function testCancelOnlyWhileQueued(): void
    {
        $run = $this->flow->entrust($this->card, 'hello', []);
        $this->flow->cancel($this->card);

        $this->assertSame(CardStatus::Ready, $this->card->getStatus());
        $this->assertSame(RunStatus::Failed, $run->getStatus());

        $this->flow->entrust($this->card, 'hello', [])->claim(new Machine('catalog', str_repeat('0', 64)), new \DateTimeImmutable('2026-11-17 10:00'));
        $this->expectException(FlowException::class);
        $this->flow->cancel($this->card);
    }

    // Ticking a step whose next one names an action hands the card to it; unticking or a plain next step does nothing
    public function testTickLaunchesTheNextStepsAction(): void
    {
        $this->card->setChecklist([['t' => 'first'], ['t' => 'second', 'action' => 'hello', 'params' => ['text' => 'catalog']], ['t' => 'third']]);

        $run = $this->flow->tick($this->card, 0);
        $this->assertNotNull($run);
        $this->assertSame('hello', $this->card->getAction());
        $this->assertSame(['text' => 'catalog'], $this->card->getParams());
        $this->assertSame(CardStatus::Running, $this->card->getStatus());

        $this->assertNull($this->flow->tick($this->card, 1));
        $this->assertNull($this->flow->tick($this->card, 0));
    }

    // A finished run puts the card to validate, a failed one marks it failed
    public function testRunFinishedSetsReviewOrFailed(): void
    {
        $run = $this->flow->entrust($this->card, 'hello', []);
        $this->finish($run, RunStatus::Done);
        $this->flow->runFinished($run);
        $this->assertSame(CardStatus::Review, $this->card->getStatus());

        $this->card->setStatus(CardStatus::Failed);
        $again = $this->flow->launch($this->card);
        $this->finish($again, RunStatus::Failed);
        $this->flow->runFinished($again);
        $this->assertSame(CardStatus::Failed, $this->card->getStatus());
    }

    // A card closed by hand while its run goes on stays closed when the run ends
    public function testRunFinishedLeavesAClosedCard(): void
    {
        $run = $this->flow->entrust($this->card, 'hello', []);
        $this->card->markDone(new \DateTimeImmutable('2026-11-17'));
        $this->finish($run, RunStatus::Done);
        $this->flow->runFinished($run);

        $this->assertSame(CardStatus::Done, $this->card->getStatus());
    }

    // Validating closes the card on the day; sending back needs a comment and relaunches with it
    public function testValidateAndSendBack(): void
    {
        $this->review();
        $second = $this->flow->sendBack($this->card, ' Shorter ');
        $this->assertSame('Shorter', $this->card->getComment());
        $this->assertSame(CardStatus::Running, $this->card->getStatus());
        $this->assertCount(2, $this->card->getRuns());

        $this->finish($second, RunStatus::Done);
        $this->flow->runFinished($second);
        $this->flow->validate($this->card);
        $this->assertSame(CardStatus::Done, $this->card->getStatus());
        $this->assertNull($this->card->getComment());
        $this->assertSame('2026-11-17', $this->card->getDoneAt()?->format('Y-m-d'));
    }

    // An empty comment or one read as an option is refused
    public function testSendBackRefusesEmptyOrDashComment(): void
    {
        $this->review();
        foreach (['  ', '-rf'] as $comment) {
            try {
                $this->flow->sendBack($this->card, $comment);
                $this->fail('Comment accepted: ' . $comment);
            } catch (FlowException $exception) {
                $this->assertStringStartsWith('flow.', $exception->getMessage());
            }
        }
    }

    // Nothing to validate on a card that is not waiting for it
    public function testValidateRefusedOutsideReview(): void
    {
        $this->expectException(FlowException::class);
        $this->flow->validate($this->card);
    }

    // A card with a finished run waiting for validation
    private function review(): void
    {
        $run = $this->flow->entrust($this->card, 'hello', []);
        $this->finish($run, RunStatus::Done);
        $this->flow->runFinished($run);
    }

    // A run claimed by a machine and finished with a status
    private function finish(Run $run, RunStatus $status): void
    {
        $now = new \DateTimeImmutable('2026-11-17 10:00');
        $run->claim(new Machine('machine-1', str_repeat('a', 64)), $now)->finish($status, null, [], null, $now);
    }
}
