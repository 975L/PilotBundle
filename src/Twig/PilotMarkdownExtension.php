<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Twig;

use League\CommonMark\GithubFlavoredMarkdownConverter;
use Twig\Attribute\AsTwigFilter;

// The files' markdown as GitHub writes it (tables, task lists), any HTML typed in escaped and unsafe links dropped
class PilotMarkdownExtension
{
    private ?GithubFlavoredMarkdownConverter $converter = null;

    // The markdown turned into HTML, the converter built once
    #[AsTwigFilter('pilot_markdown', isSafe: ['html'])]
    public function convert(?string $markdown): string
    {
        $this->converter ??= new GithubFlavoredMarkdownConverter(['html_input' => 'escape', 'allow_unsafe_links' => false]);

        return $this->converter->convert((string) $markdown)->getContent();
    }
}
