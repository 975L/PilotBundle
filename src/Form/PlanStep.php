<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Form;

use c975L\PilotBundle\Enum\Executor;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

// One step of a plan template while it is edited, its title naming it in the list of steps; the template keeps it as a plain array
#[Exclude]
class PlanStep implements \Stringable
{
    /** @param array<string, string> $params */
    public function __construct(
        public string $title = '',
        public string $executor = Executor::Human->value,
        public ?string $action = null,
        public array $params = [],
    ) {
    }

    public function __toString(): string
    {
        return $this->title;
    }

    // A step read from the template
    /** @param array{title?: string, executor?: string, action?: ?string, params?: array<string, string>} $step */
    public static function fromArray(array $step): self
    {
        return new self($step['title'] ?? '', $step['executor'] ?? Executor::Human->value, $step['action'] ?? null, $step['params'] ?? []);
    }

    // The step as the template keeps it, without the empty parts
    /** @return array{title: string, executor: string, action?: string, params?: array<string, string>} */
    public function toArray(): array
    {
        return array_filter(['title' => $this->title, 'executor' => $this->executor, 'action' => $this->action, 'params' => $this->params], static fn (mixed $value): bool => null !== $value && [] !== $value);
    }
}
