<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests;

use PHPUnit\Framework\TestCase;

// A refusal or a failure is said in the layout's notice dialog, never in the browser's alert
class NoticeDialogTest extends TestCase
{
    // Only pilot-notice.js falls back on the browser's alert, for a page without the dialog
    public function testNoControllerCallsTheBrowserAlert(): void
    {
        foreach (glob(__DIR__ . '/../assets/js/*.js') ?: [] as $file) {
            if ('pilot-notice.js' !== basename($file)) {
                $this->assertStringNotContainsString('window.alert(', (string) file_get_contents($file), basename($file));
            }
        }
    }

    // A reload waits for the notice to be closed, the dialog not blocking the page as the alert did
    public function testNoReloadRightAfterANotice(): void
    {
        foreach (glob(__DIR__ . '/../assets/js/*.js') ?: [] as $file) {
            $this->assertDoesNotMatchRegularExpression('/notice\([^;]*\);\s*window\.location\.reload\(/', (string) file_get_contents($file), basename($file));
        }
        $this->assertStringContainsString('addEventListener("close", resolve', (string) file_get_contents(__DIR__ . '/../assets/js/pilot-notice.js'));
    }

    // The layout holds the dialog pilot-notice.js fills, with its message and its title
    public function testLayoutHoldsTheNoticeDialog(): void
    {
        $layout = (string) file_get_contents(__DIR__ . '/../templates/pilot/_layout.html.twig');

        $this->assertStringContainsString('class="pilot-dialog pilot-confirm pilot-notice"', $layout);
        $this->assertStringContainsString('data-pilot-notice-message', $layout);
        $this->assertStringContainsString("'label.notice_title'|trans({}, 'pilot')", $layout);
    }
}
