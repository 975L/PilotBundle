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

    // The tile rendered alone, routes, tokens and dates stubbed
    private function render(bool $sheet): string
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['label.card_number_short' => 'No. %id%'], 'en', 'pilot');

        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 2) . '/templates', 'c975LPilot');
        $twig = new Environment($loader, ['strict_variables' => true]);
        $twig->addExtension(new TranslationExtension($translator));
        $twig->addFunction(new TwigFunction('pilot_icon', new PilotIconExtension()->icon(...), ['is_safe' => ['html']]));
        $twig->addFunction(new TwigFunction('path', static fn (string $route): string => '/' . $route));
        $twig->addFunction(new TwigFunction('csrf_token', static fn (string $id): string => 'token'));
        $twig->addFilter(new TwigFilter('format_date', static fn (mixed $date): string => 'date'));
        $twig->addFunction(new TwigFunction('pilot_card_path', static fn (array $card): string => '/card/' . $card['id']));

        return $twig->render('@c975LPilot/pilot/_card.html.twig', ['card' => [
            'id' => 7,
            'title' => 'item-1',
            'sheet' => $sheet,
            'done' => false,
            'image' => null,
            'note' => null,
            'due' => null,
            'steps' => [],
            'feedback' => [],
            'waitingOn' => null,
            'resultUrl' => null,
            'status' => ['value' => 'ready', 'label' => 'status.ready'],
            'project' => ['id' => 1, 'slug' => 'catalog', 'name' => 'Catalog'],
        ]]);
    }
}
