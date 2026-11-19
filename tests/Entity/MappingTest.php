<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Entity;

use c975L\PilotBundle\Tests\Fixtures\TestEntityManager;
use Doctrine\ORM\Tools\SchemaValidator;
use PHPUnit\Framework\TestCase;

// The attribute mapping of every entity holds together: associations on both sides, enums, tables prefixed "pilot_"
class MappingTest extends TestCase
{
    public function testMappingIsValidAndPrefixed(): void
    {
        $entityManager = TestEntityManager::create();

        $this->assertSame([], new SchemaValidator($entityManager)->validateMapping());
        foreach ($entityManager->getMetadataFactory()->getAllMetadata() as $metadata) {
            $this->assertStringStartsWith('pilot_', $metadata->getTableName(), $metadata->getName());
        }
    }
}
