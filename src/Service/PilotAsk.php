<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Service;

use c975L\PilotBundle\Repository\CardRepository;
use c975L\PilotBundle\Repository\DocRepository;
use c975L\PilotBundle\Security\PilotScope;
use c975L\UiBundle\Repository\AiSearchChunkRepository;
use c975L\UiBundle\Service\AiExcerptReply;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// Answers a question about the piloting from the cards and files the account sees, read when it is asked: nothing is indexed nor kept, a private card never reaching the site's public search nor another account's answer. The model is only called with excerpts in hand, and the links shown come from them alone
class PilotAsk
{
    public const int MIN_LENGTH = 3;
    public const int MAX_LENGTH = 300;

    // Excerpts sent with a question, the closest first
    private const int EXCERPTS = 8;

    // Characters kept of each card or file, about 400 tokens
    private const int EXCERPT_LENGTH = 1600;

    public function __construct(
        private readonly PilotAskClient $client,
        private readonly CardRepository $cardRepository,
        private readonly DocRepository $docRepository,
        private readonly CardMarkdown $cardMarkdown,
        private readonly PilotUrls $pilotUrls,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    // On as soon as the back office's key is filled in
    public function isEnabled(): bool
    {
        return $this->client->isEnabled();
    }

    // The answer in the shape the back office's assistant reads (UiBundle's ai-assistant.js), "nothing found" said in words; null when off, the question too short or the provider failed, which the reader is told apart
    /** @return array{answer: string, sources: list<array{url: string, label: string}>}|null */
    public function ask(string $question, PilotScope $scope, string $locale): ?array
    {
        $question = mb_substr(trim((string) preg_replace('/\s+/u', ' ', $question)), 0, self::MAX_LENGTH);
        if (!$this->client->isEnabled() || mb_strlen($question) < self::MIN_LENGTH) {
            return null;
        }

        $excerpts = $this->excerpts(self::stems($question), $scope);
        $reply = [] === $excerpts ? '' : $this->client->answer($question, $excerpts, $locale);
        if (null === $reply) {
            return null;
        }
        [$answer, $sources] = '' === $reply ? ['', []] : AiExcerptReply::parse($reply, $excerpts);

        return [
            'answer' => '' === $answer ? $this->translator->trans('label.ask_not_found', [], 'pilot') : $answer,
            'sources' => array_map(static fn (array $source): array => ['url' => $source['url'], 'label' => $source['title']], $sources),
        ];
    }

    // The question's stems as the site search cuts them, without their prefix mark
    /** @return list<string> */
    public static function stems(string $question): array
    {
        $query = AiSearchChunkRepository::booleanQuery($question);

        return '' === $query ? [] : array_map(static fn (string $term): string => rtrim($term, '*'), explode(' ', $query));
    }

    // The cards and files holding the most stems, a card before a file holding as many
    /**
     * @param list<string> $stems
     *
     * @return list<array{url: string, title: string, content: string}>
     */
    private function excerpts(array $stems, PilotScope $scope): array
    {
        $found = [];
        foreach ($this->cardRepository->findHolding($stems, $scope) as $card) {
            $found[] = $this->excerpt($this->pilotUrls->inSpace($card), $card->getTitle(), $this->cardMarkdown->write($card), $stems, 1);
        }
        foreach ($this->docRepository->findHolding($stems, $scope) as $doc) {
            $found[] = $this->excerpt($this->urlGenerator->generate('pilot_doc', ['slug' => $doc->getSlug()]), $doc->getTitle(), $doc->toMarkdown(), $stems, 0);
        }
        usort($found, static fn (array $one, array $other): int => [$other['score'], $other['rank']] <=> [$one['score'], $one['rank']]);

        return array_map(static fn (array $item): array => ['url' => $item['url'], 'title' => $item['title'], 'content' => $item['content']], \array_slice($found, 0, self::EXCERPTS));
    }

    // An excerpt scored by the stems its text holds
    /**
     * @param list<string> $stems
     *
     * @return array{url: string, title: string, content: string, score: int, rank: int}
     */
    private function excerpt(string $url, string $title, string $text, array $stems, int $rank): array
    {
        $lower = mb_strtolower($text);
        $hits = array_values(array_filter(array_map(static fn (string $stem): int | false => mb_strpos($lower, $stem), $stems), \is_int(...)));

        return [
            'url' => $url,
            'title' => $title,
            'content' => self::window($text, [] === $hits ? 0 : min($hits)),
            'score' => \count($hits),
            'rank' => $rank,
        ];
    }

    // The text around its first hit, its heading kept: a long file's matching passage sent rather than its start
    private static function window(string $text, int $hit): string
    {
        if (mb_strlen($text) <= self::EXCERPT_LENGTH) {
            return $text;
        }
        $heading = strtok($text, "\n") . "\n…\n";
        $start = max(0, min($hit - intdiv(self::EXCERPT_LENGTH, 4), mb_strlen($text) - self::EXCERPT_LENGTH));

        return (0 === $start ? '' : $heading) . mb_substr($text, $start, self::EXCERPT_LENGTH);
    }
}
