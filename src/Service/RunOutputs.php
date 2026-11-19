<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Service;

use c975L\PilotBundle\Entity\Run;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

// Files a run produced, sent by the machine and kept under private/pilot, with the kind of preview each one gets
class RunOutputs
{
    // Where the files are kept, relative to the project directory
    public const string DIRECTORY = 'private/pilot/runs';

    // Largest file the machine sends, a bigger one being only named on the card
    public const int MAX_BYTES = 100 * 1024 * 1024;

    private const array TEXT = ['txt', 'md', 'log', 'json', 'csv'];

    // Images and videos shown on the card, with the type they are served under
    private const array MEDIA = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'gif' => 'image/gif', 'mp4' => 'video/mp4', 'webm' => 'video/webm'];

    public function __construct(
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
    }

    // Keeps a file sent for a run under its own name, without any folder part
    public function store(Run $run, UploadedFile $file): string
    {
        $name = $this->safeName($file->getClientOriginalName());
        $file->move($this->folder($run), $name);

        return $name;
    }

    // Real path of a kept file of a run, null when missing
    public function path(Run $run, string $name): ?string
    {
        $path = $this->folder($run) . '/' . $this->safeName($name);

        return is_file($path) ? $path : null;
    }

    // The files kept in a run's folder removed with it, the card it worked for being deleted
    public function forget(string $folder): void
    {
        foreach (glob($folder . '/*') ?: [] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        if (is_dir($folder)) {
            rmdir($folder);
        }
    }

    // Preview of every output of a run: name, kind (text, image, video, file, elsewhere when only the machine holds it) and text content
    /** @return list<array{name: string, kind: string, text: ?string}> */
    public function previews(Run $run): array
    {
        $previews = [];
        foreach ($run->getOutputs() as $output) {
            $name = $this->safeName($output);
            $path = $this->path($run, $name);
            $kind = null === $path ? 'elsewhere' : $this->kind($path);
            $previews[] = [
                'name' => null === $path ? $output : $name,
                'kind' => $kind,
                'text' => 'text' === $kind ? (string) file_get_contents($path, false, null, 0, 4000) : null,
            ];
        }

        return $previews;
    }

    // Folder of a run
    public function folder(Run $run): string
    {
        return $this->projectDir . '/' . self::DIRECTORY . '/' . $run->getId();
    }

    // A file name reduced to letters, digits, dots, dashes and underscores, never a hidden one
    private function safeName(string $name): string
    {
        $name = ltrim((string) preg_replace('/[^A-Za-z0-9._-]/', '_', basename($name)), '.');

        return '' === $name ? 'output' : $name;
    }

    // Type an image or a video is served under, from its extension, null for any other file
    public function mediaType(string $path): ?string
    {
        return self::MEDIA[strtolower(pathinfo($path, \PATHINFO_EXTENSION))] ?? null;
    }

    // Preview kind from the extension
    private function kind(string $path): string
    {
        $media = $this->mediaType($path);
        if (null !== $media) {
            return strstr($media, '/', true) ?: 'file';
        }

        return \in_array(strtolower(pathinfo($path, \PATHINFO_EXTENSION)), self::TEXT, true) ? 'text' : 'file';
    }
}
