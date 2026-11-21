<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Form;

use c975L\ConfigBundle\Service\VaultEncryptor;
use c975L\PilotBundle\Entity\Credit;
use c975L\PilotBundle\Form\CreditType;
use c975L\PilotBundle\Repository\CreditRepository;
use c975L\PilotBundle\Service\Credit\ElevenLabsReader;
use c975L\PilotBundle\Service\CreditRefresher;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\HttpClient\MockHttpClient;

class CreditTypeTest extends TestCase
{
    private VaultEncryptor $vaultEncryptor;

    private FormFactoryInterface $factory;

    // A form factory holding the type with an ElevenLabs reader and a test vault key
    protected function setUp(): void
    {
        $this->vaultEncryptor = new VaultEncryptor('test-vault-key');
        $refresher = new CreditRefresher([new ElevenLabsReader(new MockHttpClient())], $this->vaultEncryptor, new MockClock(), new NullLogger(), $this->createStub(CreditRepository::class));
        $this->factory = Forms::createFormFactoryBuilder()->addExtension(new PreloadedExtension([new CreditType($refresher, $this->vaultEncryptor)], []))->getFormFactory();
    }

    // The key is never shown back and is stored encrypted, the service picked among the readers'
    public function testKeyNeverShownAndStoredEncrypted(): void
    {
        $credit = new Credit('item-1')->setLabel('Item')->setApiKey($this->vaultEncryptor->encrypt('old-key'));
        $form = $this->factory->create(CreditType::class, $credit);
        $this->assertNull($form->get('apiKey')->getData());
        $this->assertStringNotContainsString('old-key', (string) json_encode($form->createView()->children['apiKey']->vars));

        $form->submit(['label' => 'Item', 'key' => 'item-1', 'provider' => 'elevenlabs', 'apiKey' => ' new-key ', 'position' => '0']);

        $this->assertTrue($form->isSynchronized());
        $this->assertSame('elevenlabs', $credit->getProvider());
        $this->assertStringStartsWith('C975L:', (string) $credit->getApiKey());
        $this->assertSame('new-key', $this->vaultEncryptor->decrypt((string) $credit->getApiKey()));
    }

    // A key left empty stays as it was while the credit names a service, and is taken off when it goes back to a balance typed in by hand
    public function testKeyLeftEmpty(): void
    {
        $stored = $this->vaultEncryptor->encrypt('old-key');
        $credit = new Credit('item-1')->setLabel('Item')->setProvider('elevenlabs')->setApiKey($stored);

        $this->factory->create(CreditType::class, $credit)->submit(['label' => 'Item', 'key' => 'item-1', 'provider' => 'elevenlabs', 'apiKey' => '', 'position' => '0']);
        $this->assertSame($stored, $credit->getApiKey());

        $this->factory->create(CreditType::class, $credit)->submit(['label' => 'Item', 'key' => 'item-1', 'provider' => '', 'apiKey' => '', 'position' => '0']);
        $this->assertNull($credit->getApiKey());
        $this->assertTrue($credit->isManual());
    }
}
