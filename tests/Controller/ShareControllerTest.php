<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Controller;

use c975L\PilotBundle\Controller\ShareController;
use c975L\PilotBundle\Entity\Card;
use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Repository\CardRepository;
use c975L\PilotBundle\Repository\DocRepository;
use c975L\PilotBundle\Service\ActionOffer;
use c975L\PilotBundle\Service\CardFlow;
use c975L\PilotBundle\Service\CardMarkdown;
use c975L\PilotBundle\Service\StepOrder;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class ShareControllerTest extends TestCase
{
    private Card $card;

    private ActionOffer $offer;

    protected function setUp(): void
    {
        $this->card = new Card(new Project('catalog', 'Catalog'), 'item-1')->share()->lend()->setChecklist([['t' => 'step-1'], ['t' => 'step-2']]);
        $this->offer = $this->createStub(ActionOffer::class);
        $this->offer->method('directKeys')->willReturn(['build']);
    }

    // A secret no card holds answers a 404, saying nothing of the cards that exist
    public function testUnknownTokenIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->controller()->markdown(str_repeat('a', 32));
    }

    // Both links read the card in markdown, never indexed nor cached
    public function testMarkdownByEitherLink(): void
    {
        foreach ([$this->card->getShareToken(), $this->card->getEditToken()] as $token) {
            $response = $this->controller()->markdown((string) $token);
            $this->assertStringStartsWith('# item-1', (string) $response->getContent());
            $this->assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
            $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        }
    }

    // The editing link rewrites the card from a markdown body, answered 204
    public function testEditLinkSaves(): void
    {
        $response = $this->controller()->save((string) $this->card->getEditToken(), new Request(content: "# item-2\n"));

        $this->assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        $this->assertSame('item-2', $this->card->getTitle());
    }

    // The read-only link never writes
    public function testReadLinkDoesNotSave(): void
    {
        try {
            $this->controller()->save((string) $this->card->getShareToken(), new Request(content: "# item-2\n"));
            $this->fail('The read-only link saved the card.');
        } catch (NotFoundHttpException) {
            $this->assertSame('item-1', $this->card->getTitle());
        }
    }

    // The editing link ticks a step and hands the card to an action offered, answered 204
    public function testEditLinkGestures(): void
    {
        $token = (string) $this->card->getEditToken();

        $this->assertSame(Response::HTTP_NO_CONTENT, $this->controller()->gesture($token, 'step', new Request(request: ['index' => '0']))->getStatusCode());
        $this->assertTrue($this->card->getChecklist()[0]['done'] ?? false);
        $this->assertSame(Response::HTTP_NO_CONTENT, $this->controller()->gesture($token, 'entrust', new Request(request: ['action' => 'build']))->getStatusCode());
        $this->assertSame('build', $this->card->getAction());
    }

    // The editing link moves a step one place up
    public function testEditLinkMovesAStepUp(): void
    {
        $this->controller()->gesture((string) $this->card->getEditToken(), 'stepup', new Request(request: ['index' => '1']));

        $this->assertSame(['step-2', 'step-1'], array_column($this->card->getChecklist(), 't'));
    }

    // An action the project does not offer right now is refused, the card left as it was
    public function testActionNotOfferedIsRefused(): void
    {
        $response = $this->controller()->gesture((string) $this->card->getEditToken(), 'entrust', new Request(request: ['action' => 'other']));

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertNull($this->card->getAction());
    }

    // The read-only link never acts
    public function testReadLinkDoesNotAct(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->controller()->gesture((string) $this->card->getShareToken(), 'step', new Request(request: ['index' => '0']));
    }

    // Only a 32 hex secret reaches the controller, outside the back office
    public function testRoutes(): void
    {
        $paths = [];
        foreach (['card', 'markdown', 'save', 'gesture'] as $method) {
            $route = new \ReflectionMethod(ShareController::class, $method)->getAttributes(Route::class)[0]->newInstance();
            $this->assertSame('[0-9a-f]{32}', $route->requirements['token']);
            $paths[$method] = $route->path . ' ' . implode(',', $route->methods);
        }

        $this->assertSame(['card' => '/pilot/share/{token} GET', 'markdown' => '/pilot/share/{token}.md GET', 'save' => '/pilot/share/{token} POST', 'gesture' => '/pilot/share/{token}/{gesture} POST'], $paths);
    }

    private function controller(): ShareController
    {
        $cards = $this->createStub(CardRepository::class);
        $cards->method('findOneBy')->willReturnCallback(fn (array $criteria): ?Card => match (true) {
            ($criteria['editToken'] ?? null) === $this->card->getEditToken() => $this->card,
            ($criteria['shareToken'] ?? null) === $this->card->getShareToken() => $this->card,
            default => null,
        });

        return new ShareController($cards, new CardMarkdown($this->createStub(DocRepository::class)), $this->createStub(EntityManagerInterface::class), new MockClock(), new CardFlow(new MockClock()), $this->offer, new StepOrder($cards, new MockClock()));
    }
}
