<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests;

use c975L\ConfigBundle\Test\CodeRulesTestCase;

// The audit's rules replayed on the bundle's own code (see ConfigBundle's CodeRulesTestCase)
class CodeRulesTest extends CodeRulesTestCase
{
    // Where the bundle's code lives
    protected function sourceDirectory(): string
    {
        return __DIR__ . '/../src';
    }

    // The namespace of that code
    protected function sourceNamespace(): string
    {
        return 'c975L\PilotBundle';
    }

    // The legitimate exceptions, each with its reason
    protected function allowed(): array
    {
        return [
            self::BULK_QUERY => [
                'c975L\\PilotBundle\\Repository\\CreditRepository::spend' => 'Balances are read live by the dashboard and the steps, never held in a cached fragment',
                'c975L\\PilotBundle\\Repository\\RunRepository::take' => 'Runs are read live by the machines and the card page, never held in a cached fragment',
            ],
        ];
    }
}
