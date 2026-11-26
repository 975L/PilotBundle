<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Fixtures;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\PilotBundle\Management\PilotGuidedProjectProvider;
use c975L\PilotBundle\Service\PilotAsk;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

// The stubs several test classes share: a controller's container granting every right and token, the screenshots' addresses and the guided projects
trait PilotTestStubs
{
    // A controller's container with the router given, every right granted and every token valid
    private function grantedContainer(UrlGeneratorInterface $router): Container
    {
        $checker = $this->createStub(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturn(true);
        $csrf = $this->createStub(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturn(true);
        $container = new Container();
        $container->set('router', $router);
        $container->set('security.authorization_checker', $checker);
        $container->set('security.csrf.token_manager', $csrf);

        return $container;
    }

    // Addresses of the screenshots, as the router writes them
    private function urls(): UrlGeneratorInterface
    {
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturnCallback(static fn (string $route, array $parameters): string => 'https://example.com/pilot/share/image/' . $parameters['name']);

        return $urls;
    }

    // The guided projects for an admin, their routes answered as their path, Donovan on or off
    /** @return list<array<string, mixed>> */
    private function guidedProjects(bool $ask = true): array
    {
        $config = $this->createStub(ConfigServiceInterface::class);
        $config->method('get')->willReturn('ROLE_ADMIN');
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturnCallback(static fn (string $route, array $parameters = []): string => '/pilot/' . $route . ('' !== implode('', $parameters) ? '/' . implode('/', $parameters) : ''));

        $pilotAsk = $this->createStub(PilotAsk::class);
        $pilotAsk->method('isEnabled')->willReturn($ask);

        return new PilotGuidedProjectProvider($config, $urls, $pilotAsk)->getGuidedProjects();
    }
}
