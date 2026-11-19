<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Tests\Service;

use c975L\PilotBundle\Entity\Card;
use c975L\PilotBundle\Entity\Project;
use c975L\PilotBundle\Service\CardImages;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class CardImagesTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/pilot-card-images-' . bin2hex(random_bytes(4));
        mkdir($this->root);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->root . '/' . Card::IMAGE_DIRECTORY . '/*') ?: [] as $path) {
            unlink($path);
        }
        foreach ([Card::IMAGE_DIRECTORY, 'private/pilot', 'private', ''] as $folder) {
            if (is_dir($this->root . '/' . $folder)) {
                rmdir($this->root . '/' . $folder);
            }
        }
    }

    // A picture is kept under a fresh name, the previous one removed; removing it empties the card
    public function testStoreReplacesThenRemoves(): void
    {
        $images = new CardImages($this->root);
        $card = new Card(new Project('catalog', 'Catalog'), 'item-1');
        new \ReflectionProperty(Card::class, 'id')->setValue($card, 7);

        $this->assertTrue($images->store($card, $this->upload('item-1.png')));
        $first = (string) $images->path($card);
        $this->assertFileExists($first);
        $this->assertSame('image/png', $images->type($first));

        $this->assertTrue($images->store($card, $this->upload('item-2.png')));
        $this->assertFileDoesNotExist($first);
        $this->assertStringStartsWith('card-7-', (string) $card->getImage());

        $second = (string) $images->path($card);
        $images->remove($card);
        $this->assertNull($card->getImage());
        $this->assertFileDoesNotExist($second);
    }

    // A file that is no picture, or whose bytes do not match its extension, is refused and the card keeps nothing
    public function testStoreRefusesWhatIsNoPicture(): void
    {
        $images = new CardImages($this->root);
        $card = new Card(new Project('catalog', 'Catalog'), 'item-1');
        $text = tempnam(sys_get_temp_dir(), 'pilot');
        file_put_contents($text, 'item-1');

        $this->assertFalse($images->store($card, new UploadedFile($text, 'item-1.txt', null, null, true)));
        $this->assertFalse($images->store($card, new UploadedFile($text, 'item-1.png', null, null, true)));
        $this->assertFalse($images->store($card, $this->upload('item-1.jpg')));
        $this->assertNull($card->getImage());
        unlink($text);
    }

    // A one-pixel PNG sent under the given name
    private function upload(string $name): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'pilot');
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=', true));

        return new UploadedFile($path, $name, null, null, true);
    }
}
