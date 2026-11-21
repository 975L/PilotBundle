<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Service;

use c975L\ConfigBundle\Service\VaultEncryptor;
use c975L\PilotBundle\Entity\Credit;
use c975L\PilotBundle\Repository\CreditRepository;
use c975L\PilotBundle\Service\Credit\CreditBalance;
use c975L\PilotBundle\Service\Credit\CreditReaderInterface;
use c975L\PilotBundle\Service\CreditRefresher;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Clock\MockClock;

class CreditRefresherTest extends TestCase
{
    private VaultEncryptor $vaultEncryptor;

    protected function setUp(): void
    {
        $this->vaultEncryptor = new VaultEncryptor('test-vault-key');
    }

    // A reader that hands back what it was given, or fails on the key "down", over the credits given
    /** @param list<Credit> $credits */
    private function refresher(array $credits = []): CreditRefresher
    {
        $reader = new class implements CreditReaderInterface {
            public function providers(): array
            {
                return ['catalog' => 'label.catalog'];
            }

            public function read(string $provider, string $apiKey, ?string $account): CreditBalance
            {
                return 'down' === $apiKey ? throw new \RuntimeException('Service down') : new CreditBalance(\strlen($apiKey), 100);
            }
        };

        $repository = $this->createStub(CreditRepository::class);
        $repository->method('findOrdered')->willReturn($credits);
        $repository->method('findBy')->willReturn($credits);

        return new CreditRefresher([$reader], $this->vaultEncryptor, new MockClock('2026-11-20 10:00'), new NullLogger(), $repository);
    }

    // The services offered are the readers'
    public function testProviders(): void
    {
        $this->assertSame(['catalog' => 'label.catalog'], $this->refresher()->providers());
    }

    // The key is decrypted before the reader sees it, and the balance read is dated
    public function testReadsWithTheDecryptedKey(): void
    {
        $credit = new Credit('item-1')->setProvider('catalog')->setApiKey($this->vaultEncryptor->encrypt('key-12'));

        $this->assertTrue($this->refresher()->refresh($credit));
        $this->assertSame([6, 100, '2026-11-20'], [$credit->getRemaining(), $credit->getTotal(), $credit->getUpdatedAt()?->format('Y-m-d')]);
    }

    // A service down, an unknown service, no key or a balance typed in by hand: nothing read, the last balance kept
    public function testKeepsTheBalanceWhenNotRead(): void
    {
        $refresher = $this->refresher();
        $down = new Credit('item-1')->setProvider('catalog')->setApiKey($this->vaultEncryptor->encrypt('down'))->setRemaining(3);
        $unknown = new Credit('item-2')->setProvider('other')->setApiKey($this->vaultEncryptor->encrypt('key'));

        $this->assertFalse($refresher->refresh($down));
        $this->assertSame(3, $down->getRemaining());
        $this->assertFalse($refresher->refresh($unknown));
        $this->assertFalse($refresher->refresh(new Credit('item-3')->setProvider('catalog')));
        $this->assertFalse($refresher->refresh(new Credit('item-4')));
    }

    // Every credit naming a service read again, the ones that failed named, a credit typed in by hand left alone
    public function testRefreshAll(): void
    {
        $read = new Credit('item-1')->setProvider('catalog')->setApiKey($this->vaultEncryptor->encrypt('key-12'));
        $down = new Credit('item-2')->setProvider('catalog')->setApiKey($this->vaultEncryptor->encrypt('down'));
        $typed = new Credit('item-3')->setRemaining(5);

        $this->assertSame(['item-2'], $this->refresher([$read, $down, $typed])->refreshAll());
        $this->assertSame([6, 5], [$read->getRemaining(), $typed->getRemaining()]);
    }

    // What a run spent is handed to the repository's atomic debit, dated by the clock
    public function testSpend(): void
    {
        $repository = $this->createMock(CreditRepository::class);
        $repository->expects($this->once())->method('spend')->with(['item-1' => 30], new \DateTimeImmutable('2026-11-20 10:00'));

        new CreditRefresher([], $this->vaultEncryptor, new MockClock('2026-11-20 10:00'), new NullLogger(), $repository)->spend(['item-1' => 30]);
    }
}
