<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Form;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

// One step of a plan template while it is edited, its title naming it in the list of steps, its group gathering it with its neighbours on a card; the template keeps it as a plain array
#[Exclude]
class PlanStep implements \Stringable
{
    // Name a parameter may take, on the page as through the MCP
    public const string PARAM_NAME = '/^\w{1,30}$/';

    /** @param array<string, string> $params */
    public function __construct(
        public string $title = '',
        public ?string $action = null,
        public array $params = [],
        public string $after = '',
        public string $group = '',
    ) {
    }

    public function __toString(): string
    {
        return $this->title;
    }

    // Parameters written one "name=value" per line, or on one line joined by the glue given
    /** @param array<string, string> $params */
    public static function paramsText(array $params, string $glue = "\n"): string
    {
        $lines = [];
        foreach ($params as $name => $value) {
            $lines[] = $name . '=' . $value;
        }

        return implode($glue, $lines);
    }

    // Parameters read back from their lines or from a line split by « ; », a part without "=" or without a name dropped
    /** @return array<string, string> */
    public static function paramsFrom(string $text): array
    {
        $params = [];
        foreach (preg_split('/\R|;/', $text) ?: [] as $part) {
            [$name, $value] = array_pad(explode('=', $part, 2), 2, null);
            if (null !== $value && 1 === preg_match(self::PARAM_NAME, trim($name))) {
                $params[trim($name)] = trim($value);
            }
        }

        return $params;
    }

    // A step read from the template, the steps it waits for shown as their numbers, « - » for none
    /** @param array{title?: string, action?: ?string, params?: array<string, string>, after?: list<int>, group?: string} $step */
    public static function fromArray(array $step): self
    {
        $after = isset($step['after']) ? ([] === $step['after'] ? '-' : implode(', ', $step['after'])) : '';

        return new self($step['title'] ?? '', $step['action'] ?? null, $step['params'] ?? [], $after, $step['group'] ?? '');
    }

    // The step as the template keeps it, without the empty parts; « after » only when typed, the step before being waited for otherwise
    /** @return array{title: string, action?: string, params?: array<string, string>, after?: list<int>, group?: string} */
    public function toArray(): array
    {
        $step = array_filter(['title' => $this->title, 'action' => $this->action, 'params' => $this->params], static fn (mixed $value): bool => null !== $value && [] !== $value);
        $after = trim($this->after);
        if ('' !== $after) {
            $step['after'] = '-' === $after ? [] : array_values(array_unique(array_map(intval(...), preg_split('/\D+/', $after, -1, \PREG_SPLIT_NO_EMPTY) ?: [])));
        }

        $group = trim($this->group);
        if ('' !== $group) {
            $step['group'] = $group;
        }

        return $step;
    }
}
