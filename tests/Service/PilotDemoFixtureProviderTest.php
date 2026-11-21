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
use c975L\PilotBundle\Entity\CardSet;
use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Service\PilotDemoFixtureProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Contracts\Translation\TranslatorInterface;

class PilotDemoFixtureProviderTest extends TestCase
{
    // Each space comes before its collection, the collection before its cards, which is the order they are flushed in
    public function testTheSpacesComeBeforeTheirCards(): void
    {
        $fixtures = $this->fixtures();

        $this->assertInstanceOf(Project::class, $fixtures[0]);
        $this->assertInstanceOf(CardSet::class, $fixtures[1]);
        $this->assertSame(['demo-site', 'demo-catalog'], array_map(static fn (Project $space): string => $space->getSlug(), $this->ofClass($fixtures, Project::class)));
        foreach ($this->ofClass($fixtures, Card::class) as $card) {
            $this->assertSame($card->getProject(), $card->getCardSet()->getProject());
            $this->assertLessThan(array_search($card, $fixtures, true), array_search($card->getCardSet(), $fixtures, true));
        }
    }

    // The cards show what a card holds: a step ticked, a feedback, a day late and a coming one, an answer awaited, a card done
    public function testTheCardsShowWhatACardHolds(): void
    {
        $cards = [];
        foreach ($this->ofClass($this->fixtures(), Card::class) as $card) {
            $cards[$card->getTitle()] = $card;
        }

        $home = $cards['label.demo_card_home'];
        $this->assertSame(['label.demo_step_outline', 'label.demo_step_texts', 'label.demo_step_review'], array_column($home->getSteps(), 't'));
        $this->assertTrue($home->getSteps()[0]['done']);
        $this->assertSame(['label.demo_feedback_photo'], array_column($home->getFeedback(), 't'));
        $this->assertSame('2026-11-23', $home->getDue()->format('Y-m-d'));
        $this->assertSame('2026-11-20', $cards['label.demo_card_domain']->getDue()->format('Y-m-d'));
        $this->assertNull($cards['label.demo_card_launch']->getDue());
        $this->assertSame('label.demo_waiting_designer', $cards['label.demo_card_launch']->getWaitingOn());
        $this->assertTrue($cards['label.demo_card_theme']->isDone());
        $this->assertFalse($home->isDone());
    }

    // The provider, its words left as their keys and its day fixed
    /** @return list<object> */
    private function fixtures(): array
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return [...new PilotDemoFixtureProvider($translator, new MockClock('2026-11-21 10:00'))->getDemoFixtures()];
    }

    // The fixtures of one class, in their order
    /**
     * @template T of object
     *
     * @param list<object>    $fixtures
     * @param class-string<T> $class
     *
     * @return list<T>
     */
    private function ofClass(array $fixtures, string $class): array
    {
        return array_values(array_filter($fixtures, static fn (object $fixture): bool => $fixture instanceof $class));
    }
}
