<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Service\Credit;

use Symfony\Contracts\HttpClient\HttpClientInterface;

// D-ID credits left, the key being the one its studio gives for Basic authentication
class DIdReader implements CreditReaderInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function providers(): array
    {
        return ['d-id' => 'label.provider_d_id'];
    }

    // The remaining and total credits, renewed when the first batch expires
    public function read(string $provider, string $apiKey, ?string $account): CreditBalance
    {
        $data = $this->httpClient->request('GET', 'https://api.d-id.com/credits', [
            'headers' => ['Authorization' => 'Basic ' . $apiKey],
            'timeout' => 15,
        ])->toArray();
        // An answer without what remains is no balance, the last one staying
        if (!\is_int($data['remaining'] ?? null)) {
            throw new \UnexpectedValueException('No remaining credits in the D-ID answer.');
        }
        $expire = $data['credits'][0]['expire_at'] ?? null;

        return new CreditBalance(
            max(0, $data['remaining']),
            isset($data['total']) ? (int) $data['total'] : null,
            \is_string($expire) ? new \DateTimeImmutable($expire) : null,
        );
    }
}
