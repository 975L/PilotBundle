<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Service;

use c975L\PilotBundle\Entity\Doc;

// Reads an export of the Pilotage artifact database (one JSON file per document, under a folder per collection) into plain rows, without touching the database
class ArtifactReader
{
    // Projects, files and cards found in the export folder
    /**
     * @return array{projects: list<array{slug: string, name: string, color: ?string, sites: list<string>, position: int}>, docs: list<array{slug: string, title: string, project: ?string, kind: string, summary: ?string, detail: ?string}>, cards: list<array{id: string, project: string, title: string, note: ?string, due: ?string, doc: ?string, doneAt: ?string, checklist: list<array{t: string, done?: bool, doneAt?: string}>}>}
     */
    public function read(string $dir): array
    {
        if (!is_dir($dir)) {
            throw new \InvalidArgumentException(sprintf('No export folder at "%s".', $dir));
        }

        $docs = array_merge($this->docs($dir), $this->characters($dir));
        $cards = [...$this->cards($dir), ...$this->deadlines($dir)];
        $projects = $this->projects($dir, array_merge(array_column($docs, 'project'), array_column($cards, 'project')));

        return ['projects' => $projects, 'docs' => $docs, 'cards' => $cards];
    }

    // Projects of settings/projects in the layout order, plus any project only named by a file or a card
    /**
     * @param list<?string> $named
     *
     * @return list<array{slug: string, name: string, color: ?string, sites: list<string>, position: int}>
     */
    private function projects(string $dir, array $named): array
    {
        $order = $this->json($dir . '/settings/layout.json')['order'] ?? [];
        $projects = [];
        foreach ($this->json($dir . '/settings/projects.json')['items'] ?? [] as $item) {
            $projects[$item['id']] = ['slug' => $item['id'], 'name' => $item['name'] ?? $item['id'], 'color' => $item['color'] ?? null, 'sites' => array_values($item['sites'] ?? [])];
        }
        foreach (array_filter($named) as $slug) {
            $projects[$slug] ??= ['slug' => $slug, 'name' => $slug, 'color' => null, 'sites' => []];
        }

        $rows = [];
        foreach (array_values($projects) as $index => $project) {
            $position = array_search($project['slug'], $order, true);
            $rows[] = $project + ['position' => false === $position ? 1000 + $index : $position];
        }

        return $rows;
    }

    // Files and charters, the summary taken from their "L'essentiel" section when they have one
    /**
     * @return list<array{slug: string, title: string, project: ?string, kind: string, summary: ?string, detail: ?string}>
     */
    private function docs(string $dir): array
    {
        $rows = [];
        foreach ($this->files($dir . '/docs') as $slug => $doc) {
            [$summary, $detail] = $this->split((string) ($doc['markdown'] ?? ''));
            $rows[] = ['slug' => $slug, 'title' => $doc['title'] ?? $slug, 'project' => $doc['project'] ?? null, 'kind' => 'charte' === ($doc['kind'] ?? null) ? 'charter' : 'file', 'summary' => $summary, 'detail' => $detail];
        }

        return $rows;
    }

    // Character sheets, turned into a document whose detail lists their fields
    /**
     * @return list<array{slug: string, title: string, project: ?string, kind: string, summary: ?string, detail: ?string}>
     */
    private function characters(string $dir): array
    {
        $rows = [];
        foreach ($this->files($dir . '/characters') as $id => $character) {
            $lines = [];
            foreach ([['Description', $character['desc'] ?? null], ...array_map(static fn (array $info): array => [$info[0], $info[1]], $character['info'] ?? []), ['Running gags', $character['gags'] ?? null], ['Punchlines', $character['punchlines'] ?? null], ['Prompt Leonardo', $character['prompt'] ?? null]] as [$label, $value]) {
                if (null !== $value && '' !== $value) {
                    $lines[] = '**' . $label . '** : ' . $value;
                }
            }
            $rows[] = ['slug' => 'character-' . $id, 'title' => $character['name'] ?? $id, 'project' => $character['project'] ?? null, 'kind' => 'character', 'summary' => $character['role'] ?? null, 'detail' => implode("\n\n", $lines)];
        }

        return $rows;
    }

    // Open cards and done ones of every project file under tasks/
    /**
     * @return list<array{id: string, project: string, title: string, note: ?string, due: ?string, doc: ?string, doneAt: ?string, checklist: list<array{t: string, done?: bool, doneAt?: string}>}>
     */
    private function cards(string $dir): array
    {
        $rows = [];
        foreach ($this->files($dir . '/tasks') as $project => $tasks) {
            foreach ([...$tasks['items'] ?? [], ...$tasks['done'] ?? []] as $task) {
                $rows[] = [
                    'id' => (string) $task['id'],
                    'project' => (string) $project,
                    'title' => (string) $task['title'],
                    'note' => $task['note'] ?? null,
                    'due' => $task['due'] ?? null,
                    'doc' => $task['doc'] ?? null,
                    'doneAt' => $task['doneAt'] ?? null,
                    'checklist' => array_values($task['steps'] ?? []),
                ];
            }
        }

        return $rows;
    }

    // The dated deadlines of the planning collection, each a card whose note says what is left to do
    /** @return list<array{id: string, project: string, title: string, note: ?string, due: ?string, doc: ?string, doneAt: ?string, checklist: list<array{t: string, done?: bool, doneAt?: string}>}> */
    private function deadlines(string $dir): array
    {
        $rows = [];
        foreach ($this->files($dir . '/planning') as $id => $deadline) {
            $note = implode("\n\n", array_filter([$deadline['todo'] ?? null, $deadline['note'] ?? null]));
            $rows[] = [
                'id' => 'planning-' . $id,
                'project' => (string) ($deadline['project'] ?? ''),
                'title' => (string) ($deadline['title'] ?? $id),
                'note' => '' === $note ? null : $note,
                'due' => $deadline['date'] ?? null,
                'doc' => $deadline['doc'] ?? null,
                'doneAt' => null,
                'checklist' => [],
            ];
        }

        return $rows;
    }

    // Summary and detail of a markdown: the "L'essentiel" section, then the rest without its folding tags
    /**
     * @return array{?string, ?string}
     */
    private function split(string $markdown): array
    {
        $start = strpos($markdown, Doc::SUMMARY_HEADING);
        if (false === $start) {
            return [null, '' === trim($markdown) ? null : trim($markdown)];
        }
        $after = substr($markdown, $start + \strlen(Doc::SUMMARY_HEADING));
        $end = strpos($after, '<details>');
        $summary = trim(false === $end ? $after : substr($after, 0, $end));
        $detail = trim(substr($markdown, 0, $start) . (false === $end ? '' : substr($after, $end)));
        $detail = trim((string) preg_replace('~</?details>|<summary>.*?</summary>~s', '', $detail));

        return [$summary, '' === $detail ? null : $detail];
    }

    // Every JSON file of a collection folder, by document id
    /**
     * @return array<string, array<string, mixed>>
     */
    private function files(string $dir): array
    {
        $files = [];
        foreach (glob($dir . '/*.json') ?: [] as $file) {
            $files[basename($file, '.json')] = $this->json($file);
        }
        ksort($files);

        return $files;
    }

    // A JSON file decoded, empty when missing or unreadable
    /**
     * @return array<string, mixed>
     */
    private function json(string $file): array
    {
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        return \is_array($data) ? $data : [];
    }
}
