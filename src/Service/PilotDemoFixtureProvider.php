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
use c975L\UiBundle\Contract\DemoFixtureProviderInterface;
use Psr\Clock\ClockInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// The demo's piloting: two spaces, a collection each, and cards showing what a card holds - steps ticked or not, a feedback, a day late, coming or none, an answer awaited, a card done - written in the site's locale. No member, no machine and no run: the demo's admin sees every space, and a machine is a computer of the visitor's own
class PilotDemoFixtureProvider implements DemoFixtureProviderInterface
{
    // Slug, name key, colour, collection name key, then its cards
    private const array SPACES = [
        ['demo-site', 'label.demo_space_site', '#7F8F3C', 'label.demo_collection_tasks', [
            ['title' => 'label.demo_card_home', 'due' => 2, 'steps' => ['label.demo_step_outline', 'label.demo_step_texts', 'label.demo_step_review'], 'ticked' => 1, 'feedback' => 'label.demo_feedback_photo'],
            ['title' => 'label.demo_card_contact', 'due' => 9, 'steps' => ['label.demo_step_fields', 'label.demo_step_test']],
            ['title' => 'label.demo_card_domain', 'due' => -1],
            ['title' => 'label.demo_card_launch', 'waiting' => 'label.demo_waiting_designer'],
            ['title' => 'label.demo_card_theme', 'steps' => ['label.demo_step_palette'], 'ticked' => 1, 'done' => true],
        ]],
        ['demo-catalog', 'label.demo_space_catalog', '#2B5D8A', 'label.demo_collection_items', [
            ['title' => 'label.demo_card_item_1', 'due' => 5, 'steps' => ['label.demo_step_photos', 'label.demo_step_description', 'label.demo_step_price'], 'ticked' => 1],
            ['title' => 'label.demo_card_item_2', 'steps' => ['label.demo_step_photos', 'label.demo_step_description', 'label.demo_step_price']],
        ]],
    ];

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly ClockInterface $clock,
    ) {
    }

    // Each space, then its collection, then its cards, in the order they are flushed
    public function getDemoFixtures(): iterable
    {
        $now = $this->clock->now();
        foreach (self::SPACES as $position => [$slug, $name, $color, $collection, $cards]) {
            $space = new Project($slug, $this->words($name))
                ->setColor($color)
                ->setPosition($position);
            yield $space;

            $set = new CardSet($space, $this->words($collection));
            yield $set;

            foreach ($cards as $rank => $row) {
                yield $this->card($space, $set, $rank, $row, $now);
            }
        }
    }

    // One card of a SPACES row: its steps, the first ones ticked, its feedback, its day counted from today, the answer it waits for, done or not
    /** @param array{title: string, due?: int, steps?: list<string>, ticked?: int, feedback?: string, waiting?: string, done?: bool} $row */
    private function card(Project $space, CardSet $set, int $rank, array $row, \DateTimeImmutable $now): Card
    {
        $card = new Card($space, $this->words($row['title']))
            ->setCardSet($set)
            ->setRank($rank);
        foreach ($row['steps'] ?? [] as $step) {
            $card->addStep($this->words($step));
        }
        for ($index = 0; $index < ($row['ticked'] ?? 0); ++$index) {
            $card->toggleStep($index, $now);
        }
        if (isset($row['feedback'])) {
            $card->addFeedback($this->words($row['feedback']));
        }
        if (isset($row['due'])) {
            $card->setDue($now->setTime(0, 0)->modify(\sprintf('%+d days', $row['due'])));
        }
        if (isset($row['waiting'])) {
            $card->setWaitingOn($this->words($row['waiting']));
        }

        return ($row['done'] ?? false) ? $card->markDone($now) : $card;
    }

    // A demo text in the site's locale
    private function words(string $key): string
    {
        return $this->translator->trans($key, [], 'pilot');
    }
}
