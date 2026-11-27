<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests;

use c975L\PilotBundle\Twig\PilotIconExtension;
use PHPUnit\Framework\TestCase;

// An open card goes to the card beside by its arrows, the arrow keys or a swipe, without closing its dialog
class CardDialogNavigationTest extends TestCase
{
    // The arrows sit in the dialog, outside the body it empties and outside the form that closes it, hidden until a card is beside
    public function testLayoutHoldsTheArrowsOutsideTheClosingForm(): void
    {
        $layout = (string) file_get_contents(__DIR__ . '/../templates/pilot/_layout.html.twig');

        foreach (['previous' => 'chevron-left', 'next' => 'chevron-right'] as $target => $icon) {
            $this->assertMatchesRegularExpression('#<button type="button"[^>]* hidden data-pilot-card-modal-target="' . $target . '" data-action="pilot-card-modal\#' . $target . '"[^>]*>\{\{ pilot_icon\(\'' . $icon . '\'\) \}\}</button>\s*(<button|<form method="dialog")#', $layout);
            $this->assertFileExists(PilotIconExtension::DIRECTORY . '/' . $icon . '.svg');
        }
    }

    // The controller declares the arrows, steps by them, by the arrow keys and by a swipe, and keeps the back button closing the dialog
    public function testControllerStepsThroughTheCards(): void
    {
        $script = (string) file_get_contents(__DIR__ . '/../assets/js/pilot-card-modal.js');

        $this->assertStringContainsString('"previous", "next"]', $script);
        $this->assertStringContainsString('{ ArrowLeft: -1, ArrowRight: 1 }', $script);
        $this->assertStringContainsString('addEventListener("touchend"', $script);
        $this->assertMatchesRegularExpression('/go\(step\) \{\n(?:(?!\n    \}).)*replaceState/s', $script);
        $this->assertStringContainsString('removeEventListener("keydown", this.onArrow)', $script);
        $this->assertMatchesRegularExpression('/open\(url\) \{\n(?:(?!\n    \}).)*if \(url !== this\.current\) return;/s', $script);
    }
}
