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
use c975L\PilotBundle\Repository\MachineRepository;
use c975L\PilotBundle\Service\DocIngester;
use c975L\PilotBundle\Service\ReportIngester;
use c975L\PilotBundle\Service\RunOutputs;
use c975L\PilotBundle\Service\RunQueue;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

use function Symfony\Component\Translation\t;

// Runner protocol, a machine presenting its token as a Bearer: claim a run, report its progress, send its files, finish it
#[Route('/api/pilot/runner', name: 'pilot_runner_', methods: ['POST'], format: 'json')]
class RunnerController extends AbstractController
{
    // The limiter counting the unknown tokens, by address
    public const string DENIED_LIMITER = 'c975l_pilot_runner_denied';

    public function __construct(
        private readonly MachineRepository $machineRepository,
        private readonly RunQueue $runQueue,
        private readonly RunOutputs $runOutputs,
        private readonly ReportIngester $reportIngester,
        private readonly DocIngester $docIngester,
        #[Autowire(service: 'limiter.' . self::DENIED_LIMITER)]
        private readonly RateLimiterFactoryInterface $deniedLimiter,
    ) {
    }

    // Next run for the calling machine, 204 when there is none; the call also says the machine is alive and which actions it runs
    #[Route('/claim', name: 'claim')]
    public function claim(Request $request): Response
    {
        $machine = $this->machine($request);
        if (null === $machine) {
            return $this->denied($request);
        }
        $payload = $request->getPayload()->all();
        $only = \is_array($payload['only'] ?? null) ? array_values(array_filter($payload['only'], is_string(...))) : null;
        $run = $this->runQueue->claim($machine, $this->capabilities($payload['actions'] ?? []), $only);
        // How many runs the machine takes at once, told at every call so that a runner follows its settings without restarting
        $headers = ['X-Pilot-Parallel' => (string) $machine->getParallel()];
        if (null === $run) {
            return new Response(null, Response::HTTP_NO_CONTENT, $headers);
        }
        $card = $run->getCard();

        return $this->json([
            'id' => $run->getId(),
            'card' => $card->getId(),
            'action' => $run->getAction(),
            'params' => $run->getParams(),
            'title' => $card->getTitle(),
            'note' => $card->getNote() ?? '',
            'due' => $card->getDue()?->format('Y-m-d') ?? '',
            'project' => $card->getProject()->getSlug(),
            'doc' => $card->getDoc()?->getSlug() ?? '',
            'docMarkdown' => $card->getDoc()?->toMarkdown() ?? '',
            'lease' => Run::LEASE_SECONDS,
            'maxBytes' => RunOutputs::MAX_BYTES,
        ], Response::HTTP_OK, $headers);
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
            if (null !== $path && !$this->reportIngester->ingest($name, $path, $run->getCard()->getProject(), $machine)) {
                $this->docIngester->ingest($name, $path, $run->getCard());
            }
        });
    }

    // End of a held run with its status, message, produced files, the title it gave its card, the feedback it left open and what it spent ({"spent": {"credit-key": 120}})
    #[Route('/runs/{id}/finish', name: 'finish', requirements: ['id' => '\d+'])]
    public function finish(Run $run, Request $request): JsonResponse
    {
        $payload = $request->getPayload()->all();
        $status = RunStatus::tryFrom(\is_string($payload['status'] ?? null) ? $payload['status'] : '');
        if (null === $status) {
            return $this->json(['error' => 'flow.unknown_status'], Response::HTTP_BAD_REQUEST);
        }
        $outputs = array_values(array_filter((array) ($payload['outputs'] ?? []), is_string(...)));

        return $this->guard($request, fn (Machine $machine) => $this->runQueue->finish($run, $machine, $status, $this->text($payload['message'] ?? null), $outputs, $this->text($payload['title'] ?? null), $this->left($payload['feedback'] ?? null), $this->text($payload['result'] ?? null), $this->spent($payload['spent'] ?? null), array_values(array_filter((array) ($payload['files'] ?? []), is_string(...)))));
    }

    // The feedback the run left open, by its number in the comment it was handed, with the reason: {"2": "reason"}
    /** @return array<int, string> */
    private function left(mixed $feedback): array
    {
        $left = [];
        foreach (\is_array($feedback) ? $feedback : [] as $number => $reason) {
            if (is_numeric($number) && \is_string($reason) && '' !== trim($reason)) {
                $left[(int) $number] = mb_substr(trim($reason), 0, 255);
            }
        }

        return $left;
    }

    // What the run spent by credit key, each amount a positive whole number
    /** @return array<string, int> */
    private function spent(mixed $spent): array
    {
        $amounts = [];
        foreach (\is_array($spent) ? $spent : [] as $key => $amount) {
            if (\is_string($key) && 1 === preg_match('/^[a-z0-9-]{1,50}$/', $key) && \is_int($amount) && $amount > 0) {
                $amounts[$key] = $amount;
            }
        }

        return $amounts;
    }

    // The machine whose token the request carries, null without one, for an unknown one, or for an address past its ceiling of unknown tokens, refused before its token is even looked up so that guessing one is cut short
    private function machine(Request $request): ?Machine
    {
        if (0 === $this->deniedLimiter->create((string) $request->getClientIp())->consume(0)->getRemainingTokens()) {
            return null;
        }

        return $this->machineRepository->findOneByBearer($request->headers->get('Authorization'));
    }

    // Runs a queue gesture for the calling machine, a refused one answering 409 so the runner drops the run
    /** @param callable(Machine): void $gesture */
    private function guard(Request $request, callable $gesture): JsonResponse
    {
        $machine = $this->machine($request);
        if (null === $machine) {
            return $this->denied($request);
        }
        try {
            $gesture($machine);
        } catch (FlowException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_CONFLICT);
        }

        return $this->json(['ok' => true]);
    }

    // Answer to a request without a known token, counted against its address, 429 once the address is past its ceiling
    private function denied(Request $request): JsonResponse
    {
        $limit = $this->deniedLimiter->create((string) $request->getClientIp())->consume();
        if (!$limit->isAccepted()) {
            return $this->json(['error' => 'Too many unknown tokens.'], Response::HTTP_TOO_MANY_REQUESTS, ['Retry-After' => (string) max(1, $limit->getRetryAfter()->getTimestamp() - time())]);
        }

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
