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

// ElevenLabs characters left in the month, one credit per account and its key
class ElevenLabsReader implements CreditReaderInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function providers(): array
    {
        return ['elevenlabs' => 'label.provider_elevenlabs'];
    }

    // The subscription's limit less what was spent, renewed on its reset
    public function read(string $provider, string $apiKey, ?string $account): CreditBalance
    {
        $data = $this->httpClient->request('GET', 'https://api.elevenlabs.io/v1/user/subscription', [
            'headers' => ['xi-api-key' => $apiKey],
            'timeout' => 15,
        ])->toArray();
        // An answer without its counts is no balance, the last one staying
        if (!\is_int($data['character_limit'] ?? null) || !\is_int($data['character_count'] ?? null)) {
            throw new \UnexpectedValueException('No character count in the ElevenLabs answer.');
        }
        $limit = $data['character_limit'];
        $reset = $data['next_character_count_reset_unix'] ?? null;

        return new CreditBalance(
            max(0, $limit - $data['character_count']),
            $limit,
            \is_int($reset) ? new \DateTimeImmutable('@' . $reset) : null,
        );
    }
}
