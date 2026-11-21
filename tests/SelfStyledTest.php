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

class SelfStyledTest extends TestCase
{
    // What belongs to Bootstrap, Font Awesome or EasyAdmin, none of which the piloting pages load
    private const array DENYLIST = [
        '/\bfa-[a-z]/',
        '/\bfa[srb]\b/',
        '/\bbtn(\b|-)/',
        '/class="[^"]*(?<![\w-])badge\b/',
        '/\btext-bg-/',
        '/\bd-(flex|grid|block|inline|none)\b/',
        '/\bform-(control|select|check|label)\b/',
        '/\blist-group\b/',
        '/\bnav-(link|item|tabs)\b/',
        '/--bs-/',
        '/bootstrap_5/',
        '/easyadmin\.assets\.package/',
    ];

    // The templates and the scripts use the bundle's own classes and icons only
    public function testNothingLeansOnAnotherStyle(): void
    {
        $root = \dirname(__DIR__);
        $files = [...glob($root . '/templates/*/*.twig') ?: [], ...glob($root . '/templates/*/*/*.twig') ?: [], ...glob($root . '/assets/js/*.js') ?: []];
        $this->assertNotEmpty($files);
        foreach ($files as $file) {
            $text = (string) preg_replace('/\{#.*?#\}|\/\/[^\n]*/s', '', (string) file_get_contents($file));
            $text = str_replace('pilot-btn', 'pilot-button', $text);
            foreach (self::DENYLIST as $pattern) {
                $this->assertDoesNotMatchRegularExpression($pattern, $text, basename($file));
            }
        }
    }
}
