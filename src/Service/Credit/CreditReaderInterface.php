<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Service\Credit;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

// Reads a paid service's balance from its API: implement it in an app to offer one more service in the credits' settings
#[AutoconfigureTag(self::TAG)]
interface CreditReaderInterface
{
    public const string TAG = 'c975l.pilot.credit_reader';

    // The services it reads, as stored on the credit, with their label in the pilot domain
    /** @return array<string, string> provider => label */
    public function providers(): array;

    // The balance of the account the key opens, throwing when the service cannot be read
    public function read(string $provider, string $apiKey, ?string $account): CreditBalance;
}
