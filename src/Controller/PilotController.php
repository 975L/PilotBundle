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
use c975L\PilotBundle\Entity\Credit;
use c975L\PilotBundle\Entity\Doc;
use c975L\PilotBundle\Entity\Machine;
use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Entity\Report;
use c975L\PilotBundle\Entity\Run;
use c975L\PilotBundle\Enum\RunStatus;
use c975L\PilotBundle\Exception\FlowException;
use c975L\PilotBundle\Form\PlanStep;
use c975L\PilotBundle\Repository\ActionRepository;
use c975L\PilotBundle\Repository\CardRepository;
use c975L\PilotBundle\Repository\CardSetRepository;
use c975L\PilotBundle\Repository\CreditRepository;
use c975L\PilotBundle\Repository\DocRepository;
use c975L\PilotBundle\Repository\MachineRepository;
use c975L\PilotBundle\Repository\MemberRepository;
use c975L\PilotBundle\Repository\PlanTemplateRepository;
use c975L\PilotBundle\Repository\ProjectRepository;
use c975L\PilotBundle\Repository\ReportRepository;
use c975L\PilotBundle\Repository\RunRepository;
use c975L\PilotBundle\Security\PilotAccess;
use c975L\PilotBundle\Service\ActionOffer;
use c975L\PilotBundle\Service\CardFiller;
use c975L\PilotBundle\Service\CardFlow;
use c975L\PilotBundle\Service\CardImages;
use c975L\PilotBundle\Service\CardMarkdown;
use c975L\PilotBundle\Service\CardRemoval;
use c975L\PilotBundle\Service\CreditOutlook;
use c975L\PilotBundle\Service\CreditRefresher;
use c975L\PilotBundle\Service\PilotUrls;
use c975L\PilotBundle\Service\PlanFactory;
use c975L\PilotBundle\Service\Planning;
use c975L\PilotBundle\Service\ProjectOrder;
use c975L\PilotBundle\Service\RunOutputs;
use c975L\PilotBundle\Service\StepOrder;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

// The three ways in: the dashboard, a project with its backlog, a search; plus the page of a card and the gestures on it
class PilotController extends AbstractController
{
    use PilotPageTrait;

    // The route names the templates, the scripts and the redirects use
    public const string DASHBOARD_ROUTE = 'pilot_dashboard';
    public const string PROJECT_ROUTE = 'pilot_project';
    public const string CARD_ROUTE = 'pilot_card';
    public const string SEARCH_ROUTE = 'pilot_search';
    public const string GESTURE_ROUTE = 'pilot_card_gesture';
    public const string ORDER_ROUTE = 'pilot_projects_order';
    public const string NEW_CARD_ROUTE = 'pilot_card_new';
    public const string REPORT_ROUTE = 'pilot_report_run';
    public const string DONE_ROUTE = 'pilot_project_done';
    public const string DIALOG_HEADER = 'X-Pilot-Dialog';
    public const string LOCATION_HEADER = 'X-Pilot-Location';
    public const string GONE_HEADER = 'X-Pilot-Gone';

    // What the card's page asks the account may do, to show only the gestures it may make
    private const array CARD_LEVELS = [PilotAccess::WORK, PilotAccess::EDIT, PilotAccess::RUN, PilotAccess::DELETE];

    // What each gesture on a card asks of the account, editing the card otherwise
    private const array GESTURE_LEVELS = [
        'step' => PilotAccess::WORK,
        'feedbackadd' => PilotAccess::WORK,
        'feedbackimage' => PilotAccess::WORK,
        'runstep' => PilotAccess::RUN,
        'cancel' => PilotAccess::RUN,
        'feedbackrun' => PilotAccess::RUN,
    ];
    public const string CARDS_ORDER_ROUTE = 'pilot_cards_order';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CardRepository $cardRepository,
        private readonly ReportRepository $reportRepository,
        private readonly DocRepository $docRepository,
        private readonly ProjectRepository $projectRepository,
        private readonly ClockInterface $clock,
        private readonly CardFlow $cardFlow,
        private readonly TranslatorInterface $translator,
        private readonly ActionRepository $actionRepository,
        private readonly StepOrder $stepOrder,
        private readonly CardFiller $cardFiller,
        private readonly PilotAccess $pilotAccess,
        private readonly MemberRepository $memberRepository,
        private readonly CardSetRepository $cardSetRepository,
        private readonly CardImages $cardImages,
    ) {
    }

    // Late cards, the coming days up to the horizon, then the later ones by month, the credits to spend before their renewal above them
    #[Route('/pilot', name: 'pilot_dashboard')]
    public function dashboard(Planning $planning, CreditRepository $creditRepository, RunRepository $runRepository, CreditOutlook $creditOutlook): Response
    {
        $this->denyAccess();
        $scope = $this->pilotAccess->scope();
        $plan = $planning->build($scope);
        $credits = $scope->all ? $creditRepository->findOrdered() : [];

        return $this->page('@c975LPilot/pilot/dashboard.html.twig', $plan + [
            'credits' => $credits,
            'outlook' => $creditOutlook->build($credits, Planning::cardsOf($plan), $plan['today']),
            'runs' => $runRepository->findActive($scope),
            'reports' => $this->reportRepository->findShownOn(null, $scope),
            'waiting' => $this->cardRepository->findWaiting($scope),
        ]);
    }

    // The balances of the services read again from the dashboard, the ones that could not be read said so
    #[Route('/pilot/credit/refresh', name: 'pilot_credits_refresh', methods: ['POST'])]
    public function creditsRefresh(Request $request, CreditRefresher $creditRefresher): RedirectResponse
    {
        $this->denyUnlessAdmin();
        if ($this->isCsrfTokenValid('pilot_credits_refresh', $request->request->getString('_token'))) {
            if ([] !== $creditRefresher->refreshAll()) {
                $this->addFlash('danger', $this->translator->trans('flash.credit_unread', [], 'pilot'));
            }
            $this->entityManager->flush();
        }

        return $this->redirectToRoute(self::DASHBOARD_ROUTE);
    }

    // The balance of a service typed in by hand, from the dashboard
    #[Route('/pilot/credit/{id}', name: 'pilot_credit', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function credit(Credit $credit, Request $request): RedirectResponse
    {
        $this->denyUnlessAdmin();
        $remaining = $request->request->getString('remaining');
        if ($credit->isManual() && 1 === preg_match('/^\d{1,9}$/', $remaining) && $this->isCsrfTokenValid('pilot_credit' . $credit->getId(), $request->request->getString('_token'))) {
            $credit->setRemaining((int) $remaining, $this->clock->now());
            $this->entityManager->flush();
        }

        return $this->redirectToRoute(self::DASHBOARD_ROUTE);
    }

    // A space: its open cards as tiles, the done ones as short rows, its references
    #[Route('/pilot/{slug}', name: PilotUrls::SPACE_ROUTE, requirements: ['slug' => '[a-z0-9-]+'], priority: -10)]
    public function project(#[MapEntity(mapping: ['slug' => 'slug'])] Project $project, PlanTemplateRepository $planTemplateRepository, ActionOffer $actionOffer, RunRepository $runRepository): Response
    {
        $this->guard($project, PilotAccess::VIEW);
        $scope = $this->pilotAccess->scope();
        // A guest sees the cards given to it, the space's files, reports, plans and actions staying its contributors'
        $whole = $this->pilotAccess->can($project, PilotAccess::EDIT);

        return $this->page('@c975LPilot/pilot/project.html.twig', [
            'project' => $project,
            'templates' => $whole ? $planTemplateRepository->findOfferedTo($project) : [],
            'actions' => $this->pilotAccess->can($project, PilotAccess::RUN) ? $actionOffer->on($project) : [],
            'runs' => $runRepository->findActive($scope, $project),
            'reports' => $whole ? $this->reportRepository->findShownOn($project, $scope) : [],
            'cards' => $this->cardRepository->findOpen($project, $scope),
            'card_sets' => $this->cardSetRepository->findOfProject($project),
            'max_lines' => CardFiller::MAX_LINES,
            'docs' => $whole ? $this->docRepository->findBy(['project' => $project], ['title' => 'ASC']) : [],
            'references' => $whole ? $this->docRepository->findReferences($project) : [],
            'done' => $this->cardRepository->countDone($project, $scope),
            'can_edit' => $whole,
            'can_manage' => $this->pilotAccess->can($project, PilotAccess::MANAGE),
        ]);
    }

    // The done cards of a tab of a space (0 for the space's own cards), read when their fold opens
    #[Route('/pilot/space/{slug}/done/{set}', name: self::DONE_ROUTE, requirements: ['set' => '\d+'], methods: ['GET'])]
    public function done(#[MapEntity(mapping: ['slug' => 'slug'])] Project $project, int $set): Response
    {
        $this->guard($project, PilotAccess::VIEW);
        $cardSet = 0 === $set ? null : $this->cardSetRepository->find($set);
        if (0 !== $set && $cardSet?->getProject() !== $project) {
            throw $this->createNotFoundException();
        }

        return $this->render('@c975LPilot/pilot/_done.html.twig', [
            'project' => $project,
            'cards' => $this->cardRepository->findDone($project, $this->pilotAccess->scope(), $cardSet),
        ]);
    }

    // A project renamed in place from its page, its address (slug) kept; an empty name keeping the one it had
    #[Route('/pilot/space/{slug}/rename', name: 'pilot_project_rename', methods: ['POST'])]
    public function rename(#[MapEntity(mapping: ['slug' => 'slug'])] Project $project, Request $request): RedirectResponse
    {
        $this->guard($project, PilotAccess::MANAGE);
        $name = trim($request->request->getString('name'));
        if ('' !== $name && $this->isCsrfTokenValid('pilot_project_rename' . $project->getId(), $request->request->getString('_token'))) {
            $project->setName(mb_substr($name, 0, 100));
            $this->entityManager->flush();
        }

        return $this->redirectToRoute(self::PROJECT_ROUTE, ['slug' => $project->getSlug()]);
    }

    // A project's colour chosen on its page, anything but a #rrggbb colour left aside
    #[Route('/pilot/space/{slug}/color', name: 'pilot_project_color', methods: ['POST'])]
    public function color(#[MapEntity(mapping: ['slug' => 'slug'])] Project $project, Request $request): RedirectResponse
    {
        $this->guard($project, PilotAccess::MANAGE);
        $color = strtolower($request->request->getString('color'));
        if (1 === preg_match('/^#[0-9a-f]{6}$/', $color) && $this->isCsrfTokenValid('pilot_project_color' . $project->getId(), $request->request->getString('_token'))) {
            $project->setColor($color);
            $this->entityManager->flush();
        }

        return $this->redirectToRoute(self::PROJECT_ROUTE, ['slug' => $project->getSlug()]);
    }

    // Cards added to the space from a pasted list, one per line, the titles already there left aside
    #[Route('/pilot/space/{slug}/cards', name: 'pilot_project_cards', methods: ['POST'])]
    public function cards(#[MapEntity(mapping: ['slug' => 'slug'])] Project $project, Request $request): RedirectResponse
    {
        $this->guard($project, PilotAccess::EDIT);
        if ($this->isCsrfTokenValid('pilot_project_cards' . $project->getId(), $request->request->getString('_token'))) {
            foreach ($this->cardFiller->fill($project, $request->request->getString('titles'), $this->cardSetRepository->find((int) $request->request->getString('collection'))) as $card) {
                $this->entityManager->persist($card);
            }
            $this->entityManager->flush();
        }

        return $this->redirectToRoute(self::PROJECT_ROUTE, ['slug' => $project->getSlug()]);
    }

    // A recurring action launched from the project's page: a new card of the project, dated today, handed to the machine at once
    #[Route('/pilot/space/{slug}/run', name: 'pilot_project_run', methods: ['POST'])]
    public function run(#[MapEntity(mapping: ['slug' => 'slug'])] Project $project, Request $request): RedirectResponse
    {
        $this->guard($project, PilotAccess::RUN);
        $key = $request->request->getString('action');
        $action = $this->actionRepository->findOneBy(['key' => $key]);
        if (null === $action || $action->isCardOnly() || !$action->isOfferedTo($project) || !$this->isCsrfTokenValid('pilot_project_run' . $project->getId(), $request->request->getString('_token'))) {
            return $this->redirectToRoute(self::PROJECT_ROUTE, ['slug' => $project->getSlug()]);
        }
        $today = $this->clock->now()->setTime(0, 0);
        $card = new Card($project, $action->getLabel())->setDue($today);
        $this->entityManager->persist($card);
        $this->entityManager->persist($this->cardFlow->entrust($card, $key, $this->params($request), $action->getLabel()));
        $this->entityManager->flush();

        return $this->redirectToRoute(self::CARD_ROUTE, ['id' => $card->getId()]);
    }

    // An action of a report, on one of its rows or on the whole table: a card dated today on the page's project when the row is shown there, the report's project otherwise, handed to the machine at once, an action the space is not offered refused
    #[Route('/pilot/report/{id}/run', name: 'pilot_report_run', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function reportRun(Report $report, Request $request): RedirectResponse
    {
        $this->denyAccess();
        $row = $request->request->has('row') ? $request->request->getInt('row') : null;
        $action = $this->reportAction($report, $request, $row);
        if (null !== $action) {
            $project = $this->reportProject($report, $request, $row);
            $this->guard($project, PilotAccess::RUN);
            $this->reportCard($report, $project, $action, $row, $request);
        }

        return $this->redirect($this->back($request, $this->generateUrl(self::DASHBOARD_ROUTE)));
    }

    // The card a report's action makes, written only once its run is queued, an action the space is not offered refused with its reason
    /** @param array{action: string, label: string, params: array<string, string>, ask: list<string>, if: ?string} $action */
    private function reportCard(Report $report, Project $project, array $action, ?int $row, Request $request): void
    {
        $title = null === $row ? $action['label'] : $action['label'] . ' · ' . (string) array_values($report->getRows()[$row])[0];
        $card = new Card($project, mb_substr($title, 0, 500))->setDue($this->clock->now()->setTime(0, 0));
        try {
            $run = $this->cardFlow->entrust($card, $action['action'], $report->params($action, $row, $this->params($request)), $action['label']);
        } catch (FlowException $exception) {
            $this->addFlash('danger', $exception->reason->trans($this->translator));

            return;
        }
        $this->entityManager->persist($card);
        $this->entityManager->persist($run);
        $this->entityManager->flush();
    }

    // The space a report's card goes to: the page's when the report shows the row, or the table, there; the report's own otherwise
    private function reportProject(Report $report, Request $request, ?int $row): Project
    {
        $project = $this->projectRepository->find($request->request->getInt('project'));
        $shown = null === $project ? [] : $report->rowsFor($project);
        if ([] === $shown || (null !== $row && !\in_array($row, $shown, true))) {
            return $report->getProject();
        }

        return $project;
    }

    // The action asked on a report, null when it does not exist, does not apply to the row or the token is wrong
    /** @return ?array{action: string, label: string, params: array<string, string>, ask: list<string>, if: ?string} */
    private function reportAction(Report $report, Request $request, ?int $row): ?array
    {
        $action = (null === $row ? $report->getGlobals() : $report->getActions())[$request->request->getInt('action')] ?? null;
        if (null === $action || (null !== $row && !isset($report->getRows()[$row])) || !$report->applies($action, $row)) {
            return null;
        }

        return $this->isCsrfTokenValid(self::REPORT_ROUTE . $report->getId(), $request->request->getString('_token')) ? $action : null;
    }

    // A plan of the project from a template: its title and the values of the template's variables, then its cards
    #[Route('/pilot/{slug}/plan', name: 'pilot_plan_new', requirements: ['slug' => '[a-z0-9-]+'], methods: ['GET', 'POST'], priority: -5)]
    public function newPlan(#[MapEntity(mapping: ['slug' => 'slug'])] Project $project, Request $request, PlanTemplateRepository $planTemplateRepository, PlanFactory $planFactory): Response
    {
        $this->guard($project, PilotAccess::EDIT);
        $template = $planTemplateRepository->find($request->query->getInt('template'));
        if (null === $template || !$template->isOfferedTo($project)) {
            throw $this->createNotFoundException();
        }
        $title = trim($request->request->getString('title'));
        if ($request->isMethod('POST') && '' !== $title && $this->isCsrfTokenValid('pilot_plan_new', $request->request->getString('_token'))) {
            $values = array_filter($request->request->all('values'), is_string(...));
            $this->entityManager->persist($planFactory->create($template, $project, $title, array_map(trim(...), $values)));
            $this->entityManager->flush();

            return $this->redirectToRoute(self::PROJECT_ROUTE, ['slug' => $project->getSlug()]);
        }

        return $this->page('@c975LPilot/pilot/plan_new.html.twig', [
            'project' => $project,
            'template' => $template,
            'variables' => $planFactory->variables($template),
        ]);
    }

    // Cards and files holding the words, from two letters
    #[Route('/pilot/search', name: 'pilot_search')]
    public function search(Request $request): Response
    {
        $this->denyAccess();
        $words = trim($request->query->getString('q'));
        $searched = mb_strlen($words) >= 2;

        return $this->page('@c975LPilot/pilot/search.html.twig', [
            'words' => $words,
            'cards' => $searched ? $this->cardRepository->search($words, $this->pilotAccess->scope()) : [],
            'docs' => $searched ? $this->docRepository->search($words, $this->pilotAccess->scope()) : [],
        ]);
    }

    // The projects of the left column in the order they were dragged into, the token travelling as a header
    #[Route('/pilot/projects/order', name: 'pilot_projects_order', methods: ['POST'])]
    public function order(Request $request, ProjectOrder $projectOrder): Response
    {
        $this->denyUnlessAdmin();
        if (!$this->isCsrfTokenValid(self::ORDER_ROUTE, $request->headers->get('X-CSRF-Token'))) {
            return new Response(null, Response::HTTP_FORBIDDEN);
        }
        $ids = array_values(array_map(intval(...), array_filter($request->request->all('ids'), is_numeric(...))));
        $projectOrder->apply($this->projectRepository->findOrdered(), $ids);
        $this->entityManager->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    // Cards put in the order they were dragged into, inside a backlog or a day, the token travelling as a header
    #[Route('/pilot/cards/order', name: 'pilot_cards_order', methods: ['POST'])]
    public function cardsOrder(Request $request): Response
    {
        $this->denyAccess();
        if (!$this->isCsrfTokenValid(self::CARDS_ORDER_ROUTE, $request->headers->get('X-CSRF-Token'))) {
            return new Response(null, Response::HTTP_FORBIDDEN);
        }
        $ids = array_values(array_map(intval(...), array_filter($request->request->all('ids'), is_numeric(...))));
        $cards = [];
        foreach ($this->cardRepository->findBy(['id' => $ids]) as $card) {
            if ($this->pilotAccess->can($card, PilotAccess::EDIT)) {
                $cards[(int) $card->getId()] = $card;
            }
        }
        foreach ($ids as $position => $id) {
            ($cards[$id] ?? null)?->setRank($position);
        }
        $this->entityManager->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    // A card by its number alone, sent to its address under its space (the dialog follows it)
    #[Route('/pilot/card/{id}', name: 'pilot_card', requirements: ['id' => '\d+'])]
    public function permalink(Card $card, PilotUrls $pilotUrls): RedirectResponse
    {
        $this->guard($card, PilotAccess::VIEW);

        return $this->redirect($pilotUrls->card($card));
    }

    // Everything about a card: note, checklist with what its steps spend against the balances, file, runs, its markdown handed to an AI with a step; an address naming another space sent to its own
    #[Route('/pilot/{space}/{id}', name: PilotUrls::CARD_ROUTE, requirements: ['space' => '[a-z0-9-]+', 'id' => '\d+'], priority: -10)]
    public function card(Card $card, Request $request, PilotUrls $pilotUrls, CardMarkdown $cardMarkdown, ActionOffer $actionOffer, RunOutputs $runOutputs, CreditRepository $creditRepository, MachineRepository $machineRepository): Response
    {
        $this->guard($card, PilotAccess::VIEW);
        $url = $pilotUrls->card($card);
        if ($url !== $request->getBaseUrl() . $request->getPathInfo()) {
            return $this->redirect($url);
        }
        $run = $card->lastRun();
        $keys = array_values(array_unique(array_filter(array_map(static fn (array $step): ?string => $step['cost']['credit'] ?? null, $card->getChecklist()))));

        $can = array_combine(self::CARD_LEVELS, array_map(fn (string $level): bool => $this->pilotAccess->can($card, $level), self::CARD_LEVELS));

        return $this->page('@c975LPilot/pilot/card.html.twig', [
            'card' => $card,
            'run' => $run,
            'step_files' => $runOutputs->ofSteps($card),
            'action_labels' => $actionOffer->labels(),
            'docs' => $can[PilotAccess::EDIT] ? $this->docRepository->findFiles($card->getProject()) : [],
            'card_sets' => $can[PilotAccess::EDIT] ? $this->cardSetRepository->findOfProject($card->getProject()) : [],
            'members' => $can[PilotAccess::EDIT] ? $this->memberRepository->findOfProject($card->getProject()) : [],
            'credits' => [] === $keys || !$this->pilotAccess->isAdmin() ? [] : array_column(array_map(static fn (Credit $credit): array => [$credit->getKey(), $credit], $creditRepository->findBy(['key' => $keys])), 1, 0),
            'can' => $can,
            'online_actions' => $this->onlineActions($card, $machineRepository),
            'markdown' => $cardMarkdown->write($card),
        ]);
    }

    // The actions of the card's queued runs an online machine serving its space can take now, so a run no machine will take says so
    /** @return list<string> */
    private function onlineActions(Card $card, MachineRepository $machineRepository): array
    {
        $queued = array_unique(array_filter(array_map(static fn (Run $run): ?string => RunStatus::Queued === $run->getStatus() ? $run->getAction() : null, $card->getRuns()->toArray())));
        if ([] === $queued) {
            return [];
        }
        $now = $this->clock->now();
        $machines = $machineRepository->findAll();

        return array_values(array_filter($queued, static fn (string $action): bool => [] !== array_filter($machines, static fn (Machine $machine): bool => $machine->canRun($action, $card->getProject(), $now))));
    }

    // A file on its own, with the cards pointing to it (what is to do, then what was done), deleted from here by who may delete in its space
    #[Route('/pilot/doc/{slug}', name: 'pilot_doc', requirements: ['slug' => '[a-z0-9-]+'])]
    public function doc(#[MapEntity(mapping: ['slug' => 'slug'])] Doc $doc): Response
    {
        $this->guardDoc($doc, PilotAccess::EDIT);

        $project = $doc->getProject();
        $canDelete = null === $project ? $this->pilotAccess->isAdmin() : $this->pilotAccess->can($project, PilotAccess::DELETE);

        return $this->page('@c975LPilot/pilot/doc.html.twig', ['doc' => $doc, 'project' => $project, 'cards' => $this->cardRepository->findOfDoc($doc), 'can_delete' => $canDelete]);
    }

    // A file's title and two floors written on its page; in the dialog the answer says only whether it was kept
    #[Route('/pilot/doc/{slug}/edit', name: 'pilot_doc_edit', requirements: ['slug' => '[a-z0-9-]+'], methods: ['POST'])]
    public function docEdit(#[MapEntity(mapping: ['slug' => 'slug'])] Doc $doc, Request $request): Response
    {
        $this->guardDoc($doc, PilotAccess::EDIT);
        $kept = $this->isCsrfTokenValid('pilot_doc_edit' . $doc->getId(), $request->request->getString('_token'));
        if ($kept) {
            $title = trim($request->request->getString('title'));
            $summary = trim($request->request->getString('summary'));
            $detail = trim($request->request->getString('detail'));
            $doc->setTitle('' !== $title ? mb_substr($title, 0, 200) : $doc->getTitle())->write('' !== $summary ? $summary : null, '' !== $detail ? $detail : null);
            $this->entityManager->flush();
        }
        if ($request->headers->has(self::DIALOG_HEADER)) {
            return $kept ? new Response(null, Response::HTTP_NO_CONTENT) : new Response($this->translator->trans('label.gesture_expired', [], 'pilot'), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->redirect($this->back($request, $this->generateUrl('pilot_doc', ['slug' => $doc->getSlug()])));
    }

    // A file as one markdown download, to hand over or keep
    #[Route('/pilot/doc/{slug}.md', name: 'pilot_doc_markdown', requirements: ['slug' => '[a-z0-9-]+'])]
    public function markdown(#[MapEntity(mapping: ['slug' => 'slug'])] Doc $doc): Response
    {
        $this->guardDoc($doc, PilotAccess::EDIT);

        return $this->markdownDownload($doc->toMarkdown(), $doc->getSlug());
    }

    // A file deleted for good, the cards pointing to it keeping their work without it; then back to its space, the dialog closing
    #[Route('/pilot/doc/{slug}/delete', name: 'pilot_doc_delete', requirements: ['slug' => '[a-z0-9-]+'], methods: ['POST'])]
    public function docDelete(#[MapEntity(mapping: ['slug' => 'slug'])] Doc $doc, Request $request): Response
    {
        $this->guardDoc($doc, PilotAccess::DELETE);
        $project = $doc->getProject();
        $deleted = $this->isCsrfTokenValid('pilot_doc_delete' . $doc->getId(), $request->request->getString('_token'));
        if ($deleted) {
            foreach ($this->cardRepository->findOfDoc($doc) as $card) {
                $card->setDoc(null);
            }
            $this->entityManager->remove($doc);
            $this->entityManager->flush();
        }
        if ($request->headers->has(self::DIALOG_HEADER)) {
            return $deleted ? new Response(null, Response::HTTP_NO_CONTENT, [self::GONE_HEADER => '1']) : new Response($this->translator->trans('label.gesture_expired', [], 'pilot'), Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if (!$deleted) {
            $this->addFlash('danger', $this->translator->trans('label.gesture_expired', [], 'pilot'));

            return $this->redirectToRoute('pilot_doc', ['slug' => $doc->getSlug()]);
        }

        return null !== $project ? $this->redirectToRoute(self::PROJECT_ROUTE, ['slug' => $project->getSlug()]) : $this->redirectToRoute('pilot_dashboard');
    }

    // A card as one markdown download, the same text its steps are handed to an AI with
    #[Route('/pilot/card/{id}.md', name: 'pilot_card_markdown', requirements: ['id' => '\d+'])]
    public function cardMarkdown(Card $card, CardMarkdown $cardMarkdown): Response
    {
        $this->guard($card, PilotAccess::VIEW);

        return $this->markdownDownload($cardMarkdown->write($card), 'card-' . $card->getId());
    }

    // A markdown text handed as a file to download under the given name
    private function markdownDownload(string $markdown, string $name): Response
    {
        $response = new Response($markdown, Response::HTTP_OK, ['Content-Type' => 'text/markdown; charset=UTF-8']);
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $name . '.md'));

        return $response;
    }

    // A file a run produced, kept on the site: an image or a video shown under the type of its extension, anything else downloaded, so that an html or svg one runs no script on the back office's origin
    #[Route('/pilot/run/{id}/{name}', name: 'pilot_run_output', requirements: ['id' => '\d+', 'name' => '[A-Za-z0-9._-]+'])]
    public function output(Run $run, string $name, RunOutputs $runOutputs): BinaryFileResponse
    {
        $this->guard($run->getCard(), PilotAccess::VIEW);
        $path = $runOutputs->path($run, $name);
        if (null === $path) {
            throw $this->createNotFoundException();
        }
        $media = $runOutputs->mediaType($path);
        $headers = ['Content-Type' => $media ?? 'application/octet-stream', 'Content-Security-Policy' => 'sandbox', 'X-Content-Type-Options' => 'nosniff'];

        return new BinaryFileResponse($path, Response::HTTP_OK, $headers, true, null === $media ? ResponseHeaderBag::DISPOSITION_ATTACHMENT : null);
    }

    // Done, back to do, a date (empty sends it back to the backlog), another space, its title and note, a feedback added (with its screenshot), its screenshot set or removed, its steps reordered, a step ticked or run, or a machine gesture; then back where the gesture came from
    #[Route('/pilot/card/{id}/{gesture}', name: 'pilot_card_gesture', requirements: ['id' => '\d+', 'gesture' => 'done|reopen|schedule|move|edit|assign|unassign|stepadd|feedbackadd|feedbackimage|stepedit|steporder|step|runstep|cancel|feedbackrun'], methods: ['POST'])]
    public function gesture(Card $card, string $gesture, Request $request): Response
    {
        $this->guard($card, PilotAccess::VIEW);
        $refusal = match (true) {
            !$this->pilotAccess->can($card, self::GESTURE_LEVELS[$gesture] ?? PilotAccess::EDIT) => $this->translator->trans('label.gesture_forbidden', [], 'pilot'),
            !$this->isCsrfTokenValid(self::GESTURE_ROUTE . $card->getId(), $request->request->getString('_token')) => $this->translator->trans('label.gesture_expired', [], 'pilot'),
            default => null,
        };
        if (null === $refusal) {
            try {
                // The screenshots a gesture took off its feedback removed once it is written
                $images = $card->getFeedbackImages();
                $this->apply($card, $gesture, $request);
                $this->entityManager->flush();
                $this->cardImages->forget(array_values(array_diff($images, $card->getFeedbackImages())));
            } catch (FlowException $exception) {
                $refusal = $exception->reason->trans($this->translator);
            }
        }

        // A gesture made in the card's dialog gets its answer as such, the dialog showing a refusal and reading the card again
        if ($request->headers->has(self::DIALOG_HEADER)) {
            return new Response($refusal, null === $refusal ? Response::HTTP_NO_CONTENT : Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if (null !== $refusal) {
            $this->addFlash('danger', $refusal);
        }

        return $this->redirect($this->back($request, $this->generateUrl(self::CARD_ROUTE, ['id' => $card->getId()])));
    }

    // A card added in one line to a space's backlog, laid with the space's template, pointing to one of its references when added from its page; then opened over the page it was added from
    #[Route('/pilot/card/new', name: 'pilot_card_new', methods: ['POST'])]
    public function newCard(Request $request): Response
    {
        $this->denyAccess();
        $project = $this->projectRepository->find($request->request->getInt('project'));
        $title = trim($request->request->getString('title'));
        $back = $this->back($request, $this->generateUrl(self::DASHBOARD_ROUTE));
        if (null === $project || '' === $title || !$this->isCsrfTokenValid(self::NEW_CARD_ROUTE, $request->request->getString('_token'))) {
            return $this->redirect($back);
        }
        $this->guard($project, PilotAccess::EDIT);
        $doc = $this->docRepository->find((int) $request->request->getString('doc'));
        $card = $this->cardFiller->card($project, $title, $this->cardSetRepository->find((int) $request->request->getString('collection')))->setDoc($doc?->getProject() === $project ? $doc : null);
        $this->entityManager->persist($card);
        $this->entityManager->flush();

        // The new card opened at once: in the dialog it was added from, or over the page it was added on
        if ($request->headers->has(self::DIALOG_HEADER)) {
            return new Response(null, Response::HTTP_NO_CONTENT, [self::LOCATION_HEADER => $this->generateUrl(self::CARD_ROUTE, ['id' => $card->getId()])]);
        }

        return $this->redirect(strtok($back, '#') . '#card-' . $card->getId());
    }

    // A card copied, the copy opened in its place: in the dialog, its address sent back for the dialog to read it
    #[Route('/pilot/card/{id}/duplicate', name: 'pilot_card_duplicate', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function duplicate(Card $card, Request $request): Response
    {
        $this->guard($card, PilotAccess::EDIT);
        $dialog = $request->headers->has(self::DIALOG_HEADER);
        if (!$this->isCsrfTokenValid(self::GESTURE_ROUTE . $card->getId(), $request->request->getString('_token'))) {
            return $dialog ? new Response($this->translator->trans('label.gesture_expired', [], 'pilot'), Response::HTTP_UNPROCESSABLE_ENTITY) : $this->redirectToRoute(self::CARD_ROUTE, ['id' => $card->getId()]);
        }
        $copy = $card->duplicate($this->translator->trans('label.card_copy_suffix', [], 'pilot'));
        $this->entityManager->persist($copy);
        $this->entityManager->flush();
        $url = $this->generateUrl(self::CARD_ROUTE, ['id' => $copy->getId()]);

        return $dialog ? new Response(null, Response::HTTP_NO_CONTENT, [self::LOCATION_HEADER => $url]) : $this->redirect($url);
    }

    // A card deleted for good with its picture, its runs and the files they kept, refused while a machine works on it; then back to its space, the dialog closing
    #[Route('/pilot/card/{id}/delete', name: 'pilot_card_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Card $card, Request $request, CardRemoval $cardRemoval): Response
    {
        $this->guard($card, PilotAccess::DELETE);
        $project = $card->getProject();
        $refusal = $this->isCsrfTokenValid(self::GESTURE_ROUTE . $card->getId(), $request->request->getString('_token')) ? null : $this->translator->trans('label.gesture_expired', [], 'pilot');
        try {
            if (null === $refusal) {
                $cardRemoval->remove($card);
            }
        } catch (FlowException $exception) {
            $refusal = $exception->reason->trans($this->translator);
        }
        if ($request->headers->has(self::DIALOG_HEADER)) {
            return null === $refusal ? new Response(null, Response::HTTP_NO_CONTENT, [self::GONE_HEADER => '1']) : new Response($refusal, Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if (null !== $refusal) {
            $this->addFlash('danger', $refusal);
        }

        return $this->redirectToRoute(self::PROJECT_ROUTE, ['slug' => $project->getSlug()]);
    }

    // One gesture on a card, a run it creates being persisted
    private function apply(Card $card, string $gesture, Request $request): void
    {
        $run = match ($gesture) {
            'done' => $this->cardFlow->complete($card),
            'reopen' => $card->markDone(null),
            'schedule' => $card->setDue($this->day($request->request->getString('due'))),
            'move' => $this->move($card, $request->request->getInt('project')),
            'edit' => $this->edit($card, $request),
            'assign', 'unassign' => $this->assignee($card, $request->request->getInt('member'), 'assign' === $gesture),
            'stepadd' => $this->addStep($card, $request->request->getString('text')),
            'feedbackadd' => $this->feedbackAdd($card, $request),
            'feedbackimage' => $this->feedbackImage($card, $request),
            'stepedit' => $this->stepEdit($card, $request),
            'steporder' => $this->stepOrder->reorder($card, array_values(array_map(intval(...), array_filter($request->request->all('order'), is_numeric(...))))),
            'step' => $this->cardFlow->tick($card, $request->request->getInt('index', -1)),
            'runstep' => $this->cardFlow->runStep($card, $request->request->getInt('index', -1)),
            'cancel' => $this->cardFlow->cancel($card, $request->request->has('run') ? $request->request->getInt('run') : null),
            default => $this->cardFlow->treatFeedback($card),
        };
        if ($run instanceof Run) {
            $this->entityManager->persist($run);
        }
    }

    // The card given to a member of its space, or taken back; anyone else changes nothing
    private function assignee(Card $card, int $memberId, bool $given): Card
    {
        $member = $this->memberRepository->find($memberId);
        if (null === $member || $member->getProject() !== $card->getProject()) {
            return $card;
        }

        return $given ? $card->addAssignee($member->getUser()) : $card->removeAssignee($member->getUser());
    }

    // A step added by hand, never on a card following a checklist template, which takes its steps from it alone
    private function addStep(Card $card, string $text): Card
    {
        if (!$card->takesStepsByHand()) {
            throw new FlowException(t('flow.template_steps', [], 'pilot'));
        }

        return $card->addStep($text);
    }

    // The card moved to another space the account may write to, a card of a plan staying with its plan
    private function move(Card $card, int $projectId): Card
    {
        $project = $this->projectRepository->find($projectId);
        if (null === $project || !$this->pilotAccess->can($project, PilotAccess::EDIT)) {
            throw new FlowException(t('label.gesture_forbidden', [], 'pilot'));
        }
        if (null !== $card->getPlan()) {
            throw new FlowException(t('label.card_move_plan', [], 'pilot'));
        }

        return $card->moveTo($project);
    }

    // A feedback added, with up to MAX_FEEDBACK_IMAGES screenshots sent or pasted along, a picture refused adding nothing, a full checklist refused before any file is kept
    private function feedbackAdd(Card $card, Request $request): Card
    {
        $text = $request->request->getString('text');
        $sent = $request->files->get('image');
        $files = array_slice(array_filter(\is_array($sent) ? $sent : [$sent], static fn (mixed $file): bool => $file instanceof UploadedFile), 0, Card::MAX_FEEDBACK_IMAGES);
        $images = [];
        if ([] !== $files && '' !== trim($text)) {
            Card::expectRoom(\count($card->getChecklist()) + 1);
            // A picture refused among several takes the ones already kept off the disk
            foreach ($files as $file) {
                try {
                    $images[] = $this->screenshot($file);
                } catch (FlowException $refusal) {
                    $this->cardImages->forget($images);

                    throw $refusal;
                }
            }
        }
        $before = array_key_last($card->getChecklist());
        $card->addFeedback($text);
        $added = array_key_last($card->getChecklist());

        return [] !== $images && $added !== $before ? $card->setFeedbackImages((int) $added, $images) : $card;
    }

    // A screenshot added to a feedback, refused once it holds MAX_FEEDBACK_IMAGES before the file is kept, or the one named taken off by « remove »
    private function feedbackImage(Card $card, Request $request): Card
    {
        $index = $request->request->getInt('index', -1);
        $file = $request->files->get('image');
        if ($request->request->has('remove')) {
            return $card->removeFeedbackImage($index, $request->request->getString('remove'));
        }
        if (!$file instanceof UploadedFile || !$card->isFeedback($index)) {
            return $card;
        }
        $card->expectImageRoom($index);

        return $card->addFeedbackImage($index, $this->screenshot($file));
    }

    // A screenshot kept, refused when it is no picture or too heavy
    private function screenshot(UploadedFile $file): string
    {
        return $this->cardImages->storeFeedback($file) ?? throw new FlowException(t('label.card_image_refused', [], 'pilot'));
    }

    // A step's text and, when posted, the machine action it names and that action's parameters, these written only once changed so a value holding « ; » survives a rename; an empty text removes the step
    private function stepEdit(Card $card, Request $request): Card
    {
        $index = $request->request->getInt('index', -1);
        $text = $request->request->getString('text');
        $params = trim($request->request->getString('params'));
        $changed = $request->request->has('params') && $params !== PlanStep::paramsText($card->getChecklist()[$index]['params'] ?? [], '; ');
        if ('' !== trim($text) && $request->request->has('action')) {
            $card->setStepAction($index, $request->request->getString('action'));
        }
        if ($changed) {
            $card->setStepParams($index, PlanStep::paramsFrom($params));
        }

        return $card->renameStep($index, $text, $this->clock->now());
    }

    // The title, the note, who an answer is awaited from, the links, the collection or the file, edited in place one at a time on the card's page: only the fields posted change, an empty title keeping the one it had
    private function edit(Card $card, Request $request): Card
    {
        $posted = $request->request;
        $title = trim($posted->getString('title'));
        if ('' !== $title) {
            $card->setTitle($title);
        }
        if ($posted->has('note')) {
            $note = trim($posted->getString('note'));
            $card->setNote('' !== $note ? $note : null);
        }
        if ($posted->has('waitingOn')) {
            $card->setWaitingOn($posted->getString('waitingOn'));
        }
        if ($posted->has('sourceUrl')) {
            $card->setSourceUrl($posted->getString('sourceUrl'));
        }
        if ($posted->has('resultUrl')) {
            $card->setResultUrl($posted->getString('resultUrl'));
        }
        if ($posted->has('cardSet')) {
            $card->setCardSet($this->cardSetRepository->find((int) $posted->getString('cardSet')));
        }
        if ($posted->has('doc') || $posted->has('newDoc')) {
            $card->setDoc($this->chosenDoc($card, $request));
        }

        return $card;
    }

    // The file chosen for the card among its project's, a new one when a title is typed for it, none when emptied
    private function chosenDoc(Card $card, Request $request): ?Doc
    {
        $title = trim($request->request->getString('newDoc'));
        if ('' !== $title) {
            return $this->docRepository->newFile($card->getProject(), $title);
        }
        // « None » posts an empty value, which getInt() refuses
        $id = (int) $request->request->getString('doc');
        $doc = 0 < $id ? $this->docRepository->find($id) : null;

        return null !== $doc && $doc->getProject() === $card->getProject() ? $doc : null;
    }

    // A file is the business of its space's contributors, one of no space the admin's
    private function guardDoc(Doc $doc, string $level): void
    {
        $project = $doc->getProject();
        if (null === $project) {
            $this->denyUnlessAdmin();

            return;
        }
        $this->guard($project, $level);
    }

    // Parameter values posted with an action, empty ones dropped
    /** @return array<string, string> */
    private function params(Request $request): array
    {
        $params = [];
        foreach ($request->request->all('params') as $name => $value) {
            if (\is_string($name) && \is_string($value) && '' !== trim($value)) {
                $params[$name] = trim($value);
            }
        }

        return $params;
    }

    // A posted date, null for an empty or unreadable one
    private function day(string $value): ?\DateTimeImmutable
    {
        $day = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return false === $day ? null : $day;
    }

    // The page the gesture was made on when it is one of ours, the fallback otherwise
    private function back(Request $request, string $fallback): string
    {
        $referer = (string) $request->headers->get('referer');
        $host = $request->getSchemeAndHttpHost();

        return str_starts_with($referer, $host . '/') ? $referer : $fallback;
    }
}
