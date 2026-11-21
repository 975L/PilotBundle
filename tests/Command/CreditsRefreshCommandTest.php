<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Command;

use c975L\ConfigBundle\Service\VaultEncryptor;
use c975L\PilotBundle\Command\CreditsRefreshCommand;
use c975L\PilotBundle\Entity\Credit;
use c975L\PilotBundle\Repository\CreditRepository;
use c975L\PilotBundle\Service\Credit\ElevenLabsReader;
use c975L\PilotBundle\Service\CreditRefresher;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

class CreditsRefreshCommandTest extends TestCase
{
    // Each credit naming a service is read and written, one without its key said so, one typed in by hand left as it is
    public function testReadsTheServicesOnly(): void
    {
        $vaultEncryptor = new VaultEncryptor('test-vault-key');
        $read = new Credit('item-1')->setProvider('elevenlabs')->setApiKey($vaultEncryptor->encrypt('key-1'));
        $typed = new Credit('item-2')->setRemaining(5);
        $keyless = new Credit('item-3')->setProvider('elevenlabs');
        $repository = $this->createStub(CreditRepository::class);
        $repository->method('findOrdered')->willReturn([$read, $typed, $keyless]);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');
        $client = new MockHttpClient(new JsonMockResponse(['character_limit' => 100, 'character_count' => 40]));
        $refresher = new CreditRefresher([new ElevenLabsReader($client)], $vaultEncryptor, new MockClock(), new NullLogger(), $repository);

        $tester = new CommandTester(new CreditsRefreshCommand($repository, $refresher, $entityManager));
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $this->assertSame(60, $read->getRemaining());
        $this->assertSame(5, $typed->getRemaining());
        $this->assertSame("item-1: 60\nitem-3: no API key\n", $tester->getDisplay());
    }
}
