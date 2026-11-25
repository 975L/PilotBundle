<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Mcp;

use c975L\ConfigBundle\Contract\UserInterface;
use c975L\ConfigBundle\Entity\AgentToken;
use c975L\ConfigBundle\Mcp\McpServer;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\PilotBundle\Entity\Action;
use c975L\PilotBundle\Entity\Card;
use c975L\PilotBundle\Entity\CardSet;
use c975L\PilotBundle\Entity\Credit;
use c975L\PilotBundle\Entity\Doc;
use c975L\PilotBundle\Entity\PlanTemplate;
use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Mcp\PilotCreditTools;
use c975L\PilotBundle\Mcp\PilotDocTools;
use c975L\PilotBundle\Mcp\PilotMcpProvider;
use c975L\PilotBundle\Mcp\PilotTools;
use c975L\PilotBundle\Repository\ActionRepository;
use c975L\PilotBundle\Repository\CardRepository;
use c975L\PilotBundle\Repository\CardSetRepository;
use c975L\PilotBundle\Repository\CreditRepository;
use c975L\PilotBundle\Repository\DocRepository;
use c975L\PilotBundle\Repository\ProjectRepository;
use c975L\PilotBundle\Security\PilotAccess;
use c975L\PilotBundle\Security\PilotScope;
use c975L\PilotBundle\Service\CardFiller;
use c975L\PilotBundle\Service\CardFlow;
use c975L\PilotBundle\Service\CardImages;
use c975L\PilotBundle\Service\CardMarkdown;
use c975L\PilotBundle\Service\CardRemoval;
use c975L\PilotBundle\Service\CreditOutlook;
use c975L\PilotBundle\Service\PilotUrls;
use c975L\PilotBundle\Service\PlanFactory;
use c975L\PilotBundle\Service\Planning;
use c975L\PilotBundle\Service\RunOutputs;
use c975L\PilotBundle\Tests\Fixtures\TestEntityManager;
use Doctrine\ORM\EntityManager;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[RequiresPhpExtension('pdo_sqlite')]
class PilotMcpProviderTest extends TestCase
{
    private EntityManager $entityManager;
    private Project $catalog;
    private Project $other;
    private Card $card;
    private Card $hidden;
    private bool $admin = false;
    private bool $writable = true;
    /** @var list<string> */
    private array $denied = [];

    protected function setUp(): void
    {
        $this->entityManager = TestEntityManager::create(true);
        $this->catalog = new Project('catalog', 'Catalog');
        $this->other = new Project('other', 'Other');
        $this->card = new Card($this->catalog, 'item-1')->setDue(new \DateTimeImmutable('2026-11-19'))->addStep('step-1')->addStep('step-2')->setStepAction(0, 'action-1');
        $this->hidden = new Card($this->other, 'item-2');
        foreach ([$this->catalog, $this->other, new CardSet($this->catalog, 'Stories'), new CardSet($this->catalog, 'Replies'), new Doc('catalog-file-1', 'file-1')->setProject($this->catalog)->write('summary-1', 'detail-1'), new Doc('other-file-2', 'file-2')->setProject($this->other), $this->card, $this->hidden, new Action('action-2')->declare('Action 2', ['item'])] as $entity) {
            $this->entityManager->persist($entity);
        }
        $this->entityManager->flush();
    }

    // What a client sends around its requests answers without an error: the notification after the handshake accepted, the stream it may open refused
    public function testNotificationAndStream(): void
    {
        $this->assertSame(202, $this->send(['jsonrpc' => '2.0', 'method' => 'notifications/initialized'])->getStatusCode());
        $this->assertSame(405, $this->send([], 'GET')->getStatusCode());
    }

    // The admin sees and reads every space, a reading token given only the reading tools
    public function testAdminAndReadingToken(): void
    {
        $this->admin = true;
        $this->assertSame(['catalog', 'other'], array_column($this->tool('pilot_spaces'), 'slug'));
        $this->assertStringStartsWith('# item-2', $this->tool('pilot_card', ['card' => $this->hidden->getId()])['markdown']);

        $this->writable = false;
        $this->assertSame(['pilot_spaces', 'pilot_cards', 'pilot_card', 'pilot_docs', 'pilot_doc', 'pilot_credit_outlook'], array_column($this->call(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'])['result']['tools'], 'name'));
    }

    // The handshake answers on the site's own host, the tools listed with the writing ones not marked read-only and the deleting ones marked destructive
    public function testInitializeAndListTools(): void
    {
        $initialized = $this->call(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-06-18', 'capabilities' => [], 'clientInfo' => ['name' => 'client-1', 'version' => '1.0']]]);
        $this->assertSame(McpServer::NAME, $initialized['result']['serverInfo']['name']);
        $this->assertStringContainsString('Cards are tasks grouped in spaces', $initialized['result']['instructions']);

        $tools = array_column($this->call(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'])['result']['tools'], null, 'name');
        $this->assertSame(['pilot_spaces', 'pilot_cards', 'pilot_card', 'pilot_add_card', 'pilot_tick_step', 'pilot_comment', 'pilot_edit_card', 'pilot_add_steps', 'pilot_edit_step', 'pilot_close_card', 'pilot_run_step', 'pilot_cancel_run', 'pilot_merge_cards', 'pilot_delete_card', 'pilot_docs', 'pilot_doc', 'pilot_write_doc', 'pilot_credit_outlook', 'pilot_credit_set'], array_keys($tools));
        $this->assertTrue($tools['pilot_card']['annotations']['readOnlyHint']);
        $this->assertFalse($tools['pilot_add_card']['annotations']['readOnlyHint']);
        $this->assertFalse($tools['pilot_edit_card']['annotations']['destructiveHint']);
        $this->assertTrue($tools['pilot_merge_cards']['annotations']['destructiveHint']);
        $this->assertSame(['space', 'title'], $tools['pilot_add_card']['inputSchema']['required']);
    }

    // The admin reads what each credit leaves before its renewal and the later steps that could spend it; a member is refused
    public function testCreditOutlook(): void
    {
        $this->entityManager->persist(new Credit('item-1')->setLabel('Item 1')->setRemaining(10)->setRenewsOn(new \DateTimeImmutable('2026-11-08')));
        $later = new Card($this->catalog, 'item-3')->setDue(new \DateTimeImmutable('2027-01-02'))->setChecklist([['t' => 'step-1', 'cost' => ['credit' => 'item-1', 'amount' => 4]]]);
        $this->entityManager->persist($later);
        $this->entityManager->flush();
        $this->assertStringContainsString('Only an administrator', $this->call(['jsonrpc' => '2.0', 'id' => 4, 'method' => 'tools/call', 'params' => ['name' => 'pilot_credit_outlook', 'arguments' => new \stdClass()]])['result']['content'][0]['text']);

        $this->admin = true;
        $outlook = $this->tool('pilot_credit_outlook');

        $this->assertSame(['item-1', '2026-12-08', 10], [$outlook[0]['credit'], $outlook[0]['renews'], $outlook[0]['left']]);
        $this->assertSame([$later->getId(), 'step-1', 4, []], [$outlook[0]['steps'][0]['card'], $outlook[0]['steps'][0]['text'], $outlook[0]['steps'][0]['amount'], $outlook[0]['steps'][0]['awaits']]);
    }

    // The admin types in a balance by hand with its renewal; a credit read from its service, a bad day or a member refused
    public function testCreditSet(): void
    {
        $typed = new Credit('item-1')->setLabel('Item 1');
        $this->entityManager->persist($typed);
        $this->entityManager->persist(new Credit('item-2')->setLabel('Item 2')->setProvider('d-id'));
        $this->entityManager->flush();
        $this->assertStringContainsString('Only an administrator', $this->toolError('pilot_credit_set', ['credit' => 'item-1', 'remaining' => 5]));

        $this->admin = true;

        $this->assertSame(['credit' => 'item-1', 'remaining' => 70, 'renews' => '2026-12-08'], $this->tool('pilot_credit_set', ['credit' => 'item-1', 'remaining' => 70, 'renews' => '2026-12-08']));
        $this->assertSame(70, $typed->getRemaining());
        $this->assertStringContainsString('read from its service', $this->toolError('pilot_credit_set', ['credit' => 'item-2', 'remaining' => 5]));
        $this->assertStringContainsString('no day', $this->toolError('pilot_credit_set', ['credit' => 'item-1', 'remaining' => 5, 'renews' => '08/12/2026']));
    }

    // Only the spaces the account sees are listed, with their open cards counted and their collections
    public function testSpacesKeptToTheAccount(): void
    {
        $this->assertSame([['slug' => 'catalog', 'name' => 'Catalog', 'open' => 1, 'collections' => ['Replies', 'Stories']]], $this->tool('pilot_spaces'));
    }

    // Without a space nor words, the open cards due today or late; a card of another space never shows
    public function testCardsDueAndSearched(): void
    {
        $this->assertSame(['item-1'], array_column($this->tool('pilot_cards'), 'title'));
        $this->assertSame('0/2', $this->tool('pilot_cards', ['space' => 'catalog'])[0]['steps']);
        $this->assertSame([], $this->tool('pilot_cards', ['search' => 'item-2']));
    }

    // A card reads as markdown with its steps indexed, a step ticked by its index and a comment added
    public function testReadTickAndComment(): void
    {
        $read = $this->tool('pilot_card', ['card' => $this->card->getId()]);
        $this->assertStringStartsWith('# item-1', $read['markdown']);
        $this->assertSame('https://site.example/pilot/catalog#card-' . $this->card->getId(), $read['url']);
        $this->assertSame([0, 1], array_column($read['steps'], 'index'));

        $this->assertSame(['done' => true], $this->tool('pilot_tick_step', ['card' => $this->card->getId(), 'step' => 1]));
        $this->assertSame(['done' => true], $this->tool('pilot_tick_step', ['card' => $this->card->getId(), 'step' => 1]));
        $this->assertSame(['done' => false], $this->tool('pilot_tick_step', ['card' => $this->card->getId(), 'step' => 0, 'done' => false]));
        $this->assertSame(['index' => 2], $this->tool('pilot_comment', ['card' => $this->card->getId(), 'text' => 'comment-1']));
        $this->assertSame(['done' => true], $this->tool('pilot_tick_step', ['card' => $this->card->getId(), 'step' => 2]));
        $this->entityManager->clear();
        $card = $this->entityManager->find(Card::class, $this->card->getId());
        $this->assertTrue($card->getChecklist()[1]['done']);
        $this->assertSame('comment-1', $card->getFeedback()[2]['t']);
        $this->assertTrue($card->getFeedback()[2]['done']);
    }

    // The card reaches the agent as written, its note and comments as they are
    public function testReadGivesTheCardAsWritten(): void
    {
        $this->card->setNote('note-1')->addFeedback('feedback-1');
        $this->entityManager->flush();

        $read = $this->tool('pilot_card', ['card' => $this->card->getId()]);
        $this->assertStringContainsString("note-1\n", $read['markdown']);
        $this->assertSame(['feedback-1'], array_column($read['comments'], 'text'));
        $this->assertSame(['step-1', 'step-2'], array_column($read['steps'], 'text'));
    }

    // A card added to a served space with its note and day, a malformed day or an unserved space refused as a tool error
    public function testAddCard(): void
    {
        $added = $this->tool('pilot_add_card', ['space' => 'catalog', 'title' => 'item-3', 'note' => 'note-1', 'due' => '2026-12-01']);
        $card = $this->entityManager->find(Card::class, $added['id']);
        $this->assertSame('note-1', $card->getNote());
        $this->assertSame('2026-12-01', $card->getDue()?->format('Y-m-d'));

        $this->assertSame('The day "01/12/2026" is not YYYY-MM-DD.', $this->toolError('pilot_add_card', ['space' => 'catalog', 'title' => 'item-4', 'due' => '01/12/2026']));
        $this->assertSame('No space "other".', $this->toolError('pilot_add_card', ['space' => 'other', 'title' => 'item-4']));
    }

    // Only the fields given change, an empty day sending the card to the backlog, a collection chosen by its name, an unknown one refused with those of the space
    public function testEditCard(): void
    {
        $id = $this->card->getId();
        $this->tool('pilot_edit_card', ['card' => $id, 'title' => 'item-9', 'due' => '', 'collection' => 'stories']);
        $this->assertSame('No collection "films" in this space, only: Replies, Stories.', $this->toolError('pilot_edit_card', ['card' => $id, 'collection' => 'films']));
        $this->assertSame('No space "other".', $this->toolError('pilot_edit_card', ['card' => $id, 'space' => 'other']));
        $this->entityManager->clear();
        $card = $this->entityManager->find(Card::class, $id);
        $this->assertSame('item-9', $card->getTitle());
        $this->assertNull($card->getDue());
        $this->assertSame('Stories', $card->getCardSet()?->getName());
    }

    // Steps added after the others, the card closed then opened again
    public function testAddStepsAndClose(): void
    {
        $id = $this->card->getId();
        $this->assertSame(['step-1', 'step-2', 'step-3'], array_column($this->tool('pilot_add_steps', ['card' => $id, 'steps' => ['step-3', ' ']])['steps'], 'text'));
        $this->assertSame(['status' => 'done'], $this->tool('pilot_close_card', ['card' => $id]));
        $this->assertSame(['status' => 'ready'], $this->tool('pilot_close_card', ['card' => $id, 'done' => false]));
    }

    // A card merged into another carries its note, steps and comments there and is deleted; a card into itself refused
    public function testMergeAndDelete(): void
    {
        $twin = new Card($this->catalog, 'item-1 bis')->setNote('note-2')->addStep('step-9')->addFeedback('comment-9');
        $this->entityManager->persist($twin);
        $this->entityManager->flush();
        $twinId = (int) $twin->getId();
        $id = (int) $this->card->getId();

        $this->assertSame('A card is not merged into itself.', $this->toolError('pilot_merge_cards', ['card' => $id, 'into' => $id]));
        $this->assertSame(['step-1', 'step-2', 'step-9'], array_column($this->tool('pilot_merge_cards', ['card' => $twinId, 'into' => $id])['steps'], 'text'));
        $this->entityManager->clear();
        $this->assertNull($this->entityManager->find(Card::class, $twinId));
        $card = $this->entityManager->find(Card::class, $id);
        $this->assertSame('note-2', $card->getNote());
        $this->assertSame(['comment-9'], array_column(array_values($card->getFeedback()), 't'));

        $this->assertSame(['deleted' => $id], $this->tool('pilot_delete_card', ['card' => $id]));
        $this->entityManager->clear();
        $this->assertNull($this->entityManager->find(Card::class, $id));
        $this->assertSame('No card ' . $this->hidden->getId() . '.', $this->toolError('pilot_delete_card', ['card' => $this->hidden->getId()]));
    }

    // A card with steps is never merged into a card following a checklist template, which takes its steps from the template
    public function testMergeRefusedIntoATemplateCard(): void
    {
        $template = new PlanTemplate('catalog', [['title' => 'step-1']]);
        $this->entityManager->persist($template);
        $this->card->follow($template);
        $twin = new Card($this->catalog, 'item-1 bis')->addStep('step-9');
        $this->entityManager->persist($twin);
        $this->entityManager->flush();

        $this->assertSame('The card follows a checklist template: add the steps to its template.', $this->toolError('pilot_merge_cards', ['card' => (int) $twin->getId(), 'into' => (int) $this->card->getId()]));
    }

    // Two cards whose checklists would pass the ceiling together are not merged, the card merged away kept
    public function testMergeRefusedPastTheCeiling(): void
    {
        $twin = new Card($this->catalog, 'item-1 bis')->setChecklist(array_fill(0, Card::MAX_CHECKLIST, ['t' => 'step-9']));
        $this->entityManager->persist($twin);
        $this->entityManager->flush();
        $twinId = (int) $twin->getId();

        $this->assertSame('flow.checklist_full', $this->toolError('pilot_merge_cards', ['card' => $twinId, 'into' => (int) $this->card->getId()]));
        $this->entityManager->clear();
        $this->assertNotNull($this->entityManager->find(Card::class, $twinId));
    }

    // A step naming an action queued for a machine, its run then read on the step; a step naming none or already queued refused
    public function testRunStep(): void
    {
        $id = $this->card->getId();
        $this->assertSame('queued', $this->tool('pilot_run_step', ['card' => $id, 'step' => 0])['status']);
        $this->assertSame('The card has no step 1 naming a machine action.', $this->toolError('pilot_run_step', ['card' => $id, 'step' => 1]));
        $this->assertSame('flow.not_now', $this->toolError('pilot_run_step', ['card' => $id, 'step' => 0]));
        $steps = $this->tool('pilot_card', ['card' => $id])['steps'];
        $this->assertSame(['index' => 0, 'text' => 'step-1', 'done' => false, 'action' => 'action-1', 'run' => 'queued'], $steps[0]);
    }

    // A step given its action and that action's parameters, only what is given changing; an unknown action or step refused
    public function testEditStep(): void
    {
        $id = (int) $this->card->getId();

        $this->assertSame(['index' => 1, 'text' => 'step-2', 'action' => 'action-2', 'params' => ['item' => 'item-1']], $this->tool('pilot_edit_step', ['card' => $id, 'step' => 1, 'action' => 'action-2', 'params' => ['item' => 'item-1', 'bad name' => 'item-2']]));
        $this->assertSame(['index' => 1, 'text' => 'step-3', 'action' => 'action-2', 'params' => ['item' => 'item-1']], $this->tool('pilot_edit_step', ['card' => $id, 'step' => 1, 'text' => 'step-3']));
        $this->assertSame(['index' => 1, 'text' => 'step-3', 'action' => null, 'params' => []], $this->tool('pilot_edit_step', ['card' => $id, 'step' => 1, 'action' => '']));
        $this->assertSame('No machine declares the action action-9.', $this->toolError('pilot_edit_step', ['card' => $id, 'step' => 1, 'action' => 'action-9']));
        $this->assertSame('The card has no step 5.', $this->toolError('pilot_edit_step', ['card' => $id, 'step' => 5]));
    }

    // A run queued then withdrawn, the card back to do; nothing left to withdraw refused, as is an account that may not run
    public function testCancelRun(): void
    {
        $id = (int) $this->card->getId();
        $run = $this->tool('pilot_run_step', ['card' => $id, 'step' => 0])['run'];

        $this->assertSame(['url' => 'https://site.example/pilot/catalog#card-' . $id, 'run' => $run, 'status' => 'cancelled', 'cardStatus' => 'ready'], $this->tool('pilot_cancel_run', ['card' => $id, 'run' => $run]));
        $this->assertSame('flow.not_now', $this->toolError('pilot_cancel_run', ['card' => $id]));
        $this->assertSame('cancelled', $this->tool('pilot_card', ['card' => $id])['steps'][0]['run']);

        $this->denied = [PilotAccess::RUN];
        $this->assertSame('This account may not do that on card ' . $id . '.', $this->toolError('pilot_cancel_run', ['card' => $id]));
    }

    // Files listed and read within the spaces served, one added, one rewritten floor by floor and attached to a card
    public function testDocs(): void
    {
        $this->assertSame(['catalog-file-1'], array_column($this->tool('pilot_docs'), 'slug'));
        $this->assertSame([], $this->tool('pilot_docs', ['search' => 'file-2']));
        $this->assertStringContainsString('detail-1', $this->tool('pilot_doc', ['doc' => 'catalog-file-1'])['markdown']);
        $this->assertSame('No file "other-file-2".', $this->toolError('pilot_doc', ['doc' => 'other-file-2']));

        $added = $this->tool('pilot_write_doc', ['space' => 'catalog', 'title' => 'file-3', 'summary' => 'summary-3']);
        $this->assertSame('catalog-file-3', $added['slug']);
        $this->assertSame('A new file needs a title.', $this->toolError('pilot_write_doc', ['space' => 'catalog']));
        $this->tool('pilot_write_doc', ['doc' => 'catalog-file-1', 'detail' => 'detail-9']);
        $this->tool('pilot_edit_card', ['card' => $this->card->getId(), 'doc' => 'catalog-file-1']);
        $this->assertSame('No file "other-file-2" in this space.', $this->toolError('pilot_edit_card', ['card' => $this->card->getId(), 'doc' => 'other-file-2']));
        $this->entityManager->clear();
        $doc = $this->entityManager->find(Doc::class, $this->entityManager->find(Card::class, $this->card->getId())->getDoc()?->getId());
        $this->assertSame(['summary-1', 'detail-9'], [$doc->getSummary(), $doc->getDetail()]);
        $this->assertSame('catalog-file-1', $this->entityManager->find(Card::class, $this->card->getId())->getDoc()?->getSlug());
    }

    // A guest of a space, seeing it without editing there, opens none of its files, as on their page
    public function testDocsRefusedToAGuest(): void
    {
        $this->denied = [PilotAccess::EDIT];
        $this->assertSame('No file "catalog-file-1".', $this->toolError('pilot_doc', ['doc' => 'catalog-file-1']));
        $this->assertSame('This account may not do that in the space "catalog".', $this->toolError('pilot_docs', ['space' => 'catalog']));
        $this->assertSame('No file "catalog-file-1".', $this->toolError('pilot_write_doc', ['doc' => 'catalog-file-1', 'detail' => 'detail-9']));
    }

    // A card of a space the account does not see is refused like an unknown one, for reading as for writing, a gesture its role does not hold said so
    public function testUnseenCardRefused(): void
    {
        $message = 'No card ' . $this->hidden->getId() . '.';
        $this->assertSame($message, $this->toolError('pilot_card', ['card' => $this->hidden->getId()]));
        $this->assertSame($message, $this->toolError('pilot_comment', ['card' => $this->hidden->getId(), 'text' => 'comment-1']));
        $this->assertSame('The card has no step or comment 5.', $this->toolError('pilot_tick_step', ['card' => $this->card->getId(), 'step' => 5]));

        $this->denied = [PilotAccess::DELETE];
        $this->assertSame('This account may not do that on card ' . $this->card->getId() . '.', $this->toolError('pilot_delete_card', ['card' => $this->card->getId()]));
    }

    // The decoded result of a tool, its text being the JSON the tool returned
    /**
     * @param array<string, mixed> $arguments
     *
     * @return array<mixed>
     */
    private function tool(string $name, array $arguments = []): array
    {
        $result = $this->call(['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call', 'params' => ['name' => $name, 'arguments' => (object) $arguments]])['result'];
        $this->assertFalse($result['isError'] ?? false, $result['content'][0]['text'] ?? '');

        return json_decode($result['content'][0]['text'], true, flags: \JSON_THROW_ON_ERROR);
    }

    // The message of a tool refusing its call
    /** @param array<string, mixed> $arguments */
    private function toolError(string $name, array $arguments): string
    {
        $result = $this->call(['jsonrpc' => '2.0', 'id' => 4, 'method' => 'tools/call', 'params' => ['name' => $name, 'arguments' => $arguments]])['result'];
        $this->assertTrue($result['isError']);

        return $result['content'][0]['text'];
    }

    // The decoded JSON-RPC answer to a message sent with the agent token
    /**
     * @param array<string, mixed> $message
     *
     * @return array<string, mixed>
     */
    private function call(array $message): array
    {
        $response = $this->send($message);
        $this->assertSame(200, $response->getStatusCode());
        $body = $response instanceof StreamedResponse ? $this->streamed($response) : (string) $response->getContent();

        return json_decode($body, true, flags: \JSON_THROW_ON_ERROR);
    }

    // The body a streamed answer writes, its last data event when it is one
    private function streamed(StreamedResponse $response): string
    {
        ob_start();
        $response->sendContent();
        $body = (string) ob_get_clean();
        preg_match_all('/^data: (.+)$/m', $body, $events);

        return [] === $events[1] ? $body : (string) end($events[1]);
    }

    // A message sent to the site's MCP server on a host other than localhost, for an agent token of an account whose rights the test sets
    /** @param array<string, mixed> $message */
    private function send(array $message, string $method = 'POST'): Response
    {
        $server = ['HTTP_HOST' => 'site.example', 'HTTPS' => 'on', 'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json, text/event-stream', 'HTTP_MCP_PROTOCOL_VERSION' => '2025-06-18'];
        $agentToken = new AgentToken()->setUser($this->createStub(UserInterface::class))->setName('agent-1')->setWritable($this->writable);

        return new McpServer([$this->provider()])->handle(Request::create('https://site.example/api/mcp', $method, server: $server, content: 'GET' === $method ? null : json_encode($message, \JSON_THROW_ON_ERROR)), $agentToken);
    }

    // The account's rights: the admin everything, otherwise a member of the catalog, short of the levels denied, seeing nothing of the other space
    private function access(): PilotAccess
    {
        $access = $this->createStub(PilotAccess::class);
        $access->method('isAdmin')->willReturnCallback(fn (): bool => $this->admin);
        $access->method('scope')->willReturnCallback(fn (): PilotScope => new PilotScope($this->admin, [(int) $this->catalog->getId()]));
        $access->method('can')->willReturnCallback(fn (Project | Card $subject, string $level): bool => $this->admin || (($subject instanceof Card ? $subject->getProject() : $subject)->getId() === $this->catalog->getId() && !\in_array($level, $this->denied, true)));

        return $access;
    }

    // The provider on the real repositories, the card addresses made on the test's host
    private function provider(): PilotMcpProvider
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($this->entityManager);
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturnCallback(static fn (string $route, array $parameters): string => 'https://site.example/pilot/' . ($parameters['slug'] ?? $parameters['space'] . '/' . $parameters['id']));
        $flow = new CardFlow(new MockClock('2026-11-20 10:00'));
        $cards = new CardRepository($registry);
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);
        $root = sys_get_temp_dir() . '/pilot-mcp';
        $removal = new CardRemoval($this->entityManager, new CardImages($root), new RunOutputs($root));
        $access = $this->access();
        $tools = new PilotTools(new ProjectRepository($registry), $cards, new CardFiller($cards, new PlanFactory($flow)), $flow, new CardMarkdown($urls), new PilotUrls($urls), $this->entityManager, new MockClock('2026-11-20 10:00'), new CardSetRepository($registry), $removal, $translator, new DocRepository($registry), $access, new ActionRepository($registry));
        $docs = new PilotDocTools(new ProjectRepository($registry), new DocRepository($registry), $this->entityManager, $urls, $access);
        $credits = new PilotCreditTools(new Planning($cards, $this->createStub(ConfigServiceInterface::class), new MockClock('2026-11-20 10:00')), new CreditRepository($registry), new CreditOutlook(), $access, new PilotUrls($urls), $this->entityManager, new MockClock('2026-11-20 10:00'));

        return new PilotMcpProvider($tools, $docs, $credits);
    }
}
