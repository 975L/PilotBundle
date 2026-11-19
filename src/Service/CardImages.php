<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Service;

use c975L\PilotBundle\Entity\Card;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

// The picture of a card (a sheet's portrait), kept under Card::IMAGE_DIRECTORY with the type it is served under
class CardImages
{
    // The pictures accepted, by the extension they are kept and served under
    public const array TYPES = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'gif' => 'image/gif'];

    // Largest picture kept
    public const int MAX_BYTES = 10 * 1024 * 1024;

    public function __construct(
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
    }

    // A picture sent for the card kept under a fresh name, the one it had removed; false when it is no picture, its bytes and extension disagreeing, or too heavy
    public function store(Card $card, UploadedFile $file): bool
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $type = self::TYPES[$extension] ?? null;
        if (null === $type || !$file->isValid() || $file->getSize() > self::MAX_BYTES || $type !== $this->read($file->getPathname())) {
            return false;
        }
        $this->remove($card);
        $name = 'card-' . $card->getId() . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
        $file->move($this->folder(), $name);
        $card->setImage($name);

        return true;
    }

    // The card's picture removed from the disk and from the card
    public function remove(Card $card): void
    {
        $path = $this->path($card);
        if (null !== $path) {
            unlink($path);
        }
        $card->setImage(null);
    }

    // Real path of the card's picture, null when it has none or the file is missing
    public function path(Card $card): ?string
    {
        $name = $card->getImage();
        $path = $this->folder() . '/' . basename((string) $name);

        return null !== $name && is_file($path) ? $path : null;
    }

    // Type the picture is served under, from its extension
    public function type(string $path): ?string
    {
        return self::TYPES[strtolower(pathinfo($path, \PATHINFO_EXTENSION))] ?? null;
    }

    // Type the file's own bytes tell, null when it is no picture
    private function read(string $path): ?string
    {
        $size = @getimagesize($path);

        return false === $size ? null : $size['mime'];
    }

    private function folder(): string
    {
        return $this->projectDir . '/' . Card::IMAGE_DIRECTORY;
    }
}
