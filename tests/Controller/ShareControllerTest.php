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
use c975L\PilotBundle\Entity\PlanTemplate;
use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Repository\CardRepository;
use c975L\PilotBundle\Repository\DocRepository;
use c975L\PilotBundle\Service\ActionOffer;
use c975L\PilotBundle\Service\CardFlow;
use c975L\PilotBundle\Service\CardImages;
use c975L\PilotBundle\Service\CardMarkdown;
use c975L\PilotBundle\Service\StepOrder;
use c975L\PilotBundle\Tests\Fixtures\PilotTestStubs;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class ShareControllerTest extends TestCase
{
    use PilotTestStubs;

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
            $this->assertSame('no-referrer', $response->headers->get('Referrer-Policy'));
            $this->assertStringContainsString("img-src 'self' data:", (string) $response->headers->get('Content-Security-Policy'));
        }
    }

    // The page never hands its secret on as a referrer, even where the headers are dropped
    public function testPageSendsNoReferrer(): void
    {
        $this->assertStringContainsString('<meta name="referrer" content="no-referrer">', (string) file_get_contents(\dirname(__DIR__, 2) . '/templates/share/card.html.twig'));
    }

    // An editing link past its ceiling of writes is answered 429, the card left as it was
    public function testEditLinkWritesAreLimited(): void
    {
        $controller = $this->controller(1);
        $token = (string) $this->card->getEditToken();

        $this->assertSame(Response::HTTP_NO_CONTENT, $controller->gesture($token, 'feedback', new Request(request: ['text' => 'feedback-1']))->getStatusCode());
        $this->assertSame(Response::HTTP_TOO_MANY_REQUESTS, $controller->save($token, new Request(content: "# item-2\n"))->getStatusCode());
        $this->assertSame(Response::HTTP_TOO_MANY_REQUESTS, $controller->gesture($token, 'feedback', new Request(request: ['text' => 'feedback-2']))->getStatusCode());
        $this->assertSame('item-1', $this->card->getTitle());
        $this->assertCount(1, $this->card->getFeedback());
    }

    // The page's own form past the ceiling shows the page again with the reason, answered 429
    public function testThrottledFormShowsThePage(): void
    {
        $controller = $this->controller(1);
        $controller->gesture((string) $this->card->getEditToken(), 'feedback', new Request(request: ['text' => 'feedback-1']));
        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturnCallback(static fn (string $template, array $context): string => (string) $context['refusal']?->getMessage());
        $container = new Container();
        $container->set('twig', $twig);
        $controller->setContainer($container);

        $response = $controller->save((string) $this->card->getEditToken(), new Request(request: ['markdown' => "# item-2\n"]));

        $this->assertSame(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());
        $this->assertSame('flow.share_throttled', $response->getContent());
        $this->assertSame('no-referrer', $response->headers->get('Referrer-Policy'));
        $this->assertSame('item-1', $this->card->getTitle());
    }

    // A card whose checklist is full takes no more feedback through its link
    public function testEditLinkRefusesAFullChecklist(): void
    {
        $this->card->setChecklist(array_fill(0, Card::MAX_CHECKLIST, ['t' => 'step-1']));

        $response = $this->controller()->gesture((string) $this->card->getEditToken(), 'feedback', new Request(request: ['text' => 'feedback-1']));

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertCount(Card::MAX_CHECKLIST, $this->card->getChecklist());
    }

    // The editing link rewrites the card from a markdown body, answered 204
    public function testEditLinkSaves(): void
    {
        $response = $this->controller()->save((string) $this->card->getEditToken(), new Request(content: "# item-2\n"));

        $this->assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        $this->assertSame('item-2', $this->card->getTitle());
    }

    // A step added through the editing link to a card following a checklist template is refused with its reason, nothing saved
    public function testEditLinkRefusesAStepOutOfTheTemplate(): void
    {
        $this->card->follow(new PlanTemplate('catalog', [['title' => 'step-1', 'executor' => 'human']])->setChecklist(true));

        $response = $this->controller()->save((string) $this->card->getEditToken(), new Request(content: "## Steps\n\n- [ ] step-1\n- [ ] step-2\n- [ ] step-9\n"));

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $this->assertSame('flow.template_steps', $response->getContent());
        $this->assertSame(['step-1', 'step-2'], array_column($this->card->getChecklist(), 't'));
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

    // The editing link adds a feedback, a « ? » first now part of its text
    public function testEditLinkAddsFeedback(): void
    {
        $this->controller()->gesture((string) $this->card->getEditToken(), 'feedback', new Request(request: ['text' => '? feedback-1']));

        $this->assertSame([2 => ['t' => '? feedback-1', 'feedback' => true]], $this->card->getFeedback());
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

    private function controller(int $writes = 300): ShareController
    {
        $cards = $this->createStub(CardRepository::class);
        $cards->method('findOneBy')->willReturnCallback(fn (array $criteria): ?Card => match (true) {
            ($criteria['editToken'] ?? null) === $this->card->getEditToken() => $this->card,
            ($criteria['shareToken'] ?? null) === $this->card->getShareToken() => $this->card,
            default => null,
        });

        return new ShareController($cards, new CardMarkdown($this->createStub(DocRepository::class), $this->urls()), $this->createStub(EntityManagerInterface::class), new MockClock(), new CardFlow(new MockClock()), $this->offer, new StepOrder($cards, new MockClock()), new CardImages(sys_get_temp_dir()), $this->translator(), new RateLimiterFactory(['id' => ShareController::WRITE_LIMITER, 'policy' => 'fixed_window', 'limit' => $writes, 'interval' => '1 hour'], new InMemoryStorage()));
    }

    // A translator handing back the key it was given
    private function translator(): TranslatorInterface
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return $translator;
    }
}
