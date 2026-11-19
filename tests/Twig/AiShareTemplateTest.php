<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Twig;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Translation\IdentityTranslator;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class AiShareTemplateTest extends TestCase
{
    // An open step gets the button copying its text followed by its card's markdown
    public function testOpenStepIsCopiedWithItsCard(): void
    {
        $html = $this->render(['t' => 'item-1']);

        $this->assertStringContainsString('data-controller="pilot-copy"', $html);
        $this->assertStringContainsString('data-pilot-copy-text-value="label.ai_task"', $html);
        $this->assertStringContainsString('data-pilot-copy-context-value="pilot-ai-context-7"', $html);
    }

    // A done step has nothing left to hand over
    public function testDoneStepHasNoButton(): void
    {
        $this->assertSame('', trim($this->render(['t' => 'item-1', 'done' => true])));
    }

    // The partial rendered alone, its labels left as their keys
    /** @param array<string, mixed> $step */
    private function render(array $step): string
    {
        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 2) . '/templates', 'c975LPilot');
        $twig = new Environment($loader, ['strict_variables' => true]);
        $twig->addExtension(new TranslationExtension(new IdentityTranslator()));

        return $twig->render('@c975LPilot/pilot/_ai_share.html.twig', ['step' => $step, 'card' => ['id' => 7]]);
    }
}
