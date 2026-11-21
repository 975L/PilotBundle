<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Twig;

use Twig\Attribute\AsTwigFunction;

// The bundle's icons, SVG files of public/icons written into the page so that they take the color of the text around them
class PilotIconExtension
{
    // Where the icons are kept, relative to the bundle
    public const string DIRECTORY = __DIR__ . '/../../public/icons';

    /** @var array<string, string> */
    private array $read = [];

    // The icon of that name as inline SVG, hidden from assistive technologies, with its own classes added; an unknown name is a mistake of the template
    #[AsTwigFunction('pilot_icon', isSafe: ['html'])]
    public function icon(string $name, string $class = ''): string
    {
        $svg = $this->read[$name] ??= $this->read($name);
        $classes = trim('pilot-icon pilot-icon-' . $name . ' ' . preg_replace('/[^A-Za-z0-9 _-]/', '', $class));

        return '<svg class="' . $classes . '" aria-hidden="true" focusable="false"' . $svg;
    }

    // The file's viewBox and drawing, its license comment left in the file
    private function read(string $name): string
    {
        $path = self::DIRECTORY . '/' . $name . '.svg';
        if (1 !== preg_match('/^[a-z0-9-]+$/', $name) || !is_file($path)) {
            throw new \InvalidArgumentException(\sprintf('The icon "%s" is not in %s.', $name, self::DIRECTORY));
        }
        $svg = (string) preg_replace('/<!--.*?-->/s', '', (string) file_get_contents($path));

        return (string) preg_replace('/^<svg(?:[^>]*?(\sviewBox="[^"]*"))?[^>]*>/', '$1>', $svg);
    }
}
