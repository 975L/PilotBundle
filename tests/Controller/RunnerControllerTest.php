<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Controller;

use c975L\PilotBundle\Controller\RunnerController;
use c975L\PilotBundle\Entity\Card;
use c975L\PilotBundle\Entity\Machine;
use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Enum\RunStatus;
use c975L\PilotBundle\Repository\MachineRepository;
use c975L\PilotBundle\Service\CardFlow;
use c975L\PilotBundle\Service\DocIngester;
use c975L\PilotBundle\Service\ReportIngester;
use c975L\PilotBundle\Service\RunOutputs;
use c975L\PilotBundle\Service\RunQueue;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

class RunnerControllerTest extends TestCase
{
    // A claimed run carries its card's context, its date included, empty for a card of the backlog, and its requester, empty when nobody signed in asked
    public function testClaimCarriesTheCardsDate(): void
    {
        $this->assertSame('', $this->claimed(new Card(new Project('catalog', 'Catalog'), 'item-1'))['due']);

        $claimed = $this->claimed(new Card(new Project('catalog', 'Catalog'), 'item-1')->setDue(new \DateTimeImmutable('2026-12-03')));
        $this->assertSame('2026-12-03', $claimed['due']);
        $this->assertSame('item-1', $claimed['title']);
        $this->assertSame('', $claimed['requester']);
    }

    // Every claim answer tells how many runs the machine takes at once, the actions the runner can start now handed to the queue
    public function testClaimTellsTheParallelRuns(): void
    {
        $machines = $this->createStub(MachineRepository::class);
        $machines->method('findOneByBearer')->willReturn(new Machine('machine-1', '')->setParallel(3));
        $queue = $this->createMock(RunQueue::class);
        $queue->expects($this->once())->method('claim')->with($this->anything(), [], ['item-1'])->willReturn(null);
        $controller = new RunnerController($machines, $queue, $this->createStub(RunOutputs::class), $this->createStub(ReportIngester::class), $this->createStub(DocIngester::class), $this->limiter());
        $controller->setContainer(new Container());
        $request = Request::create('/api/pilot/runner/claim', 'POST', server: ['HTTP_AUTHORIZATION' => 'Bearer token-1', 'CONTENT_TYPE' => 'application/json'], content: '{"actions":{},"only":["item-1",7]}');

        $response = $controller->claim($request);
        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('3', $response->headers->get('X-Pilot-Parallel'));
    }

    // What a run spent reaches the queue by credit key, an amount that is no positive whole number or a malformed key dropped
    public function testFinishCarriesWhatWasSpent(): void
    {
        $machines = $this->createStub(MachineRepository::class);
        $machines->method('findOneByBearer')->willReturn(new Machine('machine-1', ''));
        $queue = $this->createMock(RunQueue::class);
        $queue->expects($this->once())->method('finish')->with($this->anything(), $this->anything(), RunStatus::Done, null, [], null, [], null, ['item-1' => 120]);
        $controller = new RunnerController($machines, $queue, $this->createStub(RunOutputs::class), $this->createStub(ReportIngester::class), $this->createStub(DocIngester::class), $this->limiter());
        $controller->setContainer(new Container());
        $run = new CardFlow(new MockClock('2026-11-20 10:00'))->entrust(new Card(new Project('catalog', 'Catalog'), 'item-1'), 'hello', [], 'Hello');
        $request = Request::create('/api/pilot/runner/runs/1/finish', 'POST', server: ['HTTP_AUTHORIZATION' => 'Bearer token-1', 'CONTENT_TYPE' => 'application/json'], content: '{"status":"done","spent":{"item-1":120,"item-2":-5,"Bad Key":3,"item-3":"7"}}');

        $this->assertSame(200, $controller->finish($run, $request)->getStatusCode());
    }

    // An unknown token is answered 401 and counted against its address, which past its ceiling is answered 429, a right token included, without its token looked up
    public function testUnknownTokensCutShort(): void
    {
        $machine = new Machine('machine-1', '');
        $machines = $this->createStub(MachineRepository::class);
        $machines->method('findOneByBearer')->willReturnCallback(static fn (?string $bearer): ?Machine => 'Bearer token-1' === $bearer ? $machine : null);
        $controller = new RunnerController($machines, $this->createStub(RunQueue::class), $this->createStub(RunOutputs::class), $this->createStub(ReportIngester::class), $this->createStub(DocIngester::class), $this->limiter(2));
        $controller->setContainer(new Container());
        $claim = static fn (string $token): Request => Request::create('/api/pilot/runner/claim', 'POST', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'CONTENT_TYPE' => 'application/json', 'REMOTE_ADDR' => '192.0.2.1'], content: '{"actions":{}}');

        $this->assertSame([401, 401, 429], array_map(static fn (string $token): int => $controller->claim($claim($token))->getStatusCode(), ['guess-1', 'guess-2', 'guess-3']));
        $this->assertSame(429, $controller->claim($claim('token-1'))->getStatusCode());
    }

    // A file past the run's ceilings of files or bytes is answered 413, never kept
    public function testOutputPastTheRunsCeilingRefused(): void
    {
        $machines = $this->createStub(MachineRepository::class);
        $machines->method('findOneByBearer')->willReturn(new Machine('machine-1', ''));
        $outputs = $this->createMock(RunOutputs::class);
        $outputs->method('fits')->willReturn(false);
        $outputs->expects($this->never())->method('store');
        $controller = new RunnerController($machines, $this->createStub(RunQueue::class), $outputs, $this->createStub(ReportIngester::class), $this->createStub(DocIngester::class), $this->limiter());
        $controller->setContainer(new Container());
        $path = (string) tempnam(sys_get_temp_dir(), 'pilot');
        file_put_contents($path, 'item-1');
        $run = new CardFlow(new MockClock('2026-11-20 10:00'))->entrust(new Card(new Project('catalog', 'Catalog'), 'item-1'), 'hello', [], 'Hello');
        $request = Request::create('/api/pilot/runner/runs/1/output', 'POST', server: ['HTTP_AUTHORIZATION' => 'Bearer token-1'], files: ['file' => new UploadedFile($path, 'item-1.txt', null, null, true)]);

        try {
            $controller->output($run, $request);
            $this->fail('A file past the ceiling was kept.');
        } catch (HttpException $exception) {
            $this->assertSame(413, $exception->getStatusCode());
        } finally {
            @unlink($path);
        }
    }

    // The limiter of the unknown tokens, in memory
    private function limiter(int $limit = 20): RateLimiterFactory
    {
        return new RateLimiterFactory(['id' => RunnerController::DENIED_LIMITER, 'policy' => 'fixed_window', 'limit' => $limit, 'interval' => '1 hour'], new InMemoryStorage());
    }

    // The claim answer for a run of the card, decoded
    /** @return array<string,mixed> */
    private function claimed(Card $card): array
    {
        $machines = $this->createStub(MachineRepository::class);
        $machines->method('findOneByBearer')->willReturn(new Machine('machine-1', ''));
        $queue = $this->createStub(RunQueue::class);
        $queue->method('claim')->willReturn(new CardFlow(new MockClock('2026-11-20 10:00'))->entrust($card, 'hello', [], 'Hello'));
        $controller = new RunnerController($machines, $queue, $this->createStub(RunOutputs::class), $this->createStub(ReportIngester::class), $this->createStub(DocIngester::class), $this->limiter());
        $controller->setContainer(new Container());
        $request = Request::create('/api/pilot/runner/claim', 'POST', server: ['HTTP_AUTHORIZATION' => 'Bearer token-1', 'CONTENT_TYPE' => 'application/json'], content: '{"actions":{}}');

        return json_decode((string) $controller->claim($request)->getContent(), true);
    }
}
