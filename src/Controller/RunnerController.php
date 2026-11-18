<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Controller;

use c975L\PilotBundle\Entity\Machine;
use c975L\PilotBundle\Entity\Run;
use c975L\PilotBundle\Enum\RunStatus;
use c975L\PilotBundle\Exception\FlowException;
use c975L\PilotBundle\Repository\CreditRepository;
use c975L\PilotBundle\Repository\MachineRepository;
use c975L\PilotBundle\Service\DocIngester;
use c975L\PilotBundle\Service\ReportIngester;
use c975L\PilotBundle\Service\RunOutputs;
use c975L\PilotBundle\Service\RunQueue;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use function Symfony\Component\Translation\t;

// Runner protocol, a machine presenting its token as a Bearer: claim a run, report its progress, send its files, finish it
#[Route('/api/pilot/runner', name: 'pilot_runner_', methods: ['POST'], format: 'json')]
class RunnerController extends AbstractController
{
    public function __construct(
        private readonly MachineRepository $machineRepository,
        private readonly RunQueue $runQueue,
        private readonly RunOutputs $runOutputs,
        private readonly ReportIngester $reportIngester,
        private readonly DocIngester $docIngester,
    ) {
    }

    // Next run for the calling machine, 204 when there is none; the call also says the machine is alive and which actions it runs
    #[Route('/claim', name: 'claim')]
    public function claim(Request $request): Response
    {
        $machine = $this->machine($request);
        if (null === $machine) {
            return $this->denied();
        }
        $run = $this->runQueue->claim($machine, $this->capabilities($request->getPayload()->all()['actions'] ?? []));
        if (null === $run) {
            return new Response(null, Response::HTTP_NO_CONTENT);
        }
        $card = $run->getCard();

        return $this->json([
            'id' => $run->getId(),
            'card' => $card->getId(),
            'action' => $run->getAction(),
            'params' => $run->getParams(),
            'title' => $card->getTitle(),
            'note' => $card->getNote() ?? '',
            'project' => $card->getProject()->getSlug(),
            'doc' => $card->getDoc()?->getSlug() ?? '',
            'docMarkdown' => $card->getDoc()?->toMarkdown() ?? '',
            'lease' => Run::LEASE_SECONDS,
            'maxBytes' => RunOutputs::MAX_BYTES,
        ]);
    }

    // Progress of a held run, which renews its lease; without fields it is a plain heartbeat
    #[Route('/runs/{id}/progress', name: 'progress', requirements: ['id' => '\d+'])]
    public function progress(Run $run, Request $request): JsonResponse
    {
        $payload = $request->getPayload();

        return $this->guard($request, fn (Machine $machine) => $this->runQueue->report($run, $machine, $payload->has('progress') ? (int) $payload->get('progress') : null, $this->text($payload->get('message'))));
    }

    // One file a held run produced, sent as the "file" field of a multipart request; a <key>.report.json file also refreshes that report, a <slug>.doc.md file writes that file and attaches it to the card
    #[Route('/runs/{id}/output', name: 'output', requirements: ['id' => '\d+'])]
    public function output(Run $run, Request $request): JsonResponse
    {
        $file = $request->files->get('file');

        return $this->guard($request, function (Machine $machine) use ($run, $file): void {
            $this->runQueue->expectHeld($run, $machine);
            if (!$file instanceof UploadedFile || !$file->isValid() || $file->getSize() > RunOutputs::MAX_BYTES) {
                throw new FlowException(t('flow.file_refused', [], 'pilot'));
            }
            $name = $this->runOutputs->store($run, $file);
            $path = $this->runOutputs->path($run, $name);
            if (null !== $path && !$this->reportIngester->ingest($name, $path, $run->getCard()->getProject())) {
                $this->docIngester->ingest($name, $path, $run->getCard());
            }
        });
    }

    // End of a held run with its status, message, produced files and log tail
    #[Route('/runs/{id}/finish', name: 'finish', requirements: ['id' => '\d+'])]
    public function finish(Run $run, Request $request): JsonResponse
    {
        $payload = $request->getPayload()->all();
        $status = RunStatus::tryFrom(\is_string($payload['status'] ?? null) ? $payload['status'] : '');
        if (null === $status) {
            return $this->json(['error' => 'flow.unknown_status'], Response::HTTP_BAD_REQUEST);
        }
        $outputs = array_values(array_filter((array) ($payload['outputs'] ?? []), is_string(...)));

        return $this->guard($request, fn (Machine $machine) => $this->runQueue->finish($run, $machine, $status, $this->text($payload['message'] ?? null), $outputs, $this->text($payload['log'] ?? null)));
    }

    // Balances of paid services the machine read, sent apart from any run: {"credits": {"key": {"label", "remaining", "total", "unit", "renewsOn"}}}
    #[Route('/credits', name: 'credits')]
    public function credits(Request $request, CreditRepository $creditRepository, EntityManagerInterface $entityManager, ClockInterface $clock): JsonResponse
    {
        if (null === $this->machine($request)) {
            return $this->denied();
        }
        $reports = [];
        $credits = $request->getPayload()->all()['credits'] ?? [];
        foreach (\is_array($credits) ? $credits : [] as $key => $credit) {
            if (\is_string($key) && 1 === preg_match('/^[a-z0-9-]{1,50}$/', $key) && \is_array($credit)) {
                $reports[$key] = $this->report($key, $credit);
            }
        }
        $creditRepository->report($reports, $clock->now());
        $entityManager->flush();

        return $this->json(['ok' => true]);
    }

    // One reported balance, its fields typed and bounded
    /**
     * @param array<mixed> $credit
     *
     * @return array{label: string, remaining: ?int, total: ?int, unit: ?string, renewsOn: ?\DateTimeImmutable}
     */
    private function report(string $key, array $credit): array
    {
        $renews = \is_string($credit['renewsOn'] ?? null) ? \DateTimeImmutable::createFromFormat('!Y-m-d', substr($credit['renewsOn'], 0, 10)) : false;

        return [
            'label' => mb_substr(\is_string($credit['label'] ?? null) ? $credit['label'] : $key, 0, 100),
            'remaining' => \is_int($credit['remaining'] ?? null) ? max(0, $credit['remaining']) : null,
            'total' => \is_int($credit['total'] ?? null) ? max(0, $credit['total']) : null,
            'unit' => \is_string($credit['unit'] ?? null) ? mb_substr($credit['unit'], 0, 30) : null,
            'renewsOn' => false === $renews ? null : $renews,
        ];
    }

    // The machine whose token the request carries, null without one or for an unknown one
    private function machine(Request $request): ?Machine
    {
        $header = (string) $request->headers->get('Authorization');

        return str_starts_with($header, 'Bearer ') ? $this->machineRepository->findOneByToken(substr($header, 7)) : null;
    }

    // Runs a queue gesture for the calling machine, a refused one answering 409 so the runner drops the run
    /** @param callable(Machine): void $gesture */
    private function guard(Request $request, callable $gesture): JsonResponse
    {
        $machine = $this->machine($request);
        if (null === $machine) {
            return $this->denied();
        }
        try {
            $gesture($machine);
        } catch (FlowException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_CONFLICT);
        }

        return $this->json(['ok' => true]);
    }

    // Answer to a request without a known token
    private function denied(): JsonResponse
    {
        return $this->json(['error' => 'Unknown machine.'], Response::HTTP_UNAUTHORIZED);
    }

    // The actions a machine declares, key => label and parameter names, anything malformed dropped
    /** @return array<string, array{label: string, params: list<string>, card?: bool, help?: ?string}> */
    private function capabilities(mixed $actions): array
    {
        $capabilities = [];
        foreach (\is_array($actions) ? $actions : [] as $key => $action) {
            if (\is_string($key) && 1 === preg_match('/^[a-z0-9_-]{1,50}$/', $key) && \is_array($action)) {
                $capabilities[$key] = [
                    'label' => mb_substr(\is_string($action['label'] ?? null) ? $action['label'] : $key, 0, 100),
                    'params' => $this->names($action['params'] ?? [], '/^\w{1,30}$/'),
                    'card' => true === ($action['card'] ?? false),
                    'help' => \is_string($action['help'] ?? null) ? mb_substr(trim($action['help']), 0, 255) : null,
                ];
            }
        }

        return $capabilities;
    }

    // The strings of a list matching a pattern
    /** @return list<string> */
    private function names(mixed $values, string $pattern): array
    {
        return array_values(array_filter(\is_array($values) ? $values : [], static fn (mixed $value): bool => \is_string($value) && 1 === preg_match($pattern, $value)));
    }

    // A string field kept to its last 20000 characters, null when absent or not a string
    private function text(mixed $value): ?string
    {
        return \is_string($value) ? mb_substr($value, -20000) : null;
    }
}
