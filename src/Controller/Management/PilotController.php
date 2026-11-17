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
use c975L\PilotBundle\Entity\Credit;
use c975L\PilotBundle\Entity\Doc;
use c975L\PilotBundle\Entity\Machine;
use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Entity\Report;
use c975L\PilotBundle\Entity\Run;
use c975L\PilotBundle\Exception\FlowException;
use c975L\PilotBundle\Repository\ActionRepository;
use c975L\PilotBundle\Repository\CardRepository;
use c975L\PilotBundle\Repository\CreditRepository;
use c975L\PilotBundle\Repository\DocRepository;
use c975L\PilotBundle\Repository\MachineRepository;
use c975L\PilotBundle\Repository\PlanTemplateRepository;
use c975L\PilotBundle\Repository\ProjectRepository;
use c975L\PilotBundle\Repository\ReportRepository;
use c975L\PilotBundle\Repository\RunRepository;
use c975L\PilotBundle\Service\CardFlow;
use c975L\PilotBundle\Service\PlanFactory;
use c975L\PilotBundle\Service\Planning;
use c975L\PilotBundle\Service\ProjectOrder;
use c975L\PilotBundle\Service\RunOutputs;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Psr\Clock\ClockInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Contracts\Translation\TranslatorInterface;

// The three ways in: the planning, a project with its backlog, a search; plus the page of a card and the gestures on it
class PilotController extends AbstractController
{
    // EasyAdmin prefixes the names with the Dashboard's, which gives management_pilot_XXX
    public const string WEEK_ROUTE = 'management_pilot_week';
    public const string PROJECT_ROUTE = 'management_pilot_project';
    public const string CARD_ROUTE = 'management_pilot_card';
    public const string SEARCH_ROUTE = 'management_pilot_search';
    public const string GESTURE_ROUTE = 'management_pilot_card_gesture';
    public const string ORDER_ROUTE = 'management_pilot_projects_order';
    public const string NEW_CARD_ROUTE = 'management_pilot_card_new';
    public const string REPORT_ROUTE = 'management_pilot_report_run';
    public const string DIALOG_HEADER = 'X-Pilot-Dialog';
    public const string CARDS_ORDER_ROUTE = 'management_pilot_cards_order';
    public const string OBJECTIVES_ROUTE = 'management_pilot_project_objectives';

    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly EntityManagerInterface $entityManager,
        private readonly CardRepository $cardRepository,
        private readonly ReportRepository $reportRepository,
        private readonly DocRepository $docRepository,
        private readonly ProjectRepository $projectRepository,
        private readonly ClockInterface $clock,
        private readonly CardFlow $cardFlow,
        private readonly TranslatorInterface $translator,
        private readonly ActionRepository $actionRepository,
    ) {
    }

    // Late cards, the coming days up to the horizon, then the later ones by month
    #[AdminRoute(path: '/pilot', name: 'pilot_week')]
    public function week(Planning $planning, CreditRepository $creditRepository, RunRepository $runRepository): Response
    {
        $this->denyAccess();

        return $this->page('@c975LPilot/management/week.html.twig', $planning->build() + ['credits' => $creditRepository->findOrdered(), 'runs' => $runRepository->findActive(), 'reports' => $this->reportRepository->findShownOn(null), 'waiting' => $this->cardRepository->findWaiting()]);
    }

    // The balance of a service typed in by hand, from the planning
    #[AdminRoute(path: '/pilot/credit/{id}', name: 'pilot_credit', options: ['methods' => ['POST'], 'requirements' => ['id' => '\d+']])]
    public function credit(Credit $credit, Request $request): RedirectResponse
    {
        $this->denyAccess();
        $remaining = $request->request->getString('remaining');
        if ($credit->isManual() && 1 === preg_match('/^\d{1,9}$/', $remaining) && $this->isCsrfTokenValid('pilot_credit' . $credit->getId(), $request->request->getString('_token'))) {
            $credit->setRemaining((int) $remaining, $this->clock->now());
            $this->entityManager->flush();
        }

        return $this->redirectToRoute(self::WEEK_ROUTE);
    }

    // A project: its objectives, its open cards (dated, then the backlog), its files, its done cards folded
    #[AdminRoute(path: '/pilot/project/{slug}', name: 'pilot_project')]
    public function project(#[MapEntity(mapping: ['slug' => 'slug'])] Project $project, PlanTemplateRepository $planTemplateRepository, MachineRepository $machineRepository, RunRepository $runRepository): Response
    {
        $this->denyAccess();

        return $this->page('@c975LPilot/management/project.html.twig', [
            'project' => $project,
            'templates' => $planTemplateRepository->findOfferedTo($project),
            'actions' => $this->actionsFor($project, $machineRepository),
            'runs' => $runRepository->findActive($project),
            'reports' => $this->reportRepository->findShownOn($project),
            'cards' => $this->cardRepository->findOpen($project),
            'docs' => $this->docRepository->findBy(['project' => $project], ['title' => 'ASC']),
            'done' => $this->cardRepository->findDone($project),
        ]);
    }

    // The project's objectives opened for writing in the dialog, their document created the first time
    #[AdminRoute(path: '/pilot/project/{slug}/objectives', name: 'pilot_project_objectives', options: ['methods' => ['POST']])]
    public function objectives(#[MapEntity(mapping: ['slug' => 'slug'])] Project $project, Request $request): RedirectResponse
    {
        $this->denyAccess();
        $url = $this->generateUrl(self::PROJECT_ROUTE, ['slug' => $project->getSlug()]);
        if (!$this->isCsrfTokenValid('pilot_project_objectives' . $project->getId(), $request->request->getString('_token'))) {
            return $this->redirect($url);
        }
        $doc = $this->docRepository->objectivesOf($project, $this->translator->trans('label.objectives', [], 'pilot'));
        $this->entityManager->flush();

        return $this->redirect($url . '#doc-' . $doc->getSlug());
    }

    // A recurring action launched from the project's page: a new card of the project, dated today, handed to the machine at once
    #[AdminRoute(path: '/pilot/project/{slug}/run', name: 'pilot_project_run', options: ['methods' => ['POST']])]
    public function run(#[MapEntity(mapping: ['slug' => 'slug'])] Project $project, Request $request): RedirectResponse
    {
        $this->denyAccess();
        $key = $request->request->getString('action');
        $action = $this->actionRepository->findOneBy(['key' => $key]);
        if (null === $action || $action->isCardOnly() || !$action->isOfferedTo($project) || !$this->isCsrfTokenValid('pilot_project_run' . $project->getId(), $request->request->getString('_token'))) {
            return $this->redirectToRoute(self::PROJECT_ROUTE, ['slug' => $project->getSlug()]);
        }
        $today = $this->clock->now()->setTime(0, 0);
        $card = new Card($project, $action->getLabel())->setDue($today);
        $this->entityManager->persist($card);
        $this->entityManager->persist($this->cardFlow->entrust($card, $key, $this->params($request)));
        $this->entityManager->flush();

        return $this->redirectToRoute(self::CARD_ROUTE, ['id' => $card->getId()]);
    }

    // An action of a report, on one of its rows or on the whole table: a card dated today on the page's project when the row is shown there, the report's project otherwise, handed to the machine at once
    #[AdminRoute(path: '/pilot/report/{id}/run', name: 'pilot_report_run', options: ['methods' => ['POST'], 'requirements' => ['id' => '\d+']])]
    public function reportRun(Report $report, Request $request): RedirectResponse
    {
        $this->denyAccess();
        $row = $request->request->has('row') ? $request->request->getInt('row') : null;
        $action = $this->reportAction($report, $request, $row);
        if (null !== $action) {
            $project = $this->projectRepository->find($request->request->getInt('project'));
            if (null === $project || (null !== $row && !\in_array($row, $report->rowsFor($project), true))) {
                $project = $report->getProject();
            }
            $title = null === $row ? $action['label'] : $action['label'] . ' · ' . (string) array_values($report->getRows()[$row])[0];
            $card = new Card($project, mb_substr($title, 0, 500))->setDue($this->clock->now()->setTime(0, 0));
            $this->entityManager->persist($card);
            $this->entityManager->persist($this->cardFlow->entrust($card, $action['action'], $report->params($action, $row, $this->params($request))));
            $this->entityManager->flush();
        }

        return $this->redirect($this->back($request, $this->generateUrl(self::WEEK_ROUTE)));
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
    #[AdminRoute(path: '/pilot/project/{slug}/plan', name: 'pilot_plan_new', options: ['methods' => ['GET', 'POST']])]
    public function newPlan(#[MapEntity(mapping: ['slug' => 'slug'])] Project $project, Request $request, PlanTemplateRepository $planTemplateRepository, PlanFactory $planFactory): Response
    {
        $this->denyAccess();
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

        return $this->page('@c975LPilot/management/plan_new.html.twig', [
            'project' => $project,
            'template' => $template,
            'variables' => $planFactory->variables($template),
        ]);
    }

    // Cards and files holding the words, from two letters
    #[AdminRoute(path: '/pilot/search', name: 'pilot_search')]
    public function search(Request $request): Response
    {
        $this->denyAccess();
        $words = trim($request->query->getString('q'));
        $searched = mb_strlen($words) >= 2;

        return $this->page('@c975LPilot/management/search.html.twig', [
            'words' => $words,
            'cards' => $searched ? $this->cardRepository->search($words) : [],
            'docs' => $searched ? $this->docRepository->search($words) : [],
        ]);
    }

    // The projects of the left column in the order they were dragged into, the token travelling as a header
    #[AdminRoute(path: '/pilot/projects/order', name: 'pilot_projects_order', options: ['methods' => ['POST']])]
    public function order(Request $request, ProjectOrder $projectOrder): Response
    {
        $this->denyAccess();
        if (!$this->isCsrfTokenValid(self::ORDER_ROUTE, $request->headers->get('X-CSRF-Token'))) {
            return new Response(null, Response::HTTP_FORBIDDEN);
        }
        $ids = array_values(array_map(intval(...), array_filter($request->request->all('ids'), is_numeric(...))));
        $projectOrder->apply($this->projectRepository->findOrdered(), $ids);
        $this->entityManager->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    // Cards put in the order they were dragged into, inside a backlog or a day, the token travelling as a header
    #[AdminRoute(path: '/pilot/cards/order', name: 'pilot_cards_order', options: ['methods' => ['POST']])]
    public function cardsOrder(Request $request): Response
    {
        $this->denyAccess();
        if (!$this->isCsrfTokenValid(self::CARDS_ORDER_ROUTE, $request->headers->get('X-CSRF-Token'))) {
            return new Response(null, Response::HTTP_FORBIDDEN);
        }
        $ids = array_values(array_map(intval(...), array_filter($request->request->all('ids'), is_numeric(...))));
        $cards = [];
        foreach ($this->cardRepository->findBy(['id' => $ids]) as $card) {
            $cards[(int) $card->getId()] = $card;
        }
        foreach ($ids as $position => $id) {
            ($cards[$id] ?? null)?->setRank($position);
        }
        $this->entityManager->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    // Everything about a card: note, checklist with what its steps spend against the balances, file, runs
    #[AdminRoute(path: '/pilot/card/{id}', name: 'pilot_card', options: ['requirements' => ['id' => '\d+']])]
    public function card(Card $card, MachineRepository $machineRepository, RunOutputs $runOutputs, CreditRepository $creditRepository): Response
    {
        $this->denyAccess();
        $run = $card->lastRun();
        $keys = array_values(array_unique(array_filter(array_map(static fn (array $step): ?string => $step['cost']['credit'] ?? null, $card->getChecklist()))));

        return $this->page('@c975LPilot/management/card.html.twig', [
            'card' => $card,
            'run' => $run,
            'outputs' => null === $run ? [] : $runOutputs->previews($run),
            'actions' => $this->actionsFor($card->getProject(), $machineRepository),
            'docs' => $this->docRepository->findFiles($card->getProject()),
            'credits' => [] === $keys ? [] : array_column(array_map(static fn (Credit $credit): array => [$credit->getKey(), $credit], $creditRepository->findBy(['key' => $keys])), 1, 0),
        ]);
    }

    // The actions offered on the project that a machine online runs, each with that machine
    /** @return list<array{key: string, label: string, params: list<string>, machine: string, card: bool, help: ?string}> */
    private function actionsFor(Project $project, MachineRepository $machineRepository): array
    {
        $runners = [];
        foreach ($machineRepository->findOnline($this->clock->now()) as $machine) {
            foreach (array_keys($machine->getCapabilities()) as $key) {
                $runners[$key] ??= $machine->getName();
            }
        }
        $actions = [];
        foreach ($this->actionRepository->findOffered($project, array_map(strval(...), array_keys($runners))) as $action) {
            $actions[] = ['key' => $action->getKey(), 'label' => $action->getLabel(), 'params' => $action->getParams(), 'machine' => $runners[$action->getKey()], 'card' => $action->isCardOnly(), 'help' => $action->getHelp()];
        }

        return $actions;
    }

    // A file on its own, a character with its portrait at full size to download (a reference for an image generator)
    #[AdminRoute(path: '/pilot/doc/{slug}', name: 'pilot_doc', options: ['requirements' => ['slug' => '[a-z0-9-]+']])]
    public function doc(#[MapEntity(mapping: ['slug' => 'slug'])] Doc $doc): Response
    {
        $this->denyAccess();

        return $this->page('@c975LPilot/management/doc.html.twig', ['doc' => $doc, 'project' => $doc->getProject()]);
    }

    // A file's title and two floors written on its page; in the dialog the answer says only whether it was kept
    #[AdminRoute(path: '/pilot/doc/{slug}/edit', name: 'pilot_doc_edit', options: ['methods' => ['POST'], 'requirements' => ['slug' => '[a-z0-9-]+']])]
    public function docEdit(#[MapEntity(mapping: ['slug' => 'slug'])] Doc $doc, Request $request): Response
    {
        $this->denyAccess();
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

        return $this->redirect($this->back($request, $this->generateUrl('management_pilot_doc', ['slug' => $doc->getSlug()])));
    }

    // A file as one markdown download, to hand over or keep
    #[AdminRoute(path: '/pilot/doc/{slug}.md', name: 'pilot_doc_markdown', options: ['requirements' => ['slug' => '[a-z0-9-]+']])]
    public function markdown(#[MapEntity(mapping: ['slug' => 'slug'])] Doc $doc): Response
    {
        $this->denyAccess();
        $response = new Response($doc->toMarkdown(), Response::HTTP_OK, ['Content-Type' => 'text/markdown; charset=UTF-8']);
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $doc->getSlug() . '.md'));

        return $response;
    }

    // The picture of a file (a character's portrait), shown under the type of its extension
    #[AdminRoute(path: '/pilot/doc/{slug}/image', name: 'pilot_doc_image', options: ['requirements' => ['slug' => '[a-z0-9-]+']])]
    public function docImage(#[MapEntity(mapping: ['slug' => 'slug'])] Doc $doc, RunOutputs $runOutputs, #[Autowire(param: 'kernel.project_dir')] string $projectDir): BinaryFileResponse
    {
        $this->denyAccess();
        $path = $projectDir . '/' . Doc::IMAGE_DIRECTORY . '/' . basename((string) $doc->getImage());
        $media = null === $doc->getImage() || !is_file($path) ? null : $runOutputs->mediaType($path);
        if (null === $media || !str_starts_with($media, 'image/')) {
            throw $this->createNotFoundException();
        }

        return new BinaryFileResponse($path, Response::HTTP_OK, ['Content-Type' => $media, 'X-Content-Type-Options' => 'nosniff']);
    }

    // A file a run produced, kept on the site: an image or a video shown under the type of its extension, anything else downloaded, so that an html or svg one runs no script on the back office's origin
    #[AdminRoute(path: '/pilot/run/{id}/{name}', name: 'pilot_run_output', options: ['requirements' => ['id' => '\d+', 'name' => '[A-Za-z0-9._-]+']])]
    public function output(Run $run, string $name, RunOutputs $runOutputs): BinaryFileResponse
    {
        $this->denyAccess();
        $path = $runOutputs->path($run, $name);
        if (null === $path) {
            throw $this->createNotFoundException();
        }
        $media = $runOutputs->mediaType($path);
        $headers = ['Content-Type' => $media ?? 'application/octet-stream', 'Content-Security-Policy' => 'sandbox', 'X-Content-Type-Options' => 'nosniff'];

        return new BinaryFileResponse($path, Response::HTTP_OK, $headers, true, null === $media ? ResponseHeaderBag::DISPOSITION_ATTACHMENT : null);
    }

    // Done, back to do, a date (empty sends it back to the backlog), its title and note, a step ticked, or a machine gesture; then back where the gesture came from
    #[AdminRoute(path: '/pilot/card/{id}/{gesture}', name: 'pilot_card_gesture', options: ['methods' => ['POST'], 'requirements' => ['id' => '\d+', 'gesture' => 'done|reopen|schedule|edit|step|entrust|launch|cancel|validate|sendback']])]
    public function gesture(Card $card, string $gesture, Request $request): Response
    {
        $this->denyAccess();
        $refusal = $this->isCsrfTokenValid(self::GESTURE_ROUTE . $card->getId(), $request->request->getString('_token')) ? null : $this->translator->trans('label.gesture_expired', [], 'pilot');
        if (null === $refusal) {
            try {
                $this->apply($card, $gesture, $request);
                $this->entityManager->flush();
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

    // A card added in one line, to a project, dated or in the backlog; then back to the page it was added from
    #[AdminRoute(path: '/pilot/card/new', name: 'pilot_card_new', options: ['methods' => ['POST']])]
    public function newCard(Request $request): RedirectResponse
    {
        $this->denyAccess();
        $project = $this->projectRepository->find($request->request->getInt('project'));
        $title = trim($request->request->getString('title'));
        if (null !== $project && '' !== $title && $this->isCsrfTokenValid(self::NEW_CARD_ROUTE, $request->request->getString('_token'))) {
            $this->entityManager->persist(new Card($project, $title)->setDue($this->day($request->request->getString('due'))));
            $this->entityManager->flush();
        }

        return $this->redirect($this->back($request, $this->generateUrl(self::WEEK_ROUTE)));
    }

    // One gesture on a card, a run it creates being persisted
    private function apply(Card $card, string $gesture, Request $request): void
    {
        $run = match ($gesture) {
            'done' => $this->cardFlow->complete($card),
            'reopen' => $card->markDone(null),
            'schedule' => $card->setDue($this->day($request->request->getString('due'))),
            'edit' => $this->edit($card, $request),
            'step' => $this->cardFlow->tick($card, $request->request->getInt('index', -1)),
            'entrust' => $this->cardFlow->entrust($card, $request->request->getString('action'), $this->params($request)),
            'launch' => $this->cardFlow->launch($card),
            'cancel' => $this->cardFlow->cancel($card),
            'validate' => $this->cardFlow->validate($card),
            default => $this->cardFlow->sendBack($card, $request->request->getString('comment')),
        };
        if ($run instanceof Run) {
            $this->entityManager->persist($run);
        }
    }

    // The title, the note, who an answer is awaited from and the file, set on the card's page, an empty title keeping the one it had
    private function edit(Card $card, Request $request): Card
    {
        $title = trim($request->request->getString('title'));
        $note = trim($request->request->getString('note'));

        return $card->setTitle('' !== $title ? $title : $card->getTitle())->setNote('' !== $note ? $note : null)->setWaitingOn($request->request->getString('waitingOn'))->setDoc($this->chosenDoc($card, $request));
    }

    // The file chosen for the card among its project's, a new one when a title is typed for it, none when emptied
    private function chosenDoc(Card $card, Request $request): ?Doc
    {
        $title = trim($request->request->getString('newDoc'));
        if ('' !== $title) {
            return $this->docRepository->newFile($card->getProject(), $title);
        }
        $doc = $this->docRepository->find($request->request->getInt('doc'));

        return null !== $doc && $doc->getProject() === $card->getProject() ? $doc : null;
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

    // A piloting page, with the projects and their open cards for the column on the left
    /** @param array<string, mixed> $parameters */
    private function page(string $view, array $parameters): Response
    {
        return $this->render($view, $parameters + [
            'pilot_projects' => $this->projectRepository->findOrdered(),
            'pilot_open' => $this->cardRepository->countOpenByProject(),
        ]);
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

    // The page the gesture was made on when it is one of ours, the fallback otherwise
    private function back(Request $request, string $fallback): string
    {
        $referer = (string) $request->headers->get('referer');
        $host = $request->getSchemeAndHttpHost();

        return str_starts_with($referer, $host . '/') ? $referer : $fallback;
    }
}
