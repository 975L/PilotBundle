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
use c975L\PilotBundle\Entity\Run;
use c975L\PilotBundle\Enum\RunStatus;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

// Files a run produced, sent by the machine and kept under private/pilot, with the kind of preview each one gets
class RunOutputs
{
    // Where the files are kept, relative to the project directory
    public const string DIRECTORY = 'private/pilot/runs';

    // Largest file the machine sends, a bigger one being only named on the card
    public const int MAX_BYTES = 100 * 1024 * 1024;

    // Most files and bytes a run keeps on the site, all its files together
    public const int MAX_FILES = 200;
    public const int MAX_RUN_BYTES = 500 * 1024 * 1024;

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

    // Whether the run's folder takes the file within its ceilings of files and bytes, a file of the same name it replaces left out of the count
    public function fits(Run $run, UploadedFile $file): bool
    {
        $replaced = $this->path($run, $file->getClientOriginalName());
        $kept = array_values(array_filter(glob($this->folder($run) . '/*') ?: [], static fn (string $path): bool => is_file($path) && $path !== $replaced));
        $bytes = array_sum(array_map(static fn (string $path): int => (int) filesize($path), $kept));

        return \count($kept) < self::MAX_FILES && $bytes + (int) $file->getSize() <= self::MAX_RUN_BYTES;
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

    // Files of each step from its last run that produced any, a failed or withdrawn one or one whose step left the checklist left aside, with the run they belong to, by name and kind without reading them; a markdown answer is the machine's account of its work, not a file it made, and is left out
    /** @return array<int, array{run: Run, files: list<array{name: string, kind: string}>}> */
    public function ofSteps(Card $card): array
    {
        $steps = [];
        foreach ($card->getRuns() as $run) {
            $step = $run->getStep();
            $made = array_filter($run->getOutputs(), static fn (string $path): bool => 'md' !== strtolower(pathinfo($path, \PATHINFO_EXTENSION)));
            if (null !== $step && Run::LEFT_STEP !== $step && [] !== $made && \in_array($run->getStatus(), [RunStatus::Done, RunStatus::Blocked], true)) {
                $steps[$step] = ['run' => $run, 'files' => array_values(array_filter($this->entries($run), static fn (array $file): bool => 'md' !== strtolower(pathinfo($file['name'], \PATHINFO_EXTENSION))))];
            }
        }

        return $steps;
    }

    // Name and kind (text, image, video, file, elsewhere when only the machine holds it) of every output of a run
    /** @return list<array{name: string, kind: string}> */
    private function entries(Run $run): array
    {
        $entries = [];
        foreach ($run->getOutputs() as $output) {
            $name = $this->safeName($output);
            $path = $this->path($run, $name);
            $entries[] = ['name' => null === $path ? $output : $name, 'kind' => null === $path ? 'elsewhere' : $this->kind($path)];
        }

        return $entries;
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
