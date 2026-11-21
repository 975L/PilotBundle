<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Controller;

use c975L\PilotBundle\Controller\PilotController;
use c975L\PilotBundle\Entity\Card;
use c975L\PilotBundle\Entity\Doc;
use c975L\PilotBundle\Entity\Plan;
use c975L\PilotBundle\Entity\PlanTemplate;
use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Entity\Report;
use c975L\PilotBundle\Entity\Run;
use c975L\PilotBundle\Exception\FlowException;
use c975L\PilotBundle\Repository\ActionRepository;
use c975L\PilotBundle\Repository\CardRepository;
use c975L\PilotBundle\Repository\CardSetRepository;
use c975L\PilotBundle\Repository\DocRepository;
use c975L\PilotBundle\Repository\MemberRepository;
use c975L\PilotBundle\Repository\ProjectRepository;
use c975L\PilotBundle\Repository\ReportRepository;
use c975L\PilotBundle\Security\PilotAccess;
use c975L\PilotBundle\Service\CardFiller;
use c975L\PilotBundle\Service\CardFlow;
use c975L\PilotBundle\Service\CardImages;
use c975L\PilotBundle\Service\CardMarkdown;
use c975L\PilotBundle\Service\StepOrder;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class PilotControllerEditTest extends TestCase
{
    // A field edited in place posts alone: the others keep their value
    public function testOnlyThePostedFieldChanges(): void
    {
        $card = $this->card();

        $this->edit($card, ['note' => 'note-2']);

        $this->assertSame('item-1', $card->getTitle());
        $this->assertSame('note-2', $card->getNote());
        $this->assertSame('someone', $card->getWaitingOn());
        $this->assertSame('catalog-file', $card->getDoc()?->getSlug());
    }

    // An empty title keeps the one the card had, an emptied note or wait clears it
    public function testEmptiedFields(): void
    {
        $card = $this->card();

        $this->edit($card, ['title' => ' ', 'note' => '', 'waitingOn' => '']);

        $this->assertSame('item-1', $card->getTitle());
        $this->assertNull($card->getNote());
        $this->assertNull($card->getWaitingOn());
        $this->assertNotNull($card->getDoc());
    }

    // A link posted alone is kept when it is an http(s) one, cleared when emptied, refused otherwise
    public function testLinks(): void
    {
        $card = $this->card();

        $this->edit($card, ['sourceUrl' => ' https://example.com/item-1 ', 'resultUrl' => 'javascript:alert(1)']);
        $this->assertSame('https://example.com/item-1', $card->getSourceUrl());
        $this->assertNull($card->getResultUrl());
        $this->assertSame('note-1', $card->getNote());

        $this->edit($card, ['sourceUrl' => '']);
        $this->assertNull($card->getSourceUrl());
    }

    // The file posted empty leaves the card without one
    public function testFileCleared(): void
    {
        $card = $this->card();

        $this->edit($card, ['doc' => '', 'newDoc' => '']);

        $this->assertNull($card->getDoc());
        $this->assertSame('note-1', $card->getNote());
    }

    private function card(): Card
    {
        return new Card(new Project('catalog', 'Catalog'), 'item-1')->setNote('note-1')->setWaitingOn('someone')->setDoc(new Doc('catalog-file', 'File'));
    }

    // A card moves to a space the account may write to, never to one it may not, and a card of a plan stays with it
    public function testMove(): void
    {
        $card = $this->card();
        $other = new Project('other', 'Other');
        $projects = $this->createStub(ProjectRepository::class);
        $projects->method('find')->willReturn($other);
        $access = $this->createStub(PilotAccess::class);
        $access->method('can')->willReturnOnConsecutiveCalls(true, false, true);
        $controller = $this->controller($projects, $access);
        $move = new \ReflectionMethod($controller, 'move');

        $move->invoke($controller, $card, 2);
        $this->assertSame($other, $card->getProject());

        $refused = 0;
        foreach ([$this->card(), $this->card()->placeIn(new Plan(new PlanTemplate('catalog', []), $other, 'item-1'), 1)] as $kept) {
            try {
                $move->invoke($controller, $kept, 2);
            } catch (FlowException) {
                ++$refused;
                $this->assertSame('catalog', $kept->getProject()->getSlug());
            }
        }
        $this->assertSame(2, $refused);
    }

    // A step is added by hand to a card of its own, never to one following a checklist template
    public function testStepAddedOnlyWithoutTemplate(): void
    {
        $controller = $this->controller($this->createStub(ProjectRepository::class), $this->createStub(PilotAccess::class));
        $add = new \ReflectionMethod($controller, 'addStep');
        $card = $this->card();

        $add->invoke($controller, $card, 'step-1');
        $this->assertSame('step-1', $card->getChecklist()[0]['t']);

        $card->follow(new PlanTemplate('catalog', [])->setChecklist(true));
        $this->expectException(FlowException::class);
        $add->invoke($controller, $card, 'step-2');
    }

    // The editing link lets its holder hand the card to a machine, so lending it asks as much; the read-only link asks writing the card
    public function testLendAsksRun(): void
    {
        $levels = new \ReflectionClassConstant(PilotController::class, 'GESTURE_LEVELS')->getValue();

        $this->assertSame(PilotAccess::RUN, $levels['lend']);
        $this->assertSame(PilotAccess::RUN, $levels['unlend']);
        $this->assertArrayNotHasKey('share', $levels);
    }

    // A report's card goes to the page's space only when the report shows there the row, or for the whole table any row
    public function testReportProject(): void
    {
        $own = new Project('catalog', 'Catalog');
        $page = new Project('other', 'Other')->setSites(['site-1']);
        $projects = $this->createStub(ProjectRepository::class);
        $projects->method('find')->willReturn($page);
        $controller = $this->controller($projects);
        $method = new \ReflectionMethod($controller, 'reportProject');
        $request = new Request(request: ['project' => '2']);

        $this->assertSame($page, $method->invoke($controller, $this->report($own, ['site-2', 'site-1']), $request, 1));
        $this->assertSame($own, $method->invoke($controller, $this->report($own, ['site-2', 'site-1']), $request, 0));
        $this->assertSame($page, $method->invoke($controller, $this->report($own, ['site-2', 'site-1']), $request, null));
        $this->assertSame($own, $method->invoke($controller, $this->report($own, ['site-2']), $request, null));
    }

    // A feedback added with its screenshot keeps it under a random name; a picture refused adds nothing, an empty text keeps no file
    public function testFeedbackAddedWithItsScreenshot(): void
    {
        $root = sys_get_temp_dir() . '/pilot-shot-' . bin2hex(random_bytes(4));
        $controller = $this->controller(images: new CardImages($root));
        $card = $this->card();
        $this->gesture($controller, 'feedbackAdd', $card, ['text' => 'feedback-1'], $this->png());
        $this->assertMatchesRegularExpression('/^' . CardImages::FEEDBACK_NAME . '$/', $card->getFeedbackImages()[0]);
        $this->assertFileExists($root . '/' . Card::FEEDBACK_IMAGE_DIRECTORY . '/' . $card->getFeedbackImages()[0]);

        $this->gesture($controller, 'feedbackAdd', $card, ['text' => ' '], $this->png());
        $this->assertCount(1, $card->getFeedback());

        $refused = false;
        try {
            $this->gesture($controller, 'feedbackAdd', $card, ['text' => 'feedback-2'], new UploadedFile(__FILE__, 'item-1.png', null, null, true));
        } catch (FlowException) {
            $refused = true;
        }
        $this->assertTrue($refused);
        $this->assertCount(1, $card->getFeedback());
        new Filesystem()->remove($root);
    }

    // A feedback's screenshot replaced by the one sent, removed by « remove », a step taking none
    public function testFeedbackImageSetAndRemoved(): void
    {
        $root = sys_get_temp_dir() . '/pilot-shot-' . bin2hex(random_bytes(4));
        $controller = $this->controller(images: new CardImages($root));
        $card = $this->card()->addStep('step-1')->addFeedback('feedback-1');
        $index = array_key_last($card->getChecklist());

        $this->gesture($controller, 'feedbackImage', $card, ['index' => (string) $index], $this->png());
        $this->assertCount(1, $card->getFeedbackImages());
        $this->gesture($controller, 'feedbackImage', $card, ['index' => '0'], $this->png());
        $this->assertArrayNotHasKey('img', $card->getChecklist()[0]);
        $this->gesture($controller, 'feedbackImage', $card, ['index' => (string) $index, 'remove' => '1']);
        $this->assertSame([], $card->getFeedbackImages());
        new Filesystem()->remove($root);
    }

    // A private feedback gesture run on the posted fields and the picture sent
    /** @param array<string, string> $fields */
    private function gesture(PilotController $controller, string $method, Card $card, array $fields, ?UploadedFile $file = null): void
    {
        new \ReflectionMethod($controller, $method)->invoke($controller, $card, new Request(request: $fields, files: null === $file ? [] : ['image' => $file]));
    }

    // A one-pixel PNG sent as a pasted screenshot
    private function png(): UploadedFile
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'pilot');
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=', true));

        return new UploadedFile($path, 'image.png', null, null, true);
    }

    // A report of the given space whose rows match these sites
    /** @param list<string> $sites */
    private function report(Project $project, array $sites): Report
    {
        $rows = array_map(static fn (string $site): array => ['site' => $site], $sites);

        return new Report('report-1', $project)->refresh(['label' => 'Report', 'project' => $project, 'match' => 'site', 'home' => false, 'columns' => [], 'rows' => $rows, 'actions' => [], 'globals' => []], new \DateTimeImmutable());
    }

    // The private gesture run on the posted fields
    // A reference deleted from its page leaves the cards citing it without one, the dialog told it is gone
    public function testDocDeleted(): void
    {
        $card = $this->card();
        $card->getDoc()?->setProject($card->getProject());
        $doc = $card->getDoc();
        $cards = $this->createStub(CardRepository::class);
        $cards->method('findOfDoc')->willReturn([$card]);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('remove')->with($doc);
        $entityManager->expects($this->once())->method('flush');
        $controller = $this->routed($this->controller(entityManager: $entityManager, access: $this->allowing(), cards: $cards), true);

        $response = $controller->docDelete($doc, $this->dialogRequest());

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('1', $response->headers->get(PilotController::GONE_HEADER));
        $this->assertNull($card->getDoc());
    }

    // A reference whose token expired stays, the dialog told why
    public function testDocKeptWithAnExpiredToken(): void
    {
        $card = $this->card();
        $card->getDoc()?->setProject($card->getProject());
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('remove');
        $controller = $this->routed($this->controller(entityManager: $entityManager, access: $this->allowing()), false);

        $this->assertSame(422, $controller->docDelete($card->getDoc(), $this->dialogRequest())->getStatusCode());
        $this->assertNotNull($card->getDoc());
    }

    // A card downloads as the markdown its steps are handed to an AI with, named after its number
    public function testCardMarkdown(): void
    {
        $card = $this->card();
        new \ReflectionProperty(Card::class, 'id')->setValue($card, 7);
        $cardMarkdown = new CardMarkdown($this->createStub(DocRepository::class), $this->createStub(UrlGeneratorInterface::class));
        $controller = $this->routed($this->controller(access: $this->allowing()), true);

        $response = $controller->cardMarkdown($card, $cardMarkdown);

        $this->assertSame($cardMarkdown->write($card), $response->getContent());
        $this->assertStringStartsWith('text/markdown', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('card-7.md', (string) $response->headers->get('Content-Disposition'));
    }

    // An access granting every level
    private function allowing(): PilotAccess
    {
        $access = $this->createStub(PilotAccess::class);
        $access->method('can')->willReturn(true);

        return $access;
    }

    // A request sent from the card's dialog
    private function dialogRequest(): Request
    {
        $request = new Request(request: ['_token' => 't']);
        $request->headers->set(PilotController::DIALOG_HEADER, '1');

        return $request;
    }

    // The controller given a signed-in account, a router and a token checker answering as told
    private function routed(PilotController $controller, bool $validToken): PilotController
    {
        $checker = $this->createStub(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturn(true);
        $csrf = $this->createStub(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturn($validToken);
        $container = new Container();
        $container->set('router', $this->createStub(UrlGeneratorInterface::class));
        $container->set('security.authorization_checker', $checker);
        $container->set('security.csrf.token_manager', $csrf);
        $controller->setContainer($container);

        return $controller;
    }

    /** @param array<string, string> $fields */
    private function edit(Card $card, array $fields): void
    {
        $controller = $this->controller();
        new \ReflectionMethod($controller, 'edit')->invoke($controller, $card, new Request(request: $fields));
    }

    // The controller on stubs, the spaces, the access, the entity manager and the cards given when a test needs them
    private function controller(?ProjectRepository $projects = null, ?PilotAccess $access = null, ?EntityManagerInterface $entityManager = null, ?CardImages $images = null, ?CardRepository $cards = null): PilotController
    {
        return new PilotController(
            $entityManager ?? $this->createStub(EntityManagerInterface::class),
            $cards ?? $this->createStub(CardRepository::class),
            $this->createStub(ReportRepository::class),
            $this->createStub(DocRepository::class),
            $projects ?? $this->createStub(ProjectRepository::class),
            new MockClock(),
            new CardFlow(new MockClock()),
            $this->createStub(TranslatorInterface::class),
            $this->createStub(ActionRepository::class),
            new StepOrder($this->createStub(CardRepository::class), new MockClock()),
            $this->createStub(CardFiller::class),
            $access ?? $this->createStub(PilotAccess::class),
            $this->createStub(MemberRepository::class),
            $this->createStub(CardSetRepository::class),
            $images ?? new CardImages(sys_get_temp_dir()),
        );
    }
}
