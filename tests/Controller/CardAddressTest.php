<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Controller;

use c975L\PilotBundle\Controller\PilotController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

// A card has one address a browser opens, its space's page with its dialog; its own page is read by the dialog alone, a guided project included
class CardAddressTest extends TestCase
{
    // The dialog's reading and a gesture made in it reach the card's own page, a browser opening it does not
    public function testOnlyTheDialogReadsTheCardPage(): void
    {
        $fromDialog = new \ReflectionMethod(PilotController::class, 'fromDialog');

        $this->assertTrue($fromDialog->invoke(null, $this->request(['X-Requested-With' => 'XMLHttpRequest'])));
        $this->assertTrue($fromDialog->invoke(null, $this->request([PilotController::DIALOG_HEADER => '1'])));
        $this->assertFalse($fromDialog->invoke(null, $this->request([])));
    }

    // A guided project no longer sends the card to its own page: the dialog opens without its backdrop, its panel left usable, and a failure is said rather than followed to the page
    public function testDialogOpensDuringAGuidedProject(): void
    {
        $script = (string) file_get_contents(__DIR__ . '/../../assets/js/pilot-card-modal.js');

        $this->assertStringContainsString('if (document.querySelector(".guided-project-panel")) dialog.show();', $script);
        $this->assertStringContainsString('.catch(() => notice(this.failedLabelValue));', $script);
        $this->assertStringNotContainsString('window.location.href = url', $script);
    }

    // A request carrying the given headers
    /** @param array<string, string> $headers */
    private function request(array $headers): Request
    {
        $request = Request::create('/pilot/catalog/12');
        $request->headers->add($headers);

        return $request;
    }
}
