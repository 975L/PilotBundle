<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Controller;

use c975L\PilotBundle\Entity\Credit;
use c975L\PilotBundle\Entity\Machine;
use c975L\PilotBundle\Entity\PlanTemplate;
use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Repository\CardRepository;
use c975L\PilotBundle\Repository\ProjectRepository;
use c975L\PilotBundle\Security\PilotAccess;
use c975L\PilotBundle\Service\CreditRefresher;
use c975L\PilotBundle\Service\SettingsKinds;
use c975L\PilotBundle\Service\StepOrder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

// The piloting settings on the piloting pages' own shell, the admin's: plan templates, machine actions, machines and credits listed, created, edited and deleted, a space made and edited from its own pages
class SettingsController extends AbstractController
{
    use PilotPageTrait;

    // The route names the templates and the redirects use
    public const string LIST_ROUTE = 'pilot_settings_list';
    public const string KINDS = 'space|template|action|machine|credit';

    public function __construct(
        private readonly PilotAccess $pilotAccess,
        private readonly ProjectRepository $projectRepository,
        private readonly CardRepository $cardRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly SettingsKinds $settingsKinds,
        private readonly TranslatorInterface $translator,
        private readonly CreditRefresher $creditRefresher,
    ) {
    }

    // The settings open on the plan templates
    #[Route('/pilot/settings', name: 'pilot_settings')]
    public function index(): RedirectResponse
    {
        $this->denyUnlessAdmin();

        return $this->redirectToRoute(self::LIST_ROUTE, ['kind' => 'template']);
    }

    // The rows of one kind, in their order, with their columns
    #[Route('/pilot/settings/{kind}', name: self::LIST_ROUTE, requirements: ['kind' => self::KINDS])]
    public function list(string $kind): Response
    {
        $this->denyUnlessAdmin();
        $settings = $this->settingsKinds->get($kind);

        return $this->page('@c975LPilot/pilot/settings/list.html.twig', [
            'kind' => $kind,
            'settings' => $settings,
            'rows' => $this->entityManager->getRepository($settings['class'])->findBy([], $settings['sort']),
        ]);
    }

    // A new row of a kind made here (not an action, which a machine declares); a machine's token shown once
    #[Route('/pilot/settings/{kind}/new', name: 'pilot_settings_new', requirements: ['kind' => 'space|template|machine|credit'], methods: ['GET', 'POST'])]
    public function new(string $kind, Request $request): Response
    {
        $this->denyUnlessAdmin();
        $settings = $this->settingsKinds->get($kind);
        $form = $this->createForm($settings['form'])->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $row = $form->getData();
            if ($row instanceof Machine) {
                $this->addFlash('success', $this->translator->trans('flash.machine_created', ['%token%' => $row->newToken()], 'pilot'));
            }
            $this->entityManager->persist($row);

            return $this->saved($kind, $row);
        }

        return $this->formPage($kind, $settings, $form, null);
    }

    // A row edited (a machine's token never shown again); a checklist template saved laying its steps on its open cards
    #[Route('/pilot/settings/{kind}/{id}', name: 'pilot_settings_edit', requirements: ['kind' => self::KINDS, 'id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(string $kind, int $id, Request $request, StepOrder $stepOrder): Response
    {
        $this->denyUnlessAdmin();
        $settings = $this->settingsKinds->get($kind);
        $row = $this->entityManager->find($settings['class'], $id) ?? throw $this->createNotFoundException();
        $previous = $row instanceof PlanTemplate ? $row->getSteps() : [];
        $form = $this->createForm($settings['form'], $row)->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($row instanceof PlanTemplate) {
                $stepOrder->fromTemplate($row, $previous);
            }

            return $this->saved($kind, $row);
        }

        return $this->formPage($kind, $settings, $form, $row);
    }

    // The change written, back to the list of its kind or to the space saved; a credit naming a service read at once, so that a wrong key shows straight away
    private function saved(string $kind, object $row): RedirectResponse
    {
        if ($row instanceof Credit && !$row->isManual() && !$this->creditRefresher->refresh($row)) {
            $this->addFlash('danger', $this->translator->trans('flash.credit_unread', [], 'pilot'));
        }
        $this->entityManager->flush();

        return $row instanceof Project ? $this->redirectToRoute(PilotController::PROJECT_ROUTE, ['slug' => $row->getSlug()]) : $this->redirectToRoute(self::LIST_ROUTE, ['kind' => $kind]);
    }

    // The form of a kind, for a new row or the one edited
    /** @param array<string, mixed> $settings */
    private function formPage(string $kind, array $settings, FormInterface $form, ?object $row): Response
    {
        return $this->page('@c975LPilot/pilot/settings/form.html.twig', ['kind' => $kind, 'settings' => $settings, 'form' => $form, 'row' => $row]);
    }

    // A row deleted for good, what depends on it following the database's rules (a space's cards with it), back to its list or, for a space, to the dashboard
    #[Route('/pilot/settings/{kind}/{id}/delete', name: 'pilot_settings_delete', requirements: ['kind' => self::KINDS, 'id' => '\d+'], methods: ['POST'])]
    public function delete(string $kind, int $id, Request $request): RedirectResponse
    {
        $this->denyUnlessAdmin();
        $row = $this->entityManager->find($this->settingsKinds->get($kind)['class'], $id);
        if (null !== $row && $this->isCsrfTokenValid('pilot_settings_delete' . $kind . $id, $request->request->getString('_token'))) {
            $this->entityManager->remove($row);
            $this->entityManager->flush();
        }

        return 'space' === $kind ? $this->redirectToRoute(PilotController::DASHBOARD_ROUTE) : $this->redirectToRoute(self::LIST_ROUTE, ['kind' => $kind]);
    }
}
