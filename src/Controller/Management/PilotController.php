<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Controller\Management;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\PilotBundle\Entity\Card;
use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Repository\CardRepository;
use c975L\PilotBundle\Repository\DocRepository;
use c975L\PilotBundle\Service\Planning;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Psr\Clock\ClockInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

// The three ways in: this week, a project with its backlog, a search; plus the page of a card and the gestures on it
class PilotController extends AbstractController
{
    // EasyAdmin prefixes the names with the Dashboard's, which gives management_pilot_XXX
    public const string WEEK_ROUTE = 'management_pilot_week';
    public const string PROJECT_ROUTE = 'management_pilot_project';
    public const string CARD_ROUTE = 'management_pilot_card';
    public const string SEARCH_ROUTE = 'management_pilot_search';
    public const string GESTURE_ROUTE = 'management_pilot_card_gesture';

    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly EntityManagerInterface $entityManager,
        private readonly CardRepository $cardRepository,
        private readonly ClockInterface $clock,
    ) {
    }

    // Late cards, then the coming days up to the horizon
    #[AdminRoute(path: '/pilot', name: 'pilot_week')]
    public function week(Planning $planning): Response
    {
        $this->denyAccess();

        return $this->render('@c975LPilot/management/week.html.twig', $planning->build());
    }

    // A project: its open cards (dated, then the backlog), its files, its done cards folded
    #[AdminRoute(path: '/pilot/project/{slug}', name: 'pilot_project')]
    public function project(#[MapEntity(mapping: ['slug' => 'slug'])] Project $project, DocRepository $docRepository): Response
    {
        $this->denyAccess();

        return $this->render('@c975LPilot/management/project.html.twig', [
            'project' => $project,
            'cards' => $this->cardRepository->findOpen($project),
            'docs' => $docRepository->findBy(['project' => $project], ['title' => 'ASC']),
            'done' => $this->cardRepository->findDone($project),
        ]);
    }

    // Cards and files holding the words, from two letters
    #[AdminRoute(path: '/pilot/search', name: 'pilot_search')]
    public function search(Request $request, DocRepository $docRepository): Response
    {
        $this->denyAccess();
        $words = trim($request->query->getString('q'));
        $searched = mb_strlen($words) >= 2;

        return $this->render('@c975LPilot/management/search.html.twig', [
            'words' => $words,
            'cards' => $searched ? $this->cardRepository->search($words) : [],
            'docs' => $searched ? $docRepository->search($words) : [],
        ]);
    }

    // Everything about a card: note, checklist, file, runs
    #[AdminRoute(path: '/pilot/card/{id}', name: 'pilot_card', options: ['requirements' => ['id' => '\d+']])]
    public function card(Card $card): Response
    {
        $this->denyAccess();

        return $this->render('@c975LPilot/management/card.html.twig', ['card' => $card]);
    }

    // Done, back to do, or a date (empty sends it back to the backlog); then back where the gesture came from
    #[AdminRoute(path: '/pilot/card/{id}/{gesture}', name: 'pilot_card_gesture', options: ['methods' => ['POST'], 'requirements' => ['id' => '\d+', 'gesture' => 'done|reopen|schedule']])]
    public function gesture(Card $card, string $gesture, Request $request): RedirectResponse
    {
        $this->denyAccess();
        if ($this->isCsrfTokenValid(self::GESTURE_ROUTE . $card->getId(), $request->request->getString('_token'))) {
            match ($gesture) {
                'done' => $card->markDone($this->clock->now()->setTime(0, 0)),
                'reopen' => $card->markDone(null),
                default => $card->setDue($this->day($request->request->getString('due'))),
            };
            $this->entityManager->flush();
        }

        return $this->redirect($this->back($request, $card));
    }

    // The back-office bar this whole screen sits behind
    private function denyAccess(): void
    {
        $this->denyAccessUnlessGranted((string) $this->configService->get('site-role-admin'));
    }

    // A posted date, null for an empty or unreadable one
    private function day(string $value): ?\DateTimeImmutable
    {
        $day = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return false === $day ? null : $day;
    }

    // The page the gesture was made on when it is one of ours, the card's page otherwise
    private function back(Request $request, Card $card): string
    {
        $referer = (string) $request->headers->get('referer');
        $host = $request->getSchemeAndHttpHost();

        return str_starts_with($referer, $host . '/') ? $referer : $this->generateUrl(self::CARD_ROUTE, ['id' => $card->getId()]);
    }
}
