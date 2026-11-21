<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Service\Credit;

use c975L\PilotBundle\Service\Credit\DIdReader;
use c975L\PilotBundle\Service\Credit\ElevenLabsReader;
use c975L\PilotBundle\Service\Credit\ExpoReader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

class CreditReadersTest extends TestCase
{
    // ElevenLabs: the limit less the characters spent, the key in its own header, renewed on the reset
    public function testElevenLabs(): void
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): JsonMockResponse {
            $this->assertContains('xi-api-key: key-1', $options['headers']);

            return new JsonMockResponse(['character_limit' => 10000, 'character_count' => 2419, 'next_character_count_reset_unix' => 1764547200]);
        });
        $balance = new ElevenLabsReader($client)->read('elevenlabs', 'key-1', null);

        $this->assertSame([7581, 10000, '2025-12-01'], [$balance->remaining, $balance->total, $balance->renewsOn?->format('Y-m-d')]);
    }

    // D-ID: its remaining and total, renewed when the first batch expires
    public function testDId(): void
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): JsonMockResponse {
            $this->assertContains('Authorization: Basic key-1', $options['headers']);

            return new JsonMockResponse(['remaining' => 40, 'total' => 64, 'credits' => [['expire_at' => '2026-12-17T00:00:00.000Z']]]);
        });
        $balance = new DIdReader($client)->read('d-id', 'key-1', null);

        $this->assertSame([40, 64, '2026-12-17'], [$balance->remaining, $balance->total, $balance->renewsOn?->format('Y-m-d')]);
    }

    // An answer without its counts is refused rather than read as an empty balance
    public function testIncompleteAnswersAreRefused(): void
    {
        foreach ([new ElevenLabsReader(new MockHttpClient(new JsonMockResponse(['tier' => 'free']))), new DIdReader(new MockHttpClient(new JsonMockResponse(['total' => 64])))] as $reader) {
            try {
                $reader->read('item-1', 'key-1', null);
                $this->fail('An incomplete answer is refused');
            } catch (\UnexpectedValueException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    // Expo: one platform's builds of the plan, for the account named, renewed at the end of the period
    public function testExpo(): void
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): JsonMockResponse {
            $this->assertContains('Authorization: Bearer token-1', $options['headers']);
            $this->assertSame('item-1', json_decode($options['body'], true)['variables']['name']);

            return new JsonMockResponse(['data' => ['account' => ['byName' => [
                'billingPeriod' => ['end' => '2026-12-01T00:00:00.000Z'],
                'usageMetrics' => ['builds' => ['planMetrics' => [
                    ['serviceMetric' => 'LOCAL_BUILDS', 'platformBreakdown' => null],
                    ['serviceMetric' => 'BUILDS', 'platformBreakdown' => ['ios' => ['value' => 7, 'limit' => 15], 'android' => ['value' => 0, 'limit' => 15]]],
                ]]],
            ]]]]);
        });
        $reader = new ExpoReader($client);

        $ios = $reader->read('expo-ios', 'token-1', 'item-1');
        $this->assertSame([8, 15, '2026-12-01'], [$ios->remaining, $ios->total, $ios->renewsOn?->format('Y-m-d')]);
        $this->assertSame(15, $reader->read('expo-android', 'token-1', 'item-1')->remaining);
    }

    // Expo: an account unknown to the token is refused, as an account left empty
    public function testExpoWithoutAccount(): void
    {
        $reader = new ExpoReader(new MockHttpClient(new JsonMockResponse(['data' => ['account' => ['byName' => null]], 'errors' => [['message' => 'Account not found']]])));

        try {
            $reader->read('expo-ios', 'token-1', 'item-1');
            $this->fail('An unknown account is refused');
        } catch (\RuntimeException $error) {
            $this->assertSame('Account not found', $error->getMessage());
        }
        $this->expectException(\InvalidArgumentException::class);
        $reader->read('expo-ios', 'token-1', null);
    }
}
