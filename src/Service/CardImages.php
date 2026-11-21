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

// The picture of a card (a sheet's portrait), kept under Card::IMAGE_DIRECTORY, and the screenshots of its feedback, kept under Card::FEEDBACK_IMAGE_DIRECTORY, each with the type it is served under
class CardImages
{
    // Name of a feedback's screenshot: 128 random bits, the name being what lets it be read
    public const string FEEDBACK_NAME = '[0-9a-f]{32}\\.(?:png|jpe?g|webp|gif)';

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
        $extension = $this->extension($file);
        if (null === $extension) {
            return false;
        }
        $this->remove($card);
        $name = 'card-' . $card->getId() . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
        $file->move($this->folder(), $name);
        $card->setImage($name);

        return true;
    }

    // A screenshot sent for a feedback kept under a fresh name it returns, null when it is no picture, its bytes and extension disagreeing, or too heavy
    public function storeFeedback(UploadedFile $file): ?string
    {
        $extension = $this->extension($file);
        if (null === $extension) {
            return null;
        }
        $name = bin2hex(random_bytes(16)) . '.' . $extension;
        $file->move($this->projectDir . '/' . Card::FEEDBACK_IMAGE_DIRECTORY, $name);

        return $name;
    }

    // Real path of a feedback's screenshot, null for a name it could not have or a missing file
    public function feedbackPath(string $name): ?string
    {
        $path = $this->projectDir . '/' . Card::FEEDBACK_IMAGE_DIRECTORY . '/' . $name;

        return 1 === preg_match('/^' . self::FEEDBACK_NAME . '$/', $name) && is_file($path) ? $path : null;
    }

    // The card's screenshots copied under fresh names its feedback now holds, a missing file dropped and one that could not be copied keeping its name, so the addresses a revoked link handed out die once the caller forgets the old names after writing the card
    public function renewFeedback(Card $card): Card
    {
        foreach ($card->getFeedback() as $index => $feedback) {
            $path = isset($feedback['img']) ? $this->feedbackPath($feedback['img']) : null;
            $name = null;
            if (null !== $path) {
                $name = bin2hex(random_bytes(16)) . '.' . pathinfo($path, \PATHINFO_EXTENSION);
                $name = copy($path, \dirname($path) . '/' . $name) ? $name : $feedback['img'];
            }
            $card->setFeedbackImage($index, $name);
        }

        return $card;
    }

    // The screenshots no longer held by any feedback removed from the disk
    /** @param list<string> $names */
    public function forget(array $names): void
    {
        foreach ($names as $name) {
            $path = $this->feedbackPath($name);
            if (null !== $path) {
                unlink($path);
            }
        }
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

    // Extension a picture sent is kept under, null when it is no picture, its bytes and extension disagreeing, or too heavy
    private function extension(UploadedFile $file): ?string
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $type = self::TYPES[$extension] ?? null;

        return null !== $type && $file->isValid() && $file->getSize() <= self::MAX_BYTES && $type === $this->read($file->getPathname()) ? $extension : null;
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
