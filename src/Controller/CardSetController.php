<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Controller;

use c975L\PilotBundle\Entity\CardSet;
use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Exception\FlowException;
use c975L\PilotBundle\Repository\CardRepository;
use c975L\PilotBundle\Repository\CardSetRepository;
use c975L\PilotBundle\Repository\PlanTemplateRepository;
use c975L\PilotBundle\Repository\ProjectRepository;
use c975L\PilotBundle\Security\PilotAccess;
use c975L\PilotBundle\Service\CardSetRemoval;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

// The collections of a space, set up by its owner on the space's page: one added by its name, renamed from its tab, set up with its word for a card, its template and its sheets, put in order by dragging the tabs, one deleted (its cards going to another collection of the space)
class CardSetController extends AbstractController
{
    use PilotPageTrait;

    public function __construct(
        private readonly PilotAccess $pilotAccess,
        private readonly ProjectRepository $projectRepository,
        private readonly CardRepository $cardRepository,
        private readonly CardSetRepository $cardSetRepository,
        private readonly PlanTemplateRepository $planTemplateRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
    ) {
    }

    // A collection added after the others, with the card dropped on its + if any, its tab then opened
    #[Route('/pilot/space/{slug}/collection', name: 'pilot_collection_new', methods: ['POST'])]
    public function new(#[MapEntity(mapping: ['slug' => 'slug'])] Project $project, Request $request): RedirectResponse
    {
        $this->guard($project, PilotAccess::MANAGE);
        $name = trim($request->request->getString('name'));
        if ('' === $name || !$this->isCsrfTokenValid('pilot_collection_new' . $project->getId(), $request->request->getString('_token'))) {
            return $this->back($project);
        }
        $cardSet = new CardSet($project, mb_substr($name, 0, 100))->setPosition(\count($this->cardSetRepository->findOfProject($project)));
        $this->entityManager->persist($cardSet);
        $this->fileDropped($project, $cardSet, $request->request->getInt('card'));
        $this->entityManager->flush();

        return $this->back($project, $cardSet);
    }

    // The card dragged onto the + filed in the new collection, one of another space ignored
    private function fileDropped(Project $project, CardSet $cardSet, int $id): void
    {
        $card = 0 < $id ? $this->cardRepository->find($id) : null;
        if (null !== $card && $card->getProject() === $project) {
            $card->setCardSet($cardSet);
        }
    }

    // Its name, its word for a card, its template among those offered to the space, and whether its cards show as sheets
    #[Route('/pilot/collection/{id}/edit', name: 'pilot_collection_edit', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function edit(CardSet $cardSet, Request $request): RedirectResponse
    {
        $project = $cardSet->getProject();
        $this->guard($project, PilotAccess::MANAGE);
        if ($this->isCsrfTokenValid('pilot_collection_edit' . $cardSet->getId(), $request->request->getString('_token'))) {
            $template = $this->planTemplateRepository->find((int) $request->request->getString('template'));
            $offered = null !== $template && \in_array($template, $this->planTemplateRepository->findOfferedTo($project), true);
            $cardSet->setName($request->request->getString('name'))->setItem($request->request->getString('item'))->setTemplate($offered ? $template : null)->setSheets($request->request->getBoolean('sheets'));
            $this->entityManager->flush();
        }

        return $this->back($project, $cardSet);
    }

    // Renamed in place from its tab, an empty name keeping the one it had
    #[Route('/pilot/collection/{id}/rename', name: 'pilot_collection_rename', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function rename(CardSet $cardSet, Request $request): RedirectResponse
    {
        $project = $cardSet->getProject();
        $this->guard($project, PilotAccess::MANAGE);
        if ($this->isCsrfTokenValid('pilot_collection_rename' . $cardSet->getId(), $request->request->getString('_token'))) {
            $cardSet->setName($request->request->getString('name'));
            $this->entityManager->flush();
        }

        return $this->back($project, $cardSet);
    }

    // The tabs of a space in the order they were dragged into, the token travelling as a header; a collection of another space ignored
    #[Route('/pilot/space/{slug}/collections/order', name: 'pilot_collections_order', methods: ['POST'])]
    public function order(#[MapEntity(mapping: ['slug' => 'slug'])] Project $project, Request $request): Response
    {
        $this->guard($project, PilotAccess::MANAGE);
        if (!$this->isCsrfTokenValid('pilot_collections_order' . $project->getId(), $request->headers->get('X-CSRF-Token'))) {
            return new Response(null, Response::HTTP_FORBIDDEN);
        }
        $ids = array_values(array_map(intval(...), array_filter($request->request->all('ids'), is_numeric(...))));
        foreach ($this->cardSetRepository->findOfProject($project) as $cardSet) {
            $position = array_search($cardSet->getId(), $ids, true);
            if (false !== $position) {
                $cardSet->setPosition($position);
            }
        }
        $this->entityManager->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    // Deleted, its cards going to the space's first other collection; the last one of a space holding cards is kept
    #[Route('/pilot/collection/{id}/delete', name: 'pilot_collection_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(CardSet $cardSet, Request $request, CardSetRemoval $cardSetRemoval): RedirectResponse
    {
        $project = $cardSet->getProject();
        $this->guard($project, PilotAccess::MANAGE);
        if (!$this->isCsrfTokenValid('pilot_collection_delete' . $cardSet->getId(), $request->request->getString('_token'))) {
            return $this->back($project, $cardSet);
        }
        try {
            $other = $cardSetRemoval->remove($cardSet);
        } catch (FlowException $exception) {
            $this->addFlash('danger', $exception->reason->trans($this->translator));

            return $this->back($project, $cardSet);
        }
        $this->entityManager->flush();

        return $this->back($project, $other);
    }

    // The space's page, opened on the given collection's tab
    private function back(Project $project, ?CardSet $cardSet = null): RedirectResponse
    {
        return $this->redirectToRoute('pilot_project', ['slug' => $project->getSlug()] + (null !== $cardSet ? ['_fragment' => 'collection-' . $cardSet->getId()] : []));
    }
}
