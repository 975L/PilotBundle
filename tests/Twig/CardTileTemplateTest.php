<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Twig;

use c975L\PilotBundle\Twig\PilotIconExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

class CardTileTemplateTest extends TestCase
{
    // A card's tile shows its number, to name it to an agent without opening it
    public function testTileShowsNumber(): void
    {
        $this->assertStringContainsString('No. 7', $this->render(false));
    }

    // A sheet's tile shows its number under its title too
    public function testSheetTileShowsNumber(): void
    {
        $this->assertStringContainsString('No. 7', $this->render(true));
    }

    // In the credits outlook, a tile shows the steps that could spend a credit with their cost, then the first step awaited and how many more, all of them in its tooltip
    public function testTileShowsOffer(): void
    {
        $html = $this->render(false, ['steps' => [['index' => 1, 'amount' => 600, 'credit' => ['unit' => 'units', 'label' => 'Item 2']]], 'awaits' => ['step-a', 'step-b']]);

        $this->assertStringContainsString('step-2 <span class="pilot-tag">600 units · Item 2</span>', $html);
        $this->assertStringContainsString('title="step-a, step-b">First: step-a +1', $html);
    }

    // The tile rendered alone, routes, tokens, dates and numbers stubbed, with its outlook offer when given
    /** @param array<string, mixed>|null $offer */
    private function render(bool $sheet, ?array $offer = null): string
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['label.card_number_short' => 'No. %id%', 'label.outlook_awaits' => 'First: %steps%'], 'en', 'pilot');

        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 2) . '/templates', 'c975LPilot');
        $twig = new Environment($loader, ['strict_variables' => true]);
        $twig->addExtension(new TranslationExtension($translator));
        $twig->addFunction(new TwigFunction('pilot_icon', new PilotIconExtension()->icon(...), ['is_safe' => ['html']]));
        $twig->addFunction(new TwigFunction('path', static fn (string $route): string => '/' . $route));
        $twig->addFunction(new TwigFunction('csrf_token', static fn (string $id): string => 'token'));
        $twig->addFilter(new TwigFilter('format_date', static fn (mixed $date): string => 'date'));
        $twig->addFilter(new TwigFilter('format_number', static fn (int $number): string => (string) $number));
        $twig->addFunction(new TwigFunction('pilot_card_path', static fn (array $card): string => '/card/' . $card['id']));

        return $twig->render('@c975LPilot/pilot/_card.html.twig', (null === $offer ? [] : ['offer' => $offer]) + ['card' => [
            'id' => 7,
            'title' => 'item-1',
            'sheet' => $sheet,
            'done' => false,
            'image' => null,
            'note' => null,
            'due' => null,
            'steps' => [],
            'checklist' => [['t' => 'step-1'], ['t' => 'step-2']],
            'feedback' => [],
            'waitingOn' => null,
            'resultUrl' => null,
            'status' => ['value' => 'ready', 'label' => 'status.ready'],
            'project' => ['id' => 1, 'slug' => 'catalog', 'name' => 'Catalog'],
        ]]);
    }
}
