<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Fixtures;

use c975L\ConfigBundle\Contract\UserInterface;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\ResolveTargetEntityListener;
use Doctrine\ORM\Tools\SchemaTool;

// An entity manager on an in-memory database mapping the bundle's entities, the account they name standing as UserFixture
final class TestEntityManager
{
    // The manager, its schema created when asked
    public static function create(bool $schema = false): EntityManager
    {
        $config = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 2) . '/src/Entity', __DIR__], true);
        $config->enableNativeLazyObjects(true);
        $config->setUseSchemaEditorApi(true);
        $resolver = new ResolveTargetEntityListener();
        $resolver->addResolveTargetEntity(UserInterface::class, UserFixture::class, []);
        $events = new EventManager();
        $events->addEventSubscriber($resolver);
        $entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config), $config, $events);
        if ($schema) {
            new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
        }

        return $entityManager;
    }
}
