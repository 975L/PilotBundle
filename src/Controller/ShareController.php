<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Controller;

use c975L\PilotBundle\Entity\Card;
use c975L\PilotBundle\Entity\Run;
use c975L\PilotBundle\Exception\FlowException;
use c975L\PilotBundle\Repository\CardRepository;
use c975L\PilotBundle\Service\ActionOffer;
use c975L\PilotBundle\Service\CardFlow;
use c975L\PilotBundle\Service\CardImages;
use c975L\PilotBundle\Service\CardMarkdown;
use c975L\PilotBundle\Service\StepOrder;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

use function Symfony\Component\Translation\t;

// A card reached without signing in by the secret of its link, the secret being the permission: the read-only one shows it, the editing one also rewrites it, nothing of the back office either way
class ShareController extends AbstractController
{
    public const string ROUTE = 'pilot_share';

    public const string MARKDOWN_ROUTE = 'pilot_share_markdown';

    private const string TOKEN = '[0-9a-f]{32}';

    public function __construct(
        private readonly CardRepository $cardRepository,
        private readonly CardMarkdown $cardMarkdown,
        private readonly EntityManagerInterface $entityManager,
        private readonly ClockInterface $clock,
        private readonly CardFlow $cardFlow,
        private readonly ActionOffer $actionOffer,
        private readonly StepOrder $stepOrder,
        private readonly CardImages $cardImages,
    ) {
    }

    // The card as a page, with its markdown to rewrite when the link is the editing one
    #[Route('/pilot/share/{token}', name: self::ROUTE, requirements: ['token' => self::TOKEN], methods: ['GET'])]
    public function card(string $token): Response
    {
        [$card, $editable] = $this->find($token);

        return $this->unlisted($this->render('@c975LPilot/share/card.html.twig', [
            'card' => $card,
            'editable' => $editable,
            'token' => $token,
            'markdown' => $editable ? $this->cardMarkdown->write($card) : null,
            'run' => $card->lastRun(),
            'action_labels' => $editable ? $this->actionOffer->labels() : [],
            'actions' => $editable ? array_filter($this->actionOffer->on($card->getProject()), static fn (array $action): bool => [] === $action['params']) : [],
        ]));
    }

    // The card as one markdown text, for whoever works on it outside the page
    #[Route('/pilot/share/{token}.md', name: self::MARKDOWN_ROUTE, requirements: ['token' => self::TOKEN], methods: ['GET'])]
    public function markdown(string $token): Response
    {
        [$card] = $this->find($token);

        return $this->unlisted(new Response($this->cardMarkdown->write($card), Response::HTTP_OK, ['Content-Type' => 'text/markdown; charset=UTF-8']));
    }

    // The card rewritten through its editing link, steps only moved around carried to its template like a drag, the screenshots of the feedback it no longer holds removed: the page's form back to the page, a markdown body sent as such answered 204
    #[Route('/pilot/share/{token}', name: 'pilot_share_save', requirements: ['token' => self::TOKEN], methods: ['POST'])]
    public function save(string $token, Request $request): Response
    {
        [$card, $editable] = $this->find($token);
        $form = $request->request->has('markdown');
        if (!$editable) {
            throw new NotFoundHttpException();
        }
        $before = $card->getChecklist();
        $images = $card->getFeedbackImages();
        $this->cardMarkdown->read($card, $form ? $request->request->getString('markdown') : $request->getContent(), $this->clock->now());
        $this->stepOrder->follow($card, $before);
        $this->entityManager->flush();
        $this->cardImages->forget(array_values(array_diff($images, $card->getFeedbackImages())));

        return $form ? $this->redirectToRoute(self::ROUTE, ['token' => $token], Response::HTTP_SEE_OTHER) : $this->unlisted(new Response(null, Response::HTTP_NO_CONTENT));
    }

    // A gesture through the editing link: a step ticked or moved up (carried to its template), a feedback added, the machine action a step names queued or withdrawn, or the card handed to an action offered on its project asking nothing; the page's form back to the page, any other call answered 204, a refusal 422
    #[Route('/pilot/share/{token}/{gesture}', name: 'pilot_share_gesture', requirements: ['token' => self::TOKEN, 'gesture' => 'step|stepup|feedback|runstep|cancel|entrust'], methods: ['POST'])]
    public function gesture(string $token, string $gesture, Request $request): Response
    {
        [$card, $editable] = $this->find($token);
        if (!$editable) {
            throw new NotFoundHttpException();
        }
        $refusal = null;
        try {
            $run = $this->apply($card, $gesture, $request);
            if ($run instanceof Run) {
                $this->entityManager->persist($run);
            }
            $this->entityManager->flush();
        } catch (FlowException) {
            $refusal = Response::HTTP_UNPROCESSABLE_ENTITY;
        }
        if ($request->request->has('page')) {
            return $this->redirectToRoute(self::ROUTE, ['token' => $token], Response::HTTP_SEE_OTHER);
        }

        return $this->unlisted(new Response(null, $refusal ?? Response::HTTP_NO_CONTENT));
    }

    // The gesture itself, an action that is not offered right now refused like a gesture out of turn
    private function apply(Card $card, string $gesture, Request $request): ?Run
    {
        $index = $request->request->getInt('index', -1);
        if ('step' === $gesture) {
            $this->cardFlow->tick($card, $index);

            return null;
        }
        if ('stepup' === $gesture) {
            $this->stepOrder->up($card, $index);

            return null;
        }
        if ('feedback' === $gesture) {
            $card->addFeedback($request->request->getString('text'));

            return null;
        }
        if ('runstep' === $gesture) {
            return $this->cardFlow->runStep($card, $index);
        }
        if ('cancel' === $gesture) {
            return $this->cardFlow->cancel($card, $request->request->getInt('run'));
        }
        $action = $request->request->getString('action');
        if (!\in_array($action, $this->actionOffer->directKeys($card->getProject()), true)) {
            throw new FlowException(t('flow.not_now', [], 'pilot'));
        }

        return $this->cardFlow->entrust($card, $action, []);
    }

    // The card under this secret and whether it lets edit it, a 404 for any other secret
    /** @return array{Card, bool} */
    private function find(string $token): array
    {
        $card = $this->cardRepository->findOneBy(['editToken' => $token]);
        if (null !== $card) {
            return [$card, true];
        }

        return [$this->cardRepository->findOneBy(['shareToken' => $token]) ?? throw new NotFoundHttpException(), false];
    }

    // Never indexed nor kept in a cache
    private function unlisted(Response $response): Response
    {
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
