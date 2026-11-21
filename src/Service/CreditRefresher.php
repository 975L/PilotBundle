<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Service;

use c975L\ConfigBundle\Service\VaultEncryptor;
use c975L\PilotBundle\Entity\Credit;
use c975L\PilotBundle\Repository\CreditRepository;
use c975L\PilotBundle\Service\Credit\CreditReaderInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

// Reads the balances of the credits naming a service, each with its own key, one that cannot be read keeping its last balance; takes off what a run spent on a credit typed in by hand
class CreditRefresher
{
    /** @var array<string, CreditReaderInterface> */
    private array $readers = [];

    /** @var array<string, string> */
    private array $providers = [];

    /** @param iterable<CreditReaderInterface> $readers */
    public function __construct(
        #[AutowireIterator(CreditReaderInterface::TAG)]
        iterable $readers,
        private readonly VaultEncryptor $vaultEncryptor,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
        private readonly CreditRepository $creditRepository,
    ) {
        foreach ($readers as $reader) {
            foreach ($reader->providers() as $provider => $label) {
                $this->readers[$provider] = $reader;
                $this->providers[$provider] = $label;
            }
        }
    }

    // The services offered on a credit, their label in the pilot domain
    /** @return array<string, string> provider => label */
    public function providers(): array
    {
        return $this->providers;
    }

    // The credit's balance read again, false when it names no service or the service could not be read
    public function refresh(Credit $credit): bool
    {
        $reader = $this->readers[$credit->getProvider() ?? ''] ?? null;
        if (null === $reader || null === $credit->getApiKey()) {
            return false;
        }

        // An unreadable key, a service down or an answer it does not expect: the last balance stays, the reason goes to the log without the key
        try {
            $balance = $reader->read((string) $credit->getProvider(), $this->vaultEncryptor->decrypt($credit->getApiKey()), $credit->getAccount());
        } catch (\Throwable $error) {
            $this->logger->warning('Pilot credit "{key}" not read: {reason}', ['key' => $credit->getKey(), 'reason' => $error->getMessage()]);

            return false;
        }
        $credit->read($balance, $this->clock->now());

        return true;
    }

    // Every credit naming a service read again, giving the keys of those that could not be read
    /** @return list<string> */
    public function refreshAll(): array
    {
        $unread = [];
        foreach ($this->creditRepository->findOrdered() as $credit) {
            if (!$credit->isManual() && !$this->refresh($credit)) {
                $unread[] = $credit->getKey();
            }
        }

        return $unread;
    }

    // What a run spent, by credit key: taken off a balance typed in by hand in one atomic update, a service's balance left to the morning refresh and the dashboard's button so a run's end never waits on a service; an unknown key is ignored
    /** @param array<string, int> $spent */
    public function spend(array $spent): void
    {
        $this->creditRepository->spend($spent, $this->clock->now());
    }
}
