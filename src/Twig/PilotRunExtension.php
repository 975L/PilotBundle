<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\PilotBundle\Twig;

use c975L\PilotBundle\Entity\Run;
use c975L\PilotBundle\Repository\RunRepository;
use Psr\Clock\ClockInterface;
use Twig\Attribute\AsTwigFunction;

// The progress shown for a run under way: what its action reports, or an estimate from the time its action usually takes when that is further along
class PilotRunExtension
{
    // An estimate never says done, the run alone does
    public const int ESTIMATE_MAX = 95;

    /** @var array<string, ?int> */
    private array $typical = [];

    public function __construct(
        private readonly RunRepository $runRepository,
        private readonly ClockInterface $clock,
    ) {
    }

    // The value to show and whether it is an estimate, the action's median duration read once per page
    /** @return array{value: int, estimated: bool} */
    #[AsTwigFunction('pilot_run_progress')]
    public function progress(Run $run): array
    {
        $action = (string) $run->getAction();
        $typical = \array_key_exists($action, $this->typical) ? $this->typical[$action] : $this->typical[$action] = $this->runRepository->typicalSeconds($action);
        $elapsed = $this->clock->now()->getTimestamp() - $run->getStartedAt()->getTimestamp();
        $estimate = null === $typical ? 0 : min(self::ESTIMATE_MAX, intdiv(100 * max(0, $elapsed), $typical));

        return $estimate > $run->getProgress() ? ['value' => $estimate, 'estimated' => true] : ['value' => $run->getProgress(), 'estimated' => false];
    }
}
