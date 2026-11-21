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

// EAS builds left in the billing period of an Expo account, one credit per platform, read with an access token of expo.dev
class ExpoReader implements CreditReaderInterface
{
    // The plan's builds of the period and their split by platform, as `eas account:usage` asks for them
    private const string QUERY = 'query($name: String!, $date: DateTime!) { account { byName(accountName: $name) { billingPeriod(date: $date) { end } usageMetrics { builds: byBillingPeriod(date: $date, service: BUILDS) { planMetrics { serviceMetric platformBreakdown { ios { value limit } android { value limit } } } } } } } }';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function providers(): array
    {
        return ['expo-ios' => 'label.provider_expo_ios', 'expo-android' => 'label.provider_expo_android'];
    }

    // The platform's limit less its builds, renewed at the end of the period
    public function read(string $provider, string $apiKey, ?string $account): CreditBalance
    {
        if (null === $account) {
            throw new \InvalidArgumentException('An Expo credit names its account.');
        }
        $data = $this->httpClient->request('POST', 'https://api.expo.dev/graphql', [
            'auth_bearer' => $apiKey,
            'json' => ['query' => self::QUERY, 'variables' => ['name' => $account, 'date' => gmdate('Y-m-d\TH:i:s\Z')]],
            'timeout' => 15,
        ])->toArray();
        $found = $data['data']['account']['byName'] ?? throw new \RuntimeException($data['errors'][0]['message'] ?? 'Unknown Expo account.');
        $platform = 'expo-ios' === $provider ? 'ios' : 'android';
        $metric = array_find($found['usageMetrics']['builds']['planMetrics'] ?? [], static fn (array $metric): bool => 'BUILDS' === $metric['serviceMetric']);
        $usage = $metric['platformBreakdown'][$platform] ?? throw new \RuntimeException('No build plan on the Expo account.');
        $end = $found['billingPeriod']['end'] ?? null;

        return new CreditBalance(
            max(0, (int) $usage['limit'] - (int) $usage['value']),
            (int) $usage['limit'],
            \is_string($end) ? new \DateTimeImmutable($end) : null,
        );
    }
}
