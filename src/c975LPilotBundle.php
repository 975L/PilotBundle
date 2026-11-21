<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle;

use c975L\PilotBundle\Controller\RunnerController;
use c975L\PilotBundle\Controller\ShareController;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class c975LPilotBundle extends AbstractBundle
{
    public function loadExtension(array $config, ContainerConfigurator $containerConfigurator, ContainerBuilder $containerBuilder): void
    {
        $containerConfigurator->import('../config/services.yaml');
    }

    // The bundle's scripts resolved under "@c975l/pilot-bundle" by AssetMapper, the ceiling on the tokens the runner API refuses and on the writes of an editing link
    public function prependExtension(ContainerConfigurator $containerConfigurator, ContainerBuilder $containerBuilder): void
    {
        $containerBuilder->prependExtensionConfig('framework', [
            'asset_mapper' => [
                'paths' => [
                    __DIR__ . '/../assets' => '@c975l/pilot-bundle',
                ],
            ],
            // 20 unknown tokens per address and per hour, after which the address is refused even with a right one until the window ends
            'rate_limiter' => [
                RunnerController::DENIED_LIMITER => [
                    'policy' => 'fixed_window',
                    'limit' => 20,
                    'interval' => '1 hour',
                ],
                // 300 writes per editing link and per hour, after which the link answers 429 until the window ends
                ShareController::WRITE_LIMITER => [
                    'policy' => 'fixed_window',
                    'limit' => 300,
                    'interval' => '1 hour',
                ],
            ],
        ]);
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
