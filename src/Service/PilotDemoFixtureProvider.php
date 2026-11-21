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
use c975L\PilotBundle\Entity\CardSet;
use c975L\PilotBundle\Entity\Doc;
use c975L\PilotBundle\Entity\Machine;
use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Entity\Run;
use c975L\PilotBundle\Enum\RunStatus;
use c975L\UiBundle\Contract\DemoFixtureProviderInterface;
use Psr\Clock\ClockInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// The demo's piloting: three spaces, a collection each, and cards showing what a card holds - steps ticked or not, a feedback, a day late, coming or none, an answer awaited, a card done - written in the site's locale. The automation space shows a machine at work without one ever running: its machine has a token nobody knows and was last seen two days ago, a run it made is already done with its report and the paths of its files, and an action left to launch only queues a run no machine takes. No member: the demo's admin sees every space
class PilotDemoFixtureProvider implements DemoFixtureProviderInterface
{
    // Slug, name key, colour, collection name key, then its cards
    private const array SPACES = [
        ['demo-site', 'label.demo_space_site', '#2F8F83', 'label.demo_collection_tasks', [
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
        [self::AUTOMATION, 'label.demo_space_automation', '#7F8F3C', 'label.demo_collection_runs', [
            ['title' => 'label.demo_card_report', 'due' => 0, 'steps' => ['label.demo_step_gather', 'label.demo_step_read'], 'actions' => [0 => self::REPORT], 'ran' => 0],
            ['title' => 'label.demo_card_export', 'due' => 3, 'steps' => ['label.demo_step_export', 'label.demo_step_check'], 'actions' => [0 => self::EXPORT]],
        ]],
    ];

    // The space whose cards a machine works on, and its two actions
    private const string AUTOMATION = 'demo-automation';
    private const string REPORT = 'demo-report';
    private const string EXPORT = 'demo-export';

    // The machine's name, also the initials it ticks a step with
    private const string MACHINE = 'demo-runner';

    // Where the machine left the files of its run, on its own disk, never sent to the site
    private const array FILES = ['/home/demo/reports/report.md', '/home/demo/reports/chart.png'];

    // The machine and the runs of the automation space, made when its cards are
    private ?Machine $machine = null;

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

            if (self::AUTOMATION === $slug) {
                yield from $this->machinery($space, $now);
            }
            foreach ($cards as $rank => $row) {
                $card = $this->card($space, $set, $rank, $row, $now);
                if (isset($row['ran'])) {
                    yield from $this->ran($card, $row['ran'], $now);
                }
                yield $card;
                yield from $card->getRuns();
            }
        }
    }

    // The machine and the actions it declared, offered on the automation space: a token drawn and never shown, so that nothing ever claims a run in its name
    /** @return iterable<object> */
    private function machinery(Project $space, \DateTimeImmutable $now): iterable
    {
        $capabilities = [];
        foreach ([self::REPORT => 'label.demo_action_report', self::EXPORT => 'label.demo_action_export'] as $key => $label) {
            $capabilities[$key] = ['label' => $this->words($label), 'params' => []];
        }
        $this->machine = new Machine(self::MACHINE, hash('sha256', random_bytes(32)))
            ->seen($capabilities, $now->modify('-2 days'))
            ->addProject($space);
        yield $this->machine;

        foreach (array_keys($capabilities) as $position => $key) {
            yield new Action($key)
                ->declare($capabilities[$key]['label'], [], false, null, $position)
                ->addProject($space);
        }
    }

    // The run a card's step was done by, an hour ago: its file written as the report the card links, the paths of what it left on the machine, the step ticked in the machine's name
    /** @return iterable<object> */
    private function ran(Card $card, int $step, \DateTimeImmutable $now): iterable
    {
        $report = new Doc('demo-report', $this->words('label.demo_doc_title'))
            ->setProject($card->getProject())
            ->write($this->words('label.demo_doc_summary'), $this->words('label.demo_doc_detail'));
        yield $report;
        $card->setDoc($report);

        new Run($card, self::REPORT, [], $step)
            ->claim($this->machine, $now->modify('-1 hour'))
            ->setFiles(self::FILES)
            ->finish(RunStatus::Done, $this->words('label.demo_run_done'), [], $now->modify('-50 minutes'));
        $card->toggleStep($step, $now->modify('-50 minutes'), self::MACHINE);
    }

    // One card of a SPACES row: its steps and the machine action each names, the first ones ticked, its feedback, its day counted from today, the answer it waits for, done or not
    /** @param array{title: string, due?: int, steps?: list<string>, actions?: array<int, string>, ticked?: int, ran?: int, feedback?: string, waiting?: string, done?: bool} $row */
    private function card(Project $space, CardSet $set, int $rank, array $row, \DateTimeImmutable $now): Card
    {
        $card = new Card($space, $this->words($row['title']))
            ->setCardSet($set)
            ->setRank($rank);
        foreach ($row['steps'] ?? [] as $step) {
            $card->addStep($this->words($step));
        }
        foreach ($row['actions'] ?? [] as $index => $action) {
            $card->setStepAction($index, $action);
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
