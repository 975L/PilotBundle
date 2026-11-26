<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Service;

use c975L\PilotBundle\Entity\Card;
use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Repository\CardRepository;
use c975L\PilotBundle\Repository\DocRepository;
use c975L\PilotBundle\Security\PilotScope;
use c975L\PilotBundle\Service\CardMarkdown;
use c975L\PilotBundle\Service\PilotAsk;
use c975L\PilotBundle\Service\PilotAskClient;
use c975L\PilotBundle\Service\PilotUrls;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// Donovan answers from the cards and files the account sees, the model called only with excerpts in hand
class PilotAskTest extends TestCase
{
    // Off, or a question too short, asks nothing
    public function testDisabledOrShortAsksNothing(): void
    {
        $client = $this->createMock(PilotAskClient::class);
        $client->method('isEnabled')->willReturnOnConsecutiveCalls(false, true);
        $client->expects($this->never())->method('answer');
        $ask = $this->ask($client, []);

        $this->assertNull($ask->ask('catalog layout', new PilotScope(true), 'fr'));
        $this->assertNull($ask->ask('ab', new PilotScope(true), 'fr'));
    }

    // Nothing holding the question's words is "not found", said in words, without calling the model
    public function testNothingFoundCallsNoModel(): void
    {
        $client = $this->createMock(PilotAskClient::class);
        $client->method('isEnabled')->willReturn(true);
        $client->expects($this->never())->method('answer');

        $this->assertSame(['answer' => 'label.ask_not_found', 'sources' => []], $this->ask($client, [])->ask('catalog layout', new PilotScope(true), 'fr'));
    }

    // The scope reaches the lookup, the card holding most of the words comes first, and the links are the cards' own
    public function testAnswersFromTheCardsInScope(): void
    {
        $project = new Project('catalog', 'Catalog');
        $one = new Card($project, 'item-1')->setNote('catalog');
        $two = new Card($project, 'item-2')->setNote('catalog layout');
        $scope = new PilotScope(false, [3]);
        $client = $this->createMock(PilotAskClient::class);
        $client->method('isEnabled')->willReturn(true);
        $client->expects($this->once())->method('answer')
            ->with('Catalog layout?', $this->callback(static fn (array $excerpts): bool => ['item-2', 'item-1'] === array_column($excerpts, 'title')), 'fr')
            ->willReturn('{"answer": "See item-2.", "sources": [1, 7]}');

        $result = $this->ask($client, [$one, $two], $scope)->ask(' Catalog   layout? ', $scope, 'fr');

        $this->assertSame(['answer' => 'See item-2.', 'sources' => [['url' => '/card/item-2', 'label' => 'item-2']]], $result);
    }

    // A provider failure is told apart from "nothing found"
    public function testFailureIsNull(): void
    {
        $client = $this->createStub(PilotAskClient::class);
        $client->method('isEnabled')->willReturn(true);
        $client->method('answer')->willReturn(null);

        $this->assertNull($this->ask($client, [new Card(new Project('catalog', 'Catalog'), 'catalog')])->ask('catalog', new PilotScope(true), 'fr'));
    }

    // A long text is sent around its first hit, its heading kept, rather than from its start
    public function testLongTextSentAroundItsHit(): void
    {
        $card = new Card(new Project('catalog', 'Catalog'), 'item-1')->setNote(str_repeat('filler ', 400) . 'the layout');
        $client = $this->createMock(PilotAskClient::class);
        $client->method('isEnabled')->willReturn(true);
        $client->expects($this->once())->method('answer')
            ->with('layout', $this->callback(static fn (array $excerpts): bool => str_starts_with($excerpts[0]['content'], "# item-1\n…\n") && str_contains($excerpts[0]['content'], 'the layout')), 'fr')
            ->willReturn('{"answer": "Yes.", "sources": [1]}');

        $this->ask($client, [$card])->ask('layout', new PilotScope(true), 'fr');
    }

    // The question cut into the stems the site search uses
    public function testStems(): void
    {
        $this->assertSame(['catal', 'layou'], PilotAsk::stems('The catalog layout?'));
        $this->assertSame([], PilotAsk::stems('a b'));
    }

    // The service on repositories holding the cards given, the scope checked to be the one asked with
    /** @param list<Card> $cards */
    private function ask(PilotAskClient $client, array $cards, ?PilotScope $scope = null): PilotAsk
    {
        $cardRepository = $this->createStub(CardRepository::class);
        $cardRepository->method('findHolding')->willReturnCallback(static fn (array $stems, PilotScope $given): array => null === $scope || $given === $scope ? $cards : []);
        $docRepository = $this->createStub(DocRepository::class);
        $docRepository->method('findHolding')->willReturn([]);
        $markdown = $this->createStub(CardMarkdown::class);
        $markdown->method('write')->willReturnCallback(static fn (Card $card): string => '# ' . $card->getTitle() . "\n\n" . $card->getNote());
        $urls = $this->createStub(PilotUrls::class);
        $urls->method('inSpace')->willReturnCallback(static fn (Card $card): string => '/card/' . $card->getTitle());

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new PilotAsk($client, $cardRepository, $docRepository, $markdown, $urls, $this->createStub(UrlGeneratorInterface::class), $translator);
    }
}
